<?php
require_once 'admin_auth.php';
require_once 'admin_functions.php';
if (!isAdminAuthenticated()) {
    header("Location: admin_login");
    exit();
}
if (!checkAdminAuthTimeout()) {
    header("Location: admin_login");
    exit();
}
protectAdminPage('admin_login');
logAdminActivity('Accessed Admin Dashboard');
$currentAdmin = getCurrentAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="./../css/admin.css">
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
                    <span>Welcome, <?php echo htmlspecialchars(getAdminDisplayName()); ?></span>
                    <a href="admin_logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
        <div class="stats-grid" id="stats-grid">
        </div>
       <div class="search-bar-wrapper"> <div class="search-bar" id="searchBar"> <div class="search-container"> <input type="text" id="searchInput" placeholder="Search users by name, email, or mobile..." onkeyup="searchUsers()"> </div> </div> <div class="search-bar-placeholder" id="searchBarPlaceholder"></div> </div>
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
function formatNairaCurrency(amount) {
    const num = parseFloat(amount) || 0;
    return '₦' + num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
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
                <span class="file-count-badge">${user.file_count}</span>
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
        fileDiv.innerHTML = `
            <div class="file-header">
                <h3>${getFileIcon(file.mime_type)} ${file.original_filename}</h3>
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
                <button class="download-btn" onclick="downloadFile('${file.filename}')">Download</button>
                ${file.mime_type.startsWith('image/') ? `<button class="view-files-btn" onclick="viewFile('${file.filename}', '${file.mime_type}')">View</button>` : ''}
                <button class="delete-btn" onclick="deleteFile(${file.id})">Delete</button>
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
function deleteFile(fileId) {
    if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
        fetch('admin_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_file&file_id=${fileId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File deleted successfully');
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
                } else {
                    modal.style.display = 'none';
                }
                loadStats();
                loadUsers(currentPage); 
            } else {
                alert('Error deleting file: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting file:', error);
            alert('Error deleting file');
        });
    }
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
    </script>
</body>
</html>