

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
                    <div class="stat-card">
                        <h3>Recent Registrations</h3>
                        <div class="number">${data.stats.recent_registrations}</div>
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
                <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                    <g fill="none" fill-rule="evenodd">
                        <path d="m0 0h32v32h-32z"></path>
                        <path d="m16 0c8.836556 0 16 7.163444 16 16s-7.163444 16-16 16-16-7.163444-16-16 7.163444-16 16-16zm0 2c-7.7319865 0-14 6.2680135-14 14s6.2680135 14 14 14 14-6.2680135 14-14-6.2680135-14-14-14zm2.8284271 9.7573593c.3905243-.3905243 1.0236893-.3905243 1.4142136 0s.3905243 1.0236893 0 1.4142136l-7.0710678 7.0710678c-.3905243.3905243-1.0236893.3905243-1.4142136 0s-.3905243-1.0236893 0-1.4142136z" fill="currentColor" fill-rule="nonzero"/>
                    </g>
                </svg>
                Disable
            </button>`;
            
        const renewButton = !isDisabled ? 
            `<button class="renew-btn" onclick="renewFileDownloads(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.06189 13C4.21104 14.4876 4.87789 15.8723 5.93931 16.9393C7.00072 18.0007 8.38426 18.6686 9.87081 18.8188C11.3574 18.9689 12.8668 18.5929 14.0988 17.7442C15.3308 16.8954 16.2146 15.6287 16.5959 14.1823C16.9771 12.7358 16.8295 11.2016 16.1823 9.84674C15.535 8.49185 14.4291 7.40747 13.0799 6.75916C11.7308 6.11084 10.2022 5.94735 8.74781 6.29781C7.29344 6.64826 6.01347 7.49283 5.12132 8.70652" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M2 12L5.12132 8.70652L8.5 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
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
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.5535 16.5061C12.4114 16.6615 12.2106 16.75 12 16.75C11.7894 16.75 11.5886 16.6615 11.4465 16.5061L7.44648 12.1311C7.16698 11.8254 7.18822 11.351 7.49392 11.0715C7.79963 10.792 8.27402 10.8132 8.55352 11.1189L11.25 14.0682V3C11.25 2.58579 11.5858 2.25 12 2.25C12.4142 2.25 12.75 2.58579 12.75 3V14.0682L15.4465 11.1189C15.726 10.8132 16.2004 10.792 16.5061 11.0715C16.8118 11.351 16.833 11.8254 16.5535 12.1311L12.5535 16.5061Z" fill="currentColor"/>
                        <path d="M3.75 15C3.75 14.5858 3.41422 14.25 3 14.25C2.58579 14.25 2.25 14.5858 2.25 15V15.0549C2.24998 16.4225 2.24996 17.5248 2.36652 18.3918C2.48754 19.2919 2.74643 20.0497 3.34835 20.6516C3.95027 21.2536 4.70814 21.5125 5.60825 21.6335C6.47522 21.75 7.57754 21.75 8.94513 21.75H15.0549C16.4225 21.75 17.5248 21.75 18.3918 21.6335C19.2919 21.5125 20.0497 21.2536 20.6517 20.6516C21.2536 20.0497 21.5125 19.2919 21.6335 18.3918C21.75 17.5248 21.75 16.4225 21.75 15.0549V15C21.75 14.5858 21.4142 14.25 21 14.25C20.5858 14.25 20.25 14.5858 20.25 15C20.25 16.4354 20.2484 17.4365 20.1469 18.1919C20.0482 18.9257 19.8678 19.3142 19.591 19.591C19.3142 19.8678 18.9257 20.0482 18.1919 20.1469C17.4365 20.2484 16.4354 20.25 15 20.25H9C7.56459 20.25 6.56347 20.2484 5.80812 20.1469C5.07435 20.0482 4.68577 19.8678 4.40901 19.591C4.13225 19.3142 3.9518 18.9257 3.85315 18.1919C3.75159 17.4365 3.75 16.4354 3.75 15Z" fill="currentColor"/>
                    </svg>
                    Download
                </button>
                ${file.mime_type.startsWith('image/') ? `<button class="view-files-btn" onclick="viewFile('${file.filename}', '${file.mime_type}')">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9ZM11 12C11 11.4477 11.4477 11 12 11C12.5523 11 13 11.4477 13 12C13 12.5523 12.5523 13 12 13C11.4477 13 11 12.5523 11 12Z" fill="currentColor"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M21.83 11.2807C19.542 7.15186 15.8122 5 12 5C8.18777 5 4.45796 7.15186 2.17003 11.2807C1.94637 11.6844 1.94361 12.1821 2.16029 12.5876C4.41183 16.8013 8.1628 19 12 19C15.8372 19 19.5882 16.8013 21.8397 12.5876C22.0564 12.1821 22.0536 11.6844 21.83 11.2807ZM12 17C9.06097 17 6.04052 15.3724 4.09173 11.9487C6.06862 8.59614 9.07319 7 12 7C14.9268 7 17.9314 8.59614 19.9083 11.9487C17.9595 15.3724 14.939 17 12 17Z" fill="currentColor"/>
                    </svg>
                    View
                </button>` : ''}
                ${renewButton}
                ${actionButton}
                <button class="delete-btn" onclick="permanentDeleteFile(${file.id}, '${file.original_filename.replace(/'/g, "\\'")}')">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M6 10V18C6 19.6569 7.34315 21 9 21H15C16.6569 21 18 19.6569 18 18V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5V7H9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
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
function downloadFile(filename) {
    window.open(`admin_api.php?action=download_file&filename=${filename}`, '_blank');
}
function viewFile(filename, mimeType) {
    if (mimeType.startsWith('image/')) {
        showImageModal(filename);
    } else {
        downloadFile(filename);
    }
}
function showImageModal(filename) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="text-align: center;">
            <span class="close" onclick="this.parentElement.parentElement.remove()">&times;</span>
            <img src="certificates/${filename}" style="max-width: 100%; max-height: 70vh; margin: 20px 0;">
            <div>
                <button class="download-btn" onclick="downloadFile('${filename}')">Download Image</button>
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