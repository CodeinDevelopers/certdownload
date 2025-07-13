<?php
require_once 'auth.php';
protectPage('login.php');
if (!checkAuthTimeout(30)) {
    header('Location: login.php');
    exit();
}
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safer Naija Upload Portal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="logo-container">
            <img src="images\download.png" height="50px">
            <a href="#" class="logo-text">Document Portal</a>
        </div>
        <a href="logout.php" class="logout-btn">
            Logout 
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" style="display: inline-block; vertical-align: middle;" aria-hidden="true">
                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                <g id="SVGRepo_iconCarrier"> 
                    <path d="M17.4399 14.62L19.9999 12.06L17.4399 9.5" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path> 
                    <path d="M9.76001 12.0601H19.93" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path> 
                    <path d="M11.76 20C7.34001 20 3.76001 17 3.76001 12C3.76001 7 7.34001 4 11.76 4" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path> 
                </g>
            </svg>
        </a>
    </header>
    
    <div class="main-container">
        <div class="header" style="margin-top: 45px;">
        </div>
        <div class="user-info">
            <h2>Welcome, <?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?>!</h2>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($currentUser['mobile']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email']); ?></p>
        </div>
        
        <div class="certificates-list">
            <div class="list-header">My Files</div>
            <div class="search-section">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search by IMEI or filename..."
                    class="search-input"
                />
                <button type="button" id="clearSearch" class="clear-search-btn" style="display: none;">
                    ✕
                </button>
            </div>
            <div id="certificatesList">
                <div class="empty-state">Loading files...</div>
            </div>
        </div> 
        
        <div class="upload-section">
            <div class="info-note">
                <strong>Note:</strong> Accepted file formats: PDF, JPG, JPEG, PNG (up to 5MB).
            </div>
            <form id="uploadForm" class="upload-form">  
                <div class="form-group full-width">
                    <label for="imeiInput">IMEI Number</label>
                    <input 
                        type="text" 
                        id="imeiInput" 
                        name="imei"
                        pattern="[0-9]{15}"
                        maxlength="15"
                        placeholder="Enter 15-digit IMEI number"
                        required
                    />
                    <div class="file-info">Enter the 15-digit IMEI number of your device</div>
                </div>
                <div class="form-group full-width">
                    <label for="certificateFile">Purchase Receipt File</label>
                    <input 
                        type="file" 
                        id="certificateFile" 
                        name="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    />
                    <div class="file-info">Accepted formats: PDF, JPG, JPEG, PNG (Max: 5MB)</div>
                </div>
                <div class="form-group full-width">
                    <button type="submit" class="upload-btn" id="uploadBtn">
                        <div class="loading" id="uploadLoading"></div>
                        <span id="uploadBtnText">Upload Purchase Receipt</span>
                    </button>
                </div>
            </form>
            <div class="message" id="uploadMessage"></div>
        </div>
    </div>
    
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-title">Confirm Deletion</div>
            <div class="modal-message">
                Are you sure you want to delete this File?
                <div class="modal-filename" id="modalFilename"></div><br>
                This action cannot be undone.
            </div>
            <div class="modal-buttons">
                <button class="modal-btn cancel" id="cancelBtn">Cancel</button>
                <button class="modal-btn confirm" id="confirmBtn">
                    <span id="confirmBtnText">Delete</span>
                </button>
            </div>
        </div>
    </div>

    <script src="js/certificates.js"></script>
    <script>
        const uploadForm = document.getElementById('uploadForm');
        const fileInput = document.getElementById('certificateFile');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadLoading = document.getElementById('uploadLoading');
        const uploadBtnText = document.getElementById('uploadBtnText');
        const uploadMessage = document.getElementById('uploadMessage');
        const certificatesList = document.getElementById('certificatesList');
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        const confirmModal = document.getElementById('confirmModal');
        const modalFilename = document.getElementById('modalFilename');
        const cancelBtn = document.getElementById('cancelBtn');
        const confirmBtn = document.getElementById('confirmBtn');
        const confirmBtnText = document.getElementById('confirmBtnText');
        let allCertificates = [];
        let certificateToDelete = null;
        function showToast(message, type = 'info', duration = 4000) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            const existingToasts = document.querySelectorAll('.toast');
            let topOffset = 20;
            existingToasts.forEach(existingToast => {
                topOffset += existingToast.offsetHeight + 10;
            });
            toast.style.top = `${topOffset}px`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, duration);
        }
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const file = fileInput.files[0];
            const imeiInput = document.getElementById('imeiInput');
            const imei = imeiInput.value.trim();
            
            if (!file) {
                showToast('Please select a file to upload', 'error');
                return;
            }
            if (!imei) {
                showToast('Please enter the IMEI number', 'error');
                imeiInput.focus();
                return;
            }
            if (!/^\d{15}$/.test(imei)) {
                showToast('IMEI must be exactly 15 digits', 'error');
                imeiInput.focus();
                return;
            }
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Please select a PDF, JPG, JPEG, or PNG file', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showToast('File size exceeds 5MB limit', 'error');
                return;
            }
            setLoading(uploadLoading, uploadBtnText, uploadBtn, true, 'Uploading...', 'Upload Purchase Receipt');
            showToast('Starting upload...', 'info');
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('imei', imei);

                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (result.success) {
                    let successMessage = `File uploaded successfully! File: ${result.original_filename} (IMEI: ${result.imei})`;
                    if (result.certificates_remaining !== undefined) {
                        if (result.certificates_remaining > 0) {
                            successMessage += ` - You can upload ${result.certificates_remaining} more Purchase Receipt${result.certificates_remaining === 1 ? '' : 's'}.`;
                        } else {
                            successMessage += ` - You have reached the maximum limit of 3 Purchase Receipts.`;
                        }
                    }
                    showToast(successMessage, 'success', 6000);
                    uploadForm.reset();
                    setTimeout(() => {
                        loadCertificates();
                    }, 1000);
                } else {
                    showToast(result.message || 'Upload failed.', 'error');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showToast('Upload failed. Please check your connection and try again.', 'error');
            }
            
            setLoading(uploadLoading, uploadBtnText, uploadBtn, false, 'Uploading...', 'Upload Purchase Receipt');
        });
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query) {
                clearSearchBtn.style.display = 'block';
                filterCertificates(query);
            } else {
                clearSearchBtn.style.display = 'none';
                displayCertificates(allCertificates);
            }
        });
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';
            displayCertificates(allCertificates);
            searchInput.focus();
        })
        function showDeleteConfirmation(certificate) {
            certificateToDelete = certificate;
            modalFilename.textContent = certificate.original_filename || certificate.filename;
            confirmModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function hideDeleteConfirmation() {
            confirmModal.classList.remove('show');
            document.body.style.overflow = 'auto';
            certificateToDelete = null;
        }
        async function downloadCertificate(certificate) {
            try {
                const remainingDownloads = certificate.max_downloads - certificate.download_count;
                if (remainingDownloads <= 0) {
                    showToast('Purchase Receipt download limit exceeded!', 'error');
                    return;
                }
                if (remainingDownloads === 1) {
                    const confirmDownload = confirm(
                        `This is your last download for this Purchase Receipt. After downloading, you won't be able to download it again. Continue?`
                    );
                    if (!confirmDownload) {
                        return;
                    }
                }
                showToast('Starting download...', 'info');
                const response = await fetch('download_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        certificate_id: certificate.id
                    })
                });
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Download failed');
                }
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = certificate.original_filename || certificate.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(downloadUrl);
                
                showToast('Download completed successfully!', 'success');
                setTimeout(() => {
                    loadCertificates();
                }, 1000);
                
            } catch (error) {
                console.error('Download error:', error);
                showToast('Download failed: ' + error.message, 'error');
            }
        }
        cancelBtn.addEventListener('click', hideDeleteConfirmation);
        confirmModal.addEventListener('click', function(e) {
            if (e.target === confirmModal) {
                hideDeleteConfirmation();
            }
        });
        confirmBtn.addEventListener('click', async function() {
            if (!certificateToDelete) return;
            confirmBtn.disabled = true;
            confirmBtnText.textContent = 'Deleting...';
            try {
                const response = await fetch('delete_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        certificate_id: certificateToDelete.id
                    })
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (result.success) {
                    showToast('Purchase Receipt deleted successfully', 'success');
                    hideDeleteConfirmation();
                    loadCertificates();
                } else {
                    throw new Error(result.message || 'Delete failed');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showToast('Failed to delete Purchase Receipt. Please try again.', 'error');
            }
            confirmBtn.disabled = false;
            confirmBtnText.textContent = 'Delete';
        });
        function filterCertificates(query) {
            const filteredCertificates = allCertificates.filter(cert => {
                const filename = (cert.original_filename || cert.filename || '').toLowerCase();
                const imei = (cert.imei || '').toLowerCase();
                const searchQuery = query.toLowerCase();
                
                return filename.includes(searchQuery) || imei.includes(searchQuery);
            });
            displayCertificates(filteredCertificates);
        }
        function displayCertificates(certificates) {
            if (certificates.length === 0) {
                const query = searchInput.value.trim();
                if (query) {
                    certificatesList.innerHTML = '<div class="empty-state">No files found matching your search</div>';
                } else {
                }
            } else {
                certificatesList.innerHTML = generateCertificateHTML(certificates);
                const certificateCount = certificates.length;
                const remaining = 3 - certificateCount;
                
                if (remaining > 0) {
                    const countInfo = document.createElement('div');
                    countInfo.className = 'certificate-count-info';
                    countInfo.innerHTML = `
                        <div style="background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 6px; padding: 12px; margin-bottom: 20px; color: #0c5460;">
                            <strong>Purchase Receipt Status:</strong> ${certificateCount} of 3 Purchase Receipts uploaded. 
                            You can upload ${remaining} more Purchase Receipt${remaining === 1 ? '' : 's'}.
                        </div>
                    `;
                    certificatesList.insertBefore(countInfo, certificatesList.firstChild);
                } else {
                    const countInfo = document.createElement('div');
                    countInfo.className = 'certificate-count-info';
                    countInfo.innerHTML = `
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 12px; margin-bottom: 20px; color: #856404;">
                            <strong>Purchase Receipt Limit Reached:</strong> You have uploaded the maximum of 3 Purchase Receipts. 
                            To upload a new Purchase Receipt, please delete an existing one first.
                        </div>
                    `;
                    certificatesList.insertBefore(countInfo, certificatesList.firstChild);
                }
            }
        }
        async function loadCertificates() {
            try {
                certificatesList.innerHTML = '<div class="empty-state">Loading files...</div>';
                const response = await fetch('get_certificates.php?' + Date.now());
                if (!response.ok) {
                    if (response.status === 404) {
                        allCertificates = [];
                        displayCertificates(allCertificates);
                        return;
                    }
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load certificates');
                }
                allCertificates = data.certificates || [];
                const query = searchInput.value.trim();
                if (query) {
                    filterCertificates(query);
                } else {
                    displayCertificates(allCertificates);
                }
            } catch (error) {
                console.error('Error loading files:', error);
                certificatesList.innerHTML = '<div class="empty-state">Unable to load files. Please refresh the page.</div>';
                showToast('Unable to load files. Please refresh the page.', 'error');
            }
        }
        function generateCertificateHTML(certificates) {
            return certificates.map(cert => {
                const remainingDownloads = cert.max_downloads - cert.download_count;
                const statusColor = remainingDownloads > 0 ? '#34c759' : '#ff3b30';
                const statusText = remainingDownloads > 0 ? 'Active' : 'Expired';
                const progressPercentage = Math.round((cert.download_count / cert.max_downloads) * 100);
                const canDownload = remainingDownloads > 0;
                return `
                    <div class="certificate-item">
                        <div class="cert-header">
                            <div class="cert-info">
                                <div class="cert-name">${escapeHtml(cert.original_filename || cert.filename)}</div>
                                <div class="cert-phone">Uploaded: ${new Date(cert.created_at).toLocaleDateString()}</div>
                                <div class="cert-imei">IMEI: ${escapeHtml(cert.imei)}</div>
                            </div>
                        </div>
                        <div class="cert-stats">
                            <div class="stat-item">
                                <span class="stat-label">Downloads:</span>
                                <span class="stat-value"><strong>${cert.download_count}</strong> / ${cert.max_downloads}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Remaining:</span>
                                <span class="stat-value"><strong>${remainingDownloads}</strong></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Status:</span>
                                <span class="stat-value"><strong style="color: ${statusColor}">${statusText}</strong></span>
                            </div>
                        </div>
                        <div class="cert-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${progressPercentage}%; background-color: ${statusColor}"></div>
                            </div>
                            <span class="progress-text">${progressPercentage}% used</span>
                        </div>
                        <div class="cert-actions">
                            <button class="download-btn" ${!canDownload ? 'disabled' : ''} onclick="downloadCertificate(${JSON.stringify(cert).replace(/"/g, '&quot;')})">
                                ${canDownload ? 'Download Purchase Receipt' : 'Download Limit Exceeded'}
                            </button>
                            <button class="delete-btn" onclick="showDeleteConfirmation(${JSON.stringify(cert).replace(/"/g, '&quot;')})">
                                Delete Purchase Receipt
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        function showMessage(target, text, type) {
            showToast(text, type);
        }
        function clearMessage(target) {
        }
        
        function setLoading(loadingEl, textEl, btnEl, isLoading, loadingText, defaultText) {
            if (loadingEl) loadingEl.style.display = isLoading ? 'inline-block' : 'none';
            if (btnEl) btnEl.disabled = isLoading;
            if (textEl) textEl.textContent = isLoading ? loadingText : defaultText;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && confirmModal.classList.contains('show')) {
                hideDeleteConfirmation();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            loadCertificates();
        });
        
        setInterval(loadCertificates, 30000);
        
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadCertificates();
            }
        });
    </script>
</body>
</html>