<?php
require_once 'auth.php';
require_once 'posts.php';
protectPage('login.php');
if (!checkAuthTimeout(30)) {
    header('Location: login.php');
    exit();
}
$currentUser = getCurrentUser();
try {
    $userPosts = getPostTitlesForDropdown();
} catch (Exception $e) {
    $userPosts = [];
    error_log("Error loading user posts: " . $e->getMessage());
}
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
        </div>
             <a href="https://safernaija.com.ng/" class="logout-btn">
             <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" style="display: inline-block; vertical-align: middle;" aria-hidden="true"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M6 12H18M6 12L11 7M6 12L11 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg> Portal
        </a>
        </div>
       
    </header>
    <div class="main-container">
        <div class="header" style="margin-top: 45px;">
        </div>
    <div class="user-info">
    <div class="user-details">
        <h2><?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?></h2>
        <div class="user-contact">
            <div class="contact-item">
                <span class="icon">📱</span>
                <span><?php echo htmlspecialchars($currentUser['mobile']); ?></span>
            </div>
            <div class="contact-item">
                <span class="icon">✉️</span>
                <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
            </div>
        </div>
    </div>
    <div class="user-actions">
        <div class="status-indicator">Activated</div>
        <a href="logout.php" class="pro-logout-btn">Logout</a>
    </div> </div>

        <div class="certificates-list">
            <div class="list-header">My Files</div>
            <div class="search-section">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search by Report title or filename..."
                    class="search-input" />
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
                <strong>Note:</strong> Accepted file formats: PDF, JPG, JPEG, PNG (up to 5MB).<br>
                <strong>Required:</strong> Select a Report to associate with this file.
            </div>
            <form id="uploadForm" class="upload-form">
                <div class="form-group full-width">
                    <label for="postSelect">Select Report *</label>
                    <select id="postSelect" name="post_id" required>
                        <option value="">-- Select a Report --</option>
                        <?php foreach ($userPosts as $post): ?>
                            <option value="<?php echo htmlspecialchars($post['value']); ?>">
                                <?php echo htmlspecialchars($post['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="file-info">
                        <?php if (empty($userPosts)): ?>
                            <span style="color: #ff3b30;">No Reports available. Please create a Report first.</span>
                        <?php else: ?>
                            Choose the Report you want to associate this file with
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="deviceIdentifier">Device Identifier</label>
                    <input
                        type="text"
                        id="deviceIdentifier"
                        name="device_identifier"
                        placeholder="Device identifier will be auto-filled from selected Report"
                        class="device-identifier-input" />
                    <div class="file-info">
                        <span id="identifierType">This field will be automatically filled when you select a Report</span>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="certificateFile">Purchase Receipt File</label>
                    <input
                        type="file"
                        id="certificateFile"
                        name="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required />
                    <div class="file-info">Accepted formats: PDF, JPG, JPEG, PNG (Max: 5MB)</div>
                </div>
                <div class="form-group full-width">
                    <button type="submit" class="upload-btn" id="uploadBtn" <?php echo empty($userPosts) ? 'disabled' : ''; ?>>
                        <div class="loading" id="uploadLoading"></div>
                        <span id="uploadBtnText">
                            <?php echo empty($userPosts) ? 'No Reports Available' : 'Upload Purchase Receipt'; ?>
                        </span>
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
    <script>
const uploadForm = document.getElementById('uploadForm');
const fileInput = document.getElementById('certificateFile');
const postSelect = document.getElementById('postSelect');
const deviceIdentifierInput = document.getElementById('deviceIdentifier');
const identifierTypeSpan = document.getElementById('identifierType');
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
const userPosts = <?php echo json_encode($userPosts); ?>;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatMessage(message) {
    let formatted = message.replace(/<br\s*\/?>/gi, '\n');
    formatted = formatted.replace(/<[^>]*>/g, '');
    
    const lines = formatted.split('\n');
    let html = '';
    
    lines.forEach((line, index) => {
        line = line.trim();
        if (line) {
            if (index === 0) {
                if (line.includes('successfully') || line.includes('failed') || line.includes('error')) {
                    html += `<div class="toast-header">${escapeHtml(line)}</div>`;
                } else {
                    html += `<div class="toast-body">${escapeHtml(line)}</div>`;
                }
            } else {
                html += `<div class="toast-detail">${escapeHtml(line)}</div>`;
            }
        }
    });
    
    return html;
}

function hideToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
        if (document.body.contains(toast)) {
            document.body.removeChild(toast);
        }
    }, 300);
}

function showToast(message, type = 'info', duration = 8000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    toast.innerHTML = formatMessage(message);
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '×';
    
    toast.appendChild(closeBtn);
    
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
    
    const autoHideTimeout = setTimeout(() => {
        hideToast(toast);
    }, duration);
    
    closeBtn.addEventListener('click', () => {
        clearTimeout(autoHideTimeout);
        hideToast(toast);
    });
}

function showUploadSuccessToast(reportTitle, deviceIdentifier, certificatesRemaining) {
    let message = `File uploaded successfully!\nAssociated with: ${reportTitle}`;
    
    if (deviceIdentifier) {
        message += `\nDevice Registration: ${deviceIdentifier}`;
    }
    
    if (certificatesRemaining !== undefined) {
        if (certificatesRemaining > 0) {
            message += `\nYou can upload ${certificatesRemaining} more Purchase Receipt${certificatesRemaining === 1 ? '' : 's'}.`;
        } else {
            message += `\nYou have reached the maximum limit of 3 Purchase Receipts.`;
        }
    }
    
    showToast(message, 'success', 10000);
}

function extractDeviceIdentifier(reportTitle) {
    const patterns = [
        /IMEI:\s*([A-Za-z0-9]+)/i,
        /VIN:\s*([A-Za-z0-9]+)/i,
        /serial:\s*([A-Za-z0-9]+)/i
    ];
    for (let pattern of patterns) {
        const match = reportTitle.match(pattern);
        if (match && match[1]) {
            return {
                identifier: match[1],
                type: pattern.source.split(':')[0].replace(/[^A-Za-z]/g, '').toUpperCase()
            };
        }
    }
    return null;
}

postSelect.addEventListener('change', function() {
    const selectedValue = this.value;
    const selectedPost = userPosts.find(post => post.value == selectedValue);
    if (selectedPost) {
        const reportTitle = selectedPost.text;
        const identifierData = extractDeviceIdentifier(reportTitle);
        if (identifierData) {
            deviceIdentifierInput.value = identifierData.identifier;
            identifierTypeSpan.textContent = `${identifierData.type} extracted from selected Report`;
            identifierTypeSpan.style.color = '#34c759';
        } else {
            deviceIdentifierInput.value = '';
            identifierTypeSpan.textContent = 'No device identifier found in selected Report';
            identifierTypeSpan.style.color = '#ff9500';
        }
    } else {
        deviceIdentifierInput.value = '';
        identifierTypeSpan.textContent = 'This field will be automatically filled when you select a Report';
        identifierTypeSpan.style.color = '#666';
    }
});

uploadForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    // console.log('=== Form Submission Started ===');
    const file = fileInput.files[0];
    const postId = postSelect.value;
    const deviceIdentifier = deviceIdentifierInput.value.trim();
    
    // console.log('Form submission values:', {
    //     postId,
    //     deviceIdentifier,
    //     file: file ? file.name : 'none'
    // });
    
    if (!file) {
        // console.error('No file selected');
        showToast('Please select a file to upload', 'error');
        return;
    }
    
    if (!postId) {
        // console.error('No post selected');
        showToast('Please select a Report to associate with this file', 'error');
        postSelect.focus();
        return;
    }
    
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        // console.error('Invalid file type:', file.type);
        showToast('Please select a PDF, JPG, JPEG, or PNG file', 'error');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        // console.error('File too large:', file.size);
        showToast('File size exceeds 5MB limit', 'error');
        return;
    }
    
    // console.log('All validations passed, starting upload...');
    setLoading(uploadLoading, uploadBtnText, uploadBtn, true, 'Uploading...', 'Upload Purchase Receipt');
    showToast('Starting upload...', 'info');
    
    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('post_id', postId);
        formData.append('device_identifier', deviceIdentifier);
        
        // console.log('=== FormData Contents ===');
        // for (let [key, value] of formData.entries()) {
        //     if (key === 'file') {
        //         console.log(`${key}: File object - ${value.name} (${value.size} bytes)`);
        //     } else {
        //         console.log(`${key}: "${value}"`);
        //     }
        // }
        
        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        // console.log('=== Server Response ===');
        // console.log('Status:', response.status);
        // console.log('Raw response:', responseText);
        
        if (!response.ok) {
            // console.error('HTTP error:', response.status);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        let result;
        try {
            result = JSON.parse(responseText);
            // console.log('Parsed response:', result);
        } catch (parseError) {
            // console.error('JSON parse error:', parseError);
            // console.error('Response text:', responseText);
            throw new Error('Invalid response format from server');
        }
        
        if (result.success) {
            // console.log('Upload successful!');
            const selectedPost = userPosts.find(post => post.value == postId);
            const reportTitle = selectedPost ? selectedPost.text : 'Unknown Report';
            showUploadSuccessToast(reportTitle, deviceIdentifier, result.certificates_remaining);
            
            uploadForm.reset();
            deviceIdentifierInput.value = '';
            identifierTypeSpan.textContent = 'This field will be automatically filled when you select a Report';
            identifierTypeSpan.style.color = '#666';
            
            setTimeout(() => {
                loadCertificates();
            }, 1000);
        } else {
            // console.error('Upload failed:', result);
            showToast(result.message || 'Upload failed.', 'error');
        }
    } catch (error) {
        // console.error('Upload error:', error);
        showToast('Upload failed. Please check your connection and try again.', 'error');
    }
    
    setLoading(uploadLoading, uploadBtnText, uploadBtn, false, 'Uploading...', 'Upload Purchase Receipt');
    // console.log('=== Form Submission Ended ===');
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
});

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
        // console.error('Download error:', error);
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
        // console.error('Delete error:', error);
        showToast('Failed to delete Purchase Receipt. Please try again.', 'error');
    }
    
    confirmBtn.disabled = false;
    confirmBtnText.textContent = 'Delete';
});

function filterCertificates(query) {
    const filteredCertificates = allCertificates.filter(cert => {
        const filename = (cert.original_filename || cert.filename || '').toLowerCase();
        const deviceIdentifier = (cert.device_identifier || '').toLowerCase();
        const searchQuery = query.toLowerCase();
        let reportTitle = '';
        
        if (cert.post_title && cert.post_title.trim()) {
            reportTitle = cert.post_title.toLowerCase();
        } else if (cert.post_id) {
            const matchedPost = userPosts.find(post => post.value == cert.post_id);
            if (matchedPost) {
                reportTitle = matchedPost.text.toLowerCase();
            } else {
                reportTitle = `Report id: ${cert.post_id}`.toLowerCase();
            }
        }
        
        return filename.includes(searchQuery) ||
            reportTitle.includes(searchQuery) ||
            deviceIdentifier.includes(searchQuery);
    });
    
    displayCertificates(filteredCertificates);
}

function displayCertificates(certificates) {
    if (certificates.length === 0) {
        const query = searchInput.value.trim();
        if (query) {
            certificatesList.innerHTML = '<div class="empty-state">No files found matching your search</div>';
        } else {
            certificatesList.innerHTML = '<div class="empty-state">No files uploaded yet</div>';
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
        // console.log('Certificates API response:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load certificates');
        }
        
        allCertificates = data.certificates || [];
        // console.log('Loaded certificates:', allCertificates);
        
        const query = searchInput.value.trim();
        if (query) {
            filterCertificates(query);
        } else {
            displayCertificates(allCertificates);
        }
    } catch (error) {
        // console.error('Error loading files:', error);
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
        
        // console.log('Certificate data:', {
        //     id: cert.id,
        //     post_id: cert.post_id,
        //     post_title: cert.post_title,
        //     filename: cert.original_filename || cert.filename
        // });
        
        let reportTitle = 'Unknown Report';
        if (cert.post_title && cert.post_title.trim()) {
            reportTitle = cert.post_title;
        } else if (cert.post_id) {
            const matchedPost = userPosts.find(post => post.value == cert.post_id);
            if (matchedPost) {
                reportTitle = matchedPost.text;
                // console.log(`Found post title from userPosts: ${reportTitle}`);
            } else {
                reportTitle = `Report ID: ${cert.post_id}`;
                // console.warn(`No post title found for post ID: ${cert.post_id}`);
            }
        }
        
        return `
            <div class="certificate-item">
                <div class="cert-header">
                    <div class="cert-info">
                        <div class="cert-name">${escapeHtml(cert.original_filename || cert.filename)}</div>
                        <div class="cert-phone">Uploaded: ${new Date(cert.created_at).toLocaleDateString()}</div>
                        <div class="cert-identifiers">Associated Report: ${escapeHtml(reportTitle)}</div>
                        ${cert.device_identifier ? `<div class="cert-identifiers">Device ID: ${escapeHtml(cert.device_identifier)}</div>` : ''}
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
                        ${canDownload ? 'Download File' : 'Download Limit Exceeded'}
                    </button>
                    <button class="delete-btn" onclick="showDeleteConfirmation(${JSON.stringify(cert).replace(/"/g, '&quot;')})">
                        Delete File
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function showMessage(target, text, type) {
    showToast(text, type);
}

function clearMessage(target) {}

function setLoading(loadingEl, textEl, btnEl, isLoading, loadingText, defaultText) {
    if (loadingEl) loadingEl.style.display = isLoading ? 'inline-block' : 'none';
    if (btnEl) btnEl.disabled = isLoading;
    if (textEl) textEl.textContent = isLoading ? loadingText : defaultText;
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