<?php
require_once './../auth00/admin_auth.php';
protectAdminPage('admin_login');
if (!checkAdminAuthTimeout()) {
    header("Location: admin_login");
    exit();
}
logAdminActivity('Accessed Admin Dashboard');
$currentAdmin = getCurrentAdmin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="./../assets/css/admin_style.css?v=<?php echo filemtime('./../assets/css/admin_style.css'); ?>?v=<?php echo filemtime('./../assets/css/admin_style.css'); ?>" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p>Manage users and Purchase Receipts</p>
                </div>
                <div class="admin-info">
                    <span>Welcome, Admin</span>
                    <a href="admin_logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
        <div class="stats-grid" id="stats-grid">
        </div>
        <div class="search-bar-wrapper">
            <div class="search-bar" id="searchBar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search users by name, email, or mobile..." onkeyup="searchUsers()">
                </div>
            </div>
            <div class="search-bar-placeholder" id="searchBarPlaceholder"></div>
        </div>
        <div class="users-table">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Email Verified</th>
                        <th>Files Count</th>
                        <th>Total File Size</th>
                        <th>Balance</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                </tbody>
            </table>
            <div class="pagination" id="pagination">
            </div>
        </div>
    </div>
    <div id="filesModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">User Files</h2>
            <div id="filesContainer">
            </div>
        </div>
    </div>
    <script>
        let currentPage = 1;
        let searchTerm = '';
        let searchBarOriginalPosition = 0;
        let isSearchBarSticky = false;

        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadUsers();
            setupModal();
            setupStickySearchBar();

            const searchBar = document.getElementById('searchBar');
            if (searchBar) {
                searchBarOriginalPosition = searchBar.offsetTop;
            }
        });

        function setupStickySearchBar() {
            const searchBar = document.getElementById('searchBar');
            const searchBarPlaceholder = document.getElementById('searchBarPlaceholder');
            if (!searchBar) return;
            setTimeout(() => {
                searchBarOriginalPosition = searchBar.offsetTop;
            }, 100);
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollTop > searchBarOriginalPosition && !isSearchBarSticky) {
                    searchBar.classList.add('sticky');
                    if (searchBarPlaceholder) {
                        searchBarPlaceholder.classList.add('active');
                    }
                    isSearchBarSticky = true;
                } else if (scrollTop <= searchBarOriginalPosition && isSearchBarSticky) {
                    searchBar.classList.remove('sticky');
                    if (searchBarPlaceholder) {
                        searchBarPlaceholder.classList.remove('active');
                    }
                    isSearchBarSticky = false;
                }
            });
        }

        function loadStats() {
            fetch('admin_api.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statsGrid = document.getElementById('stats-grid');
                        statsGrid.innerHTML = `
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <div class="number">${data.stats.total_users}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Users</h3>
                        <div class="number">${data.stats.active_users}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Purchase Receipts</h3>
                        <div class="number">${data.stats.total_certificates}</div>
                        <small>Active: ${data.stats.active_certificates || 0} | Disabled: ${data.stats.disabled_certificates || 0}</small>
                    </div>
                    <div class="stat-card">
                        <h3>Users with Purchase Receipts</h3>
                        <div class="number">${data.stats.users_with_certificates}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Email Verified</h3>
                        <div class="number">${data.stats.email_verified}</div>
                    </div>
                `;
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }

        function loadUsers(page = 1) {
            currentPage = page;
            const url = `admin_api.php?action=get_users&page=${page}&search=${encodeURIComponent(searchTerm)}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUsers(data.users);
                        displayPagination(data.pagination);
                    }
                })
                .catch(error => console.error('Error loading users:', error));
        }

        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = parseFloat(bytes) || 0;
            let unitIndex = 0;
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
        }

        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            users.forEach(user => {
                const row = document.createElement('tr');
                let fileCountDisplay = user.file_count;
                if (user.active_file_count !== undefined && user.disabled_file_count !== undefined) {
                    const activeCount = user.active_file_count || 0;
                    const disabledCount = user.disabled_file_count || 0;
                    if (disabledCount > 0) {
                        fileCountDisplay = `${user.file_count} (${activeCount} active, ${disabledCount} disabled)`;
                    }
                }
                row.innerHTML = `
            <td>${user.id}</td>
            <td>${user.firstname} ${user.lastname}</td>
            <td>${user.email}</td>
            <td>${user.mobile}</td>
            <td>
                <span class="status-badge ${user.status == 1 ? 'status-active' : 'status-inactive'}">
                    ${user.status == 1 ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td class="${user.ev == 1 ? 'verified' : 'not-verified'}">
                ${user.ev == 1 ? '✓' : '✗'}
            </td>
            <td class="files-count">
                <span class="file-count-badge" title="${fileCountDisplay}">${user.file_count}</span>
            </td>
            <td class="file-size">
                <span class="file-size-badge">${formatFileSize(user.total_file_size)}</span>
            </td>
            <td>₦${parseFloat(user.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>
                <button class="view-files-btn" onclick="viewUserFiles(${user.id}, '${user.firstname} ${user.lastname}')">
                    View Files
                </button>
            </td>
        `;
                tbody.appendChild(row);
            });
        }

        function displayPagination(pagination) {
            const paginationDiv = document.getElementById('pagination');
            paginationDiv.innerHTML = '';
            if (pagination.totalPages <= 1) return;
            if (pagination.page > 1) {
                paginationDiv.innerHTML += `<a href="#" onclick="loadUsers(${pagination.page - 1})">&laquo; Previous</a>`;
            }
            for (let i = 1; i <= pagination.totalPages; i++) {
                if (i === pagination.page) {
                    paginationDiv.innerHTML += `<span class="current">${i}</span>`;
                } else {
                    paginationDiv.innerHTML += `<a href="#" onclick="loadUsers(${i})">${i}</a>`;
                }
            }
            if (pagination.page < pagination.totalPages) {
                paginationDiv.innerHTML += `<a href="#" onclick="loadUsers(${pagination.page + 1})">Next &raquo;</a>`;
            }
        }

        function searchUsers() {
            const searchInput = document.getElementById('searchInput');
            searchTerm = searchInput.value.trim();
            loadUsers(1);
        }

        function viewUserFiles(userId, userName) {
            const modal = document.getElementById('filesModal');
            const modalTitle = document.getElementById('modalTitle');
            const filesContainer = document.getElementById('filesContainer');
            modalTitle.textContent = `Files for ${userName}`;
            filesContainer.innerHTML = '<p>Loading files...</p>';
            modal.style.display = 'block';
            fetch(`admin_api.php?action=get_user_files&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFiles(data.files);
                    } else {
                        filesContainer.innerHTML = '<p class="no-files">Error loading files</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading files:', error);
                    filesContainer.innerHTML = '<p class="no-files">Error loading files</p>';
                });
        }

        function renewFileDownloads(fileId, fileName) {
            if (confirm(`Are you sure you want to renew download count for "${fileName}"?\n\nThis will reset the download count to 0, allowing the file to be downloaded again up to the maximum limit.`)) {
                fetch('admin_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=renew_file_downloads&file_id=${fileId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Download count renewed successfully');
                            refreshCurrentModal();
                        } else {
                            alert('Error renewing download count: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error renewing download count:', error);
                        alert('Error renewing download count');
                    });
            }
        }

        function displayFiles(files) {
            const filesContainer = document.getElementById('filesContainer');
            if (files.length === 0) {
                filesContainer.innerHTML = '<p class="no-files">No files uploaded by this user</p>';
                return;
            }
            filesContainer.innerHTML = '';
            files.forEach(file => {
                const fileDiv = document.createElement('div');
                fileDiv.className = 'file-item';
                const isDisabled = file.deleted == 1;
                const statusClass = isDisabled ? 'file-disabled' : 'file-active';
                const statusText = isDisabled ? 'DISABLED' : 'ACTIVE';

                const actionButton = isDisabled ?
                    `<button class="restore-btn" onclick="restoreFile(${file.id})">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.52185 7H7C7.55229 7 8 7.44772 8 8C8 8.55229 7.55228 9 7 9H3C1.89543 9 1 8.10457 1 7V3C1 2.44772 1.44772 2 2 2C2.55228 2 3 2.44772 3 3V5.6754C4.26953 3.8688 6.06062 2.47676 8.14852 1.69631C10.6633 0.756291 13.435 0.768419 15.9415 1.73041C18.448 2.69239 20.5161 4.53782 21.7562 6.91897C22.9963 9.30013 23.3228 12.0526 22.6741 14.6578C22.0254 17.263 20.4464 19.541 18.2345 21.0626C16.0226 22.5842 13.3306 23.2444 10.6657 22.9188C8.00083 22.5931 5.54702 21.3041 3.76664 19.2946C2.20818 17.5356 1.25993 15.3309 1.04625 13.0078C0.995657 12.4579 1.45216 12.0088 2.00445 12.0084C2.55673 12.0079 3.00351 12.4566 3.06526 13.0055C3.27138 14.8374 4.03712 16.5706 5.27027 17.9625C6.7255 19.605 8.73118 20.6586 10.9094 20.9247C13.0876 21.1909 15.288 20.6513 17.0959 19.4075C18.9039 18.1638 20.1945 16.3018 20.7247 14.1724C21.2549 12.043 20.9881 9.79319 19.9745 7.8469C18.9608 5.90061 17.2704 4.3922 15.2217 3.6059C13.173 2.8196 10.9074 2.80968 8.8519 3.57803C7.11008 4.22911 5.62099 5.40094 4.57993 6.92229C4.56156 6.94914 4.54217 6.97505 4.52185 7Z" fill="currentColor"/>
                </svg>
                Restore
            </button>` :
                    `<button class="disable-btn" onclick="disableFile(${file.id})">
                <svg fill="currentColor" height="200px" width="200px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M366.473,172.549c-8.552-9.598-23.262-10.44-32.858-1.888c-9.595,8.552-10.44,23.263-1.887,32.858 c16.556,18.576,25.676,42.527,25.676,67.443c-0.002,55.913-45.49,101.402-101.404,101.402s-101.402-45.489-101.402-101.402 c0-24.913,9.118-48.863,25.678-67.443c8.552-9.595,7.705-24.308-1.89-32.86c-9.596-8.552-24.306-7.705-32.858,1.89 c-24.166,27.116-37.474,62.065-37.474,98.413C108.052,352.54,174.421,418.909,256,418.909s147.948-66.369,147.948-147.948 C403.948,234.611,390.639,199.661,366.473,172.549z"></path> </g> </g> <g> <g> <path d="M256,93.091c-12.853,0-23.273,10.42-23.273,23.273v99.739c0,12.853,10.42,23.273,23.273,23.273 c12.853,0,23.273-10.42,23.273-23.273v-99.739C279.273,103.511,268.853,93.091,256,93.091z"></path> </g> </g> <g> <g> <path d="M256,0C114.842,0,0,114.842,0,256s114.842,256,256,256c141.16,0,256-114.842,256-256S397.16,0,256,0z M256,465.455 c-115.493,0-209.455-93.961-209.455-209.455S140.507,46.545,256,46.545S465.455,140.507,465.455,256S371.493,465.455,256,465.455z "></path> </g> </g> </g></svg>
                Disable
            </button>`;

                const renewButton = !isDisabled ?
                    `<button class="renew-btn" onclick="renewFileDownloads(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
               <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6C8.69 6 6 8.69 6 12H9L5 16L1 12H4C4 7.58 7.58 4 12 4C13.57 4 15.03 4.46 16.26 5.24L14.8 6.7C13.97 6.25 13.01 6 12 6ZM15 12L19 8L23 12H20C20 16.42 16.42 20 12 20C10.43 20 8.97 19.54 7.74 18.76L9.2 17.3C10.03 17.75 10.99 18 12 18C15.31 18 18 15.31 18 12H15Z" fill="currentColor"></path> </g></svg>
                Renew
            </button>` : '';

                fileDiv.innerHTML = `
            <div class="file-header">
                <h3>${getFileIcon(file.mime_type)} ${file.original_filename}</h3>
                <span class="file-status ${statusClass}">${statusText}</span>
            </div>
            <div class="file-info">
                <div><strong>IMEI:</strong> ${file.imei}</div>
                <div><strong>Size:</strong> ${formatFileSize(file.file_size)}</div>
                <div><strong>Type:</strong> ${file.mime_type}</div>
                <div><strong>Downloads:</strong> ${file.download_count}/${file.max_downloads}</div>
                <div><strong>Uploaded:</strong> ${new Date(file.created_at).toLocaleString()}</div>
                <div><strong>Last Modified:</strong> ${new Date(file.updated_at).toLocaleString()}</div>
            </div>
            <div class="file-actions">
                <button class="download-btn" onclick="downloadFile('${file.filename}')">
                   <svg viewBox="0 0 24.00 24.00" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M3 15C3 17.8284 3 19.2426 3.87868 20.1213C4.75736 21 6.17157 21 9 21H15C17.8284 21 19.2426 21 20.1213 20.1213C21 19.2426 21 17.8284 21 15" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 3V16M12 16L16 11.625M12 16L8 11.625" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                    Download
                </button>
                ${file.mime_type.startsWith('image/') ? `<button class="view-files-btn" onclick="viewFile('${file.filename}', '${file.mime_type}')">
                   <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9ZM11 12C11 11.4477 11.4477 11 12 11C12.5523 11 13 11.4477 13 12C13 12.5523 12.5523 13 12 13C11.4477 13 11 12.5523 11 12Z" fill="currentColor"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M21.83 11.2807C19.542 7.15186 15.8122 5 12 5C8.18777 5 4.45796 7.15186 2.17003 11.2807C1.94637 11.6844 1.94361 12.1821 2.16029 12.5876C4.41183 16.8013 8.1628 19 12 19C15.8372 19 19.5882 16.8013 21.8397 12.5876C22.0564 12.1821 22.0536 11.6844 21.83 11.2807ZM12 17C9.06097 17 6.04052 15.3724 4.09173 11.9487C6.06862 8.59614 9.07319 7 12 7C14.9268 7 17.9314 8.59614 19.9083 11.9487C17.9595 15.3724 14.939 17 12 17Z" fill="currentColor"></path> </g></svg>
                    View
                </button>` : ''}
                ${renewButton}
                ${actionButton}
                <button class="delete-btn" onclick="permanentDeleteFile(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M10 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M6 10V18C6 19.6569 7.34315 21 9 21H15C16.6569 21 18 19.6569 18 18V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5V7H9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                    Delete
                </button>
            </div>
        `;
                filesContainer.appendChild(fileDiv);
            });
        }
        function getFileIcon(mimeType) {
            switch (mimeType) {
                case 'application/pdf':
                    return '📄';
                case 'image/jpeg':
                case 'image/jpg':
                case 'image/png':
                    return '🖼️';
                default:
                    return '📎';
            }
        }

        function downloadFile(filename, certificateId = null) {
            if (!filename) {
                alert('Filename is required');
                return;
            }
            const originalCursor = document.body.style.cursor;
            document.body.style.cursor = 'wait';
            const payload = {
                filename: filename
            };
            if (certificateId) {
                payload.certificate_id = certificateId;
            }
            fetch('download_recipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => {
                    const contentType = response.headers.get('Content-Type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().then(data => {
                            throw new Error(data.error || data.message || 'Download failed');
                        });
                    }
                    if (!response.ok) {
                        throw new Error(`Download failed: ${response.status} ${response.statusText}`);
                    }
                    return response.blob();
                })
                .then(blob => {
                    if (!blob) {
                        throw new Error('No file data received');
                    }
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    console.log('file downloaded successfully:', filename);
                    showNotification('File downloaded successfully', 'success');
                })
                .catch(error => {
                    console.error('download error:', error);
                    alert('Download failed: ' + error.message);
                    showNotification('Download failed: ' + error.message, 'error');
                })
                .finally(() => {
                    document.body.style.cursor = originalCursor;
                });
        }
function viewFile(filename, mimeType, originalFilename = null) {
    const displayName = originalFilename || filename;
    
    if (mimeType.startsWith('image/')) {
        showImageModal(filename, displayName);
    } else if (mimeType === 'application/pdf') {
        showPDFModal(filename, displayName);
    } else if (mimeType.startsWith('text/') || 
               mimeType === 'application/json' || 
               mimeType.includes('xml')) {
        showTextModal(filename, displayName, mimeType);
    } else if (mimeType.startsWith('video/')) {
        showVideoModal(filename, displayName);
    } else if (mimeType.startsWith('audio/')) {
        showAudioModal(filename, displayName);
    } else {
        showUnsupportedFileModal(filename, displayName, mimeType);
    }
}

function showImageModal(filename, displayName) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container">
                <div class="loading-spinner">Loading...</div>
                <img id="preview-image" src="" style="display: none; max-width: 100%; max-height: 70vh; margin: 20px 0;" 
                     onload="this.style.display='block'; this.parentElement.querySelector('.loading-spinner').style.display='none';"
                     onerror="showImageError(this)">
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 15C3 17.8284 3 19.2426 3.87868 20.1213C4.75736 21 6.17157 21 9 21H15C17.8284 21 19.2426 21 20.1213 20.1213C21 19.2426 21 17.8284 21 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M12 3V16M12 16L16 11.625M12 16L8 11.625" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    Download
                </button>
                <button class="view-files-btn" onclick="openInNewTab('./../certificates/${filename}')">
                    Open Externally
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    const img = modal.querySelector('#preview-image');
    img.src = `./../certificates/${filename}`;
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

function showPDFModal(filename, displayName) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content pdf-preview">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container">
                <iframe src="./../certificates/${filename}" 
                        style="width: 100%; height: 70vh; border: none;"
                        onload="this.style.display='block'"
                        onerror="showPDFError(this)">
                </iframe>
                <div class="pdf-fallback" style="display: none; text-align: center; padding: 40px;">
                    <p>PDF preview not available in this browser.</p>
                    <p>Use the buttons below to view the file.</p>
                </div>
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    Download PDF
                </button>
                <button class="external-btn" onclick="openInNewTab('./../certificates/${filename}')">
                    Open in New Tab
                </button>
                <button class="external-btn" onclick="openWithGoogleDocs('./../certificates/${filename}')">
                    View with Google Docs
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

function showTextModal(filename, displayName, mimeType) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content text-preview">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container">
                <div class="loading-spinner">Loading text content...</div>
                <pre id="text-content" style="display: none; white-space: pre-wrap; max-height: 60vh; overflow: auto; background: #f5f5f5; padding: 20px; border-radius: 8px;"></pre>
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    Download File
                </button>
                <button class="external-btn" onclick="openInNewTab('./../certificates/${filename}')">
                    Open in New Tab
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Load text content
    fetch(`./../certificates/${filename}`)
        .then(response => response.text())
        .then(text => {
            const textElement = modal.querySelector('#text-content');
            const loadingElement = modal.querySelector('.loading-spinner');
            
            textElement.textContent = text;
            textElement.style.display = 'block';
            loadingElement.style.display = 'none';
        })
        .catch(error => {
            const loadingElement = modal.querySelector('.loading-spinner');
            loadingElement.innerHTML = 'Error loading text content. <br><button onclick="downloadFile(\'' + filename + '\')">Download instead</button>';
        });
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

function showVideoModal(filename, displayName) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container">
                <video controls style="width: 100%; max-height: 70vh;">
                    <source src="./../certificates/${filename}" type="video/mp4">
                    <source src="./../certificates/${filename}" type="video/webm">
                    <source src="./../certificates/${filename}" type="video/ogg">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    Download Video
                </button>
                <button class="external-btn" onclick="openInNewTab('./../certificates/${filename}')">
                    Open in New Tab
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

function showAudioModal(filename, displayName) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container" style="text-align: center; padding: 40px;">
                <audio controls style="width: 100%; max-width: 500px;">
                    <source src="./../certificates/${filename}" type="audio/mpeg">
                    <source src="./../certificates/${filename}" type="audio/ogg">
                    <source src="./../certificates/${filename}" type="audio/wav">
                    Your browser does not support the audio element.
                </audio>
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    Download Audio
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

function showUnsupportedFileModal(filename, displayName, mimeType) {
    const modal = document.createElement('div');
    modal.className = 'modal preview-modal';
    modal.innerHTML = `
        <div class="modal-content preview-content">
            <div class="modal-header">
                <h3>${displayName}</h3>
                <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
            </div>
            <div class="preview-container" style="text-align: center; padding: 40px;">
                <div style="font-size: 48px; margin-bottom: 20px;">📄</div>
                <p><strong>File Type:</strong> ${mimeType}</p>
                <p>Preview not available for this file type.</p>
                <p>Use the options below to view or download the file.</p>
            </div>
            <div class="preview-actions">
                <button class="download-btn" onclick="downloadFile('${filename}')">
                    Download File
                </button>
                <button class="external-btn" onclick="openInNewTab('./../certificates/${filename}')">
                    Try Opening in Browser
                </button>
                <button class="external-btn" onclick="openWithGoogleDocs('./../certificates/${filename}')">
                    Try Google Docs Viewer
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.remove();
        }
    }
}

// Helper functions
function showImageError(imgElement) {
    imgElement.style.display = 'none';
    const container = imgElement.parentElement;
    const loadingSpinner = container.querySelector('.loading-spinner');
    loadingSpinner.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <p>❌ Could not load image</p>
            <p>The file might be corrupted or the path is incorrect.</p>
        </div>
    `;
}

function showPDFError(iframeElement) {
    iframeElement.style.display = 'none';
    const fallback = iframeElement.parentElement.querySelector('.pdf-fallback');
    if (fallback) {
        fallback.style.display = 'block';
    }
}

function openInNewTab(url) {
    window.open(url, '_blank');
}

function openWithGoogleDocs(url) {
    const fullUrl = window.location.origin + '/' + url;
    const googleDocsUrl = `https://docs.google.com/viewer?url=${encodeURIComponent(fullUrl)}`;
    window.open(googleDocsUrl, '_blank');
}

// Update the displayFiles function to pass original filename
function displayFiles(files) {
    const filesContainer = document.getElementById('filesContainer');
    if (files.length === 0) {
        filesContainer.innerHTML = '<p class="no-files">No files uploaded by this user</p>';
        return;
    }

    filesContainer.innerHTML = '';
    files.forEach(file => {
        const fileDiv = document.createElement('div');
        fileDiv.className = 'file-item';
        const isDisabled = file.deleted == 1;
        const statusClass = isDisabled ? 'file-disabled' : 'file-active';
        const statusText = isDisabled ? 'DISABLED' : 'ACTIVE';

        const actionButton = isDisabled ?
            `<button class="restore-btn" onclick="restoreFile(${file.id})">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.52185 7H7C7.55229 7 8 7.44772 8 8C8 8.55229 7.55228 9 7 9H3C1.89543 9 1 8.10457 1 7V3C1 2.44772 1.44772 2 2 2C2.55228 2 3 2.44772 3 3V5.6754C4.26953 3.8688 6.06062 2.47676 8.14852 1.69631C10.6633 0.756291 13.435 0.768419 15.9415 1.73041C18.448 2.69239 20.5161 4.53782 21.7562 6.91897C22.9963 9.30013 23.3228 12.0526 22.6741 14.6578C22.0254 17.263 20.4464 19.541 18.2345 21.0626C16.0226 22.5842 13.3306 23.2444 10.6657 22.9188C8.00083 22.5931 5.54702 21.3041 3.76664 19.2946C2.20818 17.5356 1.25993 15.3309 1.04625 13.0078C0.995657 12.4579 1.45216 12.0088 2.00445 12.0084C2.55673 12.0079 3.00351 12.4566 3.06526 13.0055C3.27138 14.8374 4.03712 16.5706 5.27027 17.9625C6.7255 19.605 8.73118 20.6586 10.9094 20.9247C13.0876 21.1909 15.288 20.6513 17.0959 19.4075C18.9039 18.1638 20.1945 16.3018 20.7247 14.1724C21.2549 12.043 20.9881 9.79319 19.9745 7.8469C18.9608 5.90061 17.2704 4.3922 15.2217 3.6059C13.173 2.8196 10.9074 2.80968 8.8519 3.57803C7.11008 4.22911 5.62099 5.40094 4.57993 6.92229C4.56156 6.94914 4.54217 6.97505 4.52185 7Z" fill="currentColor"/>
                </svg>
                Restore
            </button>` :
            `<button class="disable-btn" onclick="disableFile(${file.id})">
                <svg fill="currentColor" height="200px" width="200px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M366.473,172.549c-8.552-9.598-23.262-10.44-32.858-1.888c-9.595,8.552-10.44,23.263-1.887,32.858 c16.556,18.576,25.676,42.527,25.676,67.443c-0.002,55.913-45.49,101.402-101.404,101.402s-101.402-45.489-101.402-101.402 c0-24.913,9.118-48.863,25.678-67.443c8.552-9.595,7.705-24.308-1.89-32.86c-9.596-8.552-24.306-7.705-32.858,1.89 c-24.166,27.116-37.474,62.065-37.474,98.413C108.052,352.54,174.421,418.909,256,418.909s147.948-66.369,147.948-147.948 C403.948,234.611,390.639,199.661,366.473,172.549z"></path> </g> </g> <g> <g> <path d="M256,93.091c-12.853,0-23.273,10.42-23.273,23.273v99.739c0,12.853,10.42,23.273,23.273,23.273 c12.853,0,23.273-10.42,23.273-23.273v-99.739C279.273,103.511,268.853,93.091,256,93.091z"></path> </g> </g> <g> <g> <path d="M256,0C114.842,0,0,114.842,0,256s114.842,256,256,256c141.16,0,256-114.842,256-256S397.16,0,256,0z M256,465.455 c-115.493,0-209.455-93.961-209.455-209.455S140.507,46.545,256,46.545S465.455,140.507,465.455,256S371.493,465.455,256,465.455z "></path> </g> </g> </g></svg>
                Disable
            </button>`;

        const renewButton = !isDisabled ?
            `<button class="renew-btn" onclick="renewFileDownloads(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
               <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6C8.69 6 6 8.69 6 12H9L5 16L1 12H4C4 7.58 7.58 4 12 4C13.57 4 15.03 4.46 16.26 5.24L14.8 6.7C13.97 6.25 13.01 6 12 6ZM15 12L19 8L23 12H20C20 16.42 16.42 20 12 20C10.43 20 8.97 19.54 7.74 18.76L9.2 17.3C10.03 17.75 10.99 18 12 18C15.31 18 18 15.31 18 12H15Z" fill="currentColor"></path> </g></svg>
                Renew
            </button>` : '';

        fileDiv.innerHTML = `
            <div class="file-header">
                <h3>${getFileIcon(file.mime_type)} ${file.original_filename}</h3>
                <span class="file-status ${statusClass}">${statusText}</span>
            </div>
            <div class="file-info">
                <div><strong>IMEI:</strong> ${file.imei}</div>
                <div><strong>Size:</strong> ${formatFileSize(file.file_size)}</div>
                <div><strong>Type:</strong> ${file.mime_type}</div>
                <div><strong>Downloads:</strong> ${file.download_count}/${file.max_downloads}</div>
                <div><strong>Uploaded:</strong> ${new Date(file.created_at).toLocaleString()}</div>
                <div><strong>Last Modified:</strong> ${new Date(file.updated_at).toLocaleString()}</div>
            </div>
            <div class="file-actions">
                <button class="download-btn" onclick="downloadFile('${file.filename}')">
                   <svg viewBox="0 0 24.00 24.00" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M3 15C3 17.8284 3 19.2426 3.87868 20.1213C4.75736 21 6.17157 21 9 21H15C17.8284 21 19.2426 21 20.1213 20.1213C21 19.2426 21 17.8284 21 15" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 3V16M12 16L16 11.625M12 16L8 11.625" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                    Download
                </button>
                <button class="view-files-btn" onclick="viewFile('${file.filename}', '${file.mime_type}', '${file.original_filename.replace(/'/g, "\\'")}')">
                   <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9ZM11 12C11 11.4477 11.4477 11 12 11C12.5523 11 13 11.4477 13 12C13 12.5523 12.5523 13 12 13C11.4477 13 11 12.5523 11 12Z" fill="currentColor"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M21.83 11.2807C19.542 7.15186 15.8122 5 12 5C8.18777 5 4.45796 7.15186 2.17003 11.2807C1.94637 11.6844 1.94361 12.1821 2.16029 12.5876C4.41183 16.8013 8.1628 19 12 19C15.8372 19 19.5882 16.8013 21.8397 12.5876C22.0564 12.1821 22.0536 11.6844 21.83 11.2807ZM12 17C9.06097 17 6.04052 15.3724 4.09173 11.9487C6.06862 8.59614 9.07319 7 12 7C14.9268 7 17.9314 8.59614 19.9083 11.9487C17.9595 15.3724 14.939 17 12 17Z" fill="currentColor"></path> </g></svg>
                    Preview
                </button>
                ${renewButton}
                ${actionButton}
                <button class="delete-btn" onclick="permanentDeleteFile(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M10 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M6 10V18C6 19.6569 7.34315 21 9 21H15C16.6569 21 18 19.6569 18 18V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5V7H9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                    Delete
                </button>
            </div>
        `;
        filesContainer.appendChild(fileDiv);
    });
}
        function disableFile(fileId) {
            if (confirm('Are you sure you want to disable this file? It will be marked as deleted but not permanently removed.')) {
                fetch('admin_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=disable_file&file_id=${fileId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('File disabled successfully');
                            refreshCurrentModal();
                        } else {
                            alert('Error disabling file: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error disabling file:', error);
                        alert('Error disabling file');
                    });
            }
        }

        function restoreFile(fileId) {
            if (confirm('Are you sure you want to restore this file? It will be marked as active again.')) {
                fetch('admin_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=restore_file&file_id=${fileId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('File restored successfully');
                            refreshCurrentModal();
                        } else {
                            alert('Error restoring file: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error restoring file:', error);
                        alert('Error restoring file');
                    });
            }
        }

        function refreshCurrentModal() {
            const modal = document.getElementById('filesModal');
            const modalTitle = document.getElementById('modalTitle');
            const titleText = modalTitle.textContent;
            const userName = titleText.replace('Files for ', '');
            const rows = document.querySelectorAll('#usersTableBody tr');
            let userId = null;
            rows.forEach(row => {
                const nameCell = row.cells[1].textContent;
                if (nameCell === userName) {
                    userId = row.cells[0].textContent;
                }
            });
            if (userId) {
                viewUserFiles(userId, userName);
            }
            loadStats();
            loadUsers(currentPage);
        }

        function setupModal() {
            const modal = document.getElementById('filesModal');
            const span = document.getElementsByClassName('close')[0];
            span.onclick = function() {
                modal.style.display = 'none';
            }
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }

        function permanentDeleteFile(fileId, fileName) {
            if (confirm(`Are you sure you want to PERMANENTLY delete "${fileName}"?\n\nThis action cannot be undone. The file will be completely removed from the database and storage.`)) {
                fetch('admin_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=permanent_delete_file&file_id=${fileId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('File permanently deleted successfully');
                            refreshCurrentModal();
                        } else {
                            alert('Error permanently deleting file: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error permanently deleting file:', error);
                        alert('Error permanently deleting file');
                    });
            }
        }
    </script>
</body>

</html>