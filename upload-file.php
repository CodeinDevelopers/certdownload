<?php
require_once 'auth.php';
protectPage('login.php');
if (!checkAuthTimeout(30)) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Upload Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
     .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #666666;
        }

     .powered-text {
            font-family: Arial, sans-serif;
            font-size: 0.75rem;
            color: #fff;
            /* adjust to match your section's text color */
        }

        .gold-text {
            color: gold;
            font-weight: bold;
            text-shadow: 0 0 5px #ffd700, 0 0 10px #ffcc00, 0 0 20px #ff9900;
            animation: glow 2s ease-in-out infinite;
        }

        @keyframes glow {

            0%,
            100% {
                text-shadow: 0 0 5px #ffd700, 0 0 10px #ffcc00, 0 0 20px #ff9900;
            }

            50% {
                text-shadow: 0 0 20px #ffd700, 0 0 30px #ffcc00, 0 0 40px #ff9900;
            }
        }</style>
</head>
<body>
     <a href="index.html" class="nav-link">← Download Portal</a>
    <a href="logout.php" class="logout-btn">Logout</a>
    
    <div class="main-container">
        <div class="header">
            <h1>Certificate Upload Portal</h1>
            <p class="subtitle">Upload new certificates and manage existing ones</p>
        </div>

        <div class="upload-section">
            <form id="uploadForm" class="upload-form">
                <div class="form-group">
                <label for="phoneNumber">Phone Number</label>
                <input 
                    type="tel" 
                    id="phoneNumber" 
                    name="phoneNumber" 
                    placeholder="+234 (0) 7053 959 6589"
                    required
                />
            </div>
                <div class="form-group">
                    <label for="displayName">Certificate Name</label>
                    <input 
                        type="text" 
                        id="displayName" 
                        name="displayName" 
                        placeholder="e.g., Professional Certificate"
                        required
                    />
                </div>
                <div class="form-group full-width">
                    <label for="certificateFile">Certificate File (PDF)</label>
                    <input 
                        type="file" 
                        id="certificateFile" 
                        name="certificateFile" 
                        accept=".pdf"
                        required
                    />
                    <div class="file-info">Only PDF files are accepted</div>
                </div>
                <div class="form-group full-width">
                    <button type="submit" class="upload-btn" id="uploadBtn">
                        <div class="loading" id="uploadLoading"></div>
                        <span id="uploadBtnText">Upload Certificate</span>
                    </button>
                </div>
            </form>
            <div class="message" id="uploadMessage"></div>
        </div>
<div class="certificates-list">
    <div class="list-header">Existing Certificates</div>
    <div id="certificatesList">
        <div class="empty-state">Loading certificates...</div>
    </div>
</div>
<div class="certificates-list past-certificates-section">
    <div class="list-header">Past Certificates</div>
    <div id="pastCertificatesList">
        <div class="empty-state">Loading past certificates...</div>
    </div>
</div>
    </div>

     <div class="footer powered-text">
            Powered by <span class="gold-text">BABOSAMMO ng</span>
        </div>
<script src="certificates.js"></script>
<script>
const uploadForm = document.getElementById('uploadForm');
const phoneInput = document.getElementById('phoneNumber');
const displayNameInput = document.getElementById('displayName');
const fileInput = document.getElementById('certificateFile');
const uploadBtn = document.getElementById('uploadBtn');
const uploadLoading = document.getElementById('uploadLoading');
const uploadBtnText = document.getElementById('uploadBtnText');
const uploadMessage = document.getElementById('uploadMessage');
const certificatesList = document.getElementById('certificatesList');

phoneInput.addEventListener('input', function(e) {
    e.target.value = certificateManager.formatPhoneNumber(e.target.value);
});

uploadForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    let phoneNumber = phoneInput.value.trim();
    const displayName = displayNameInput.value.trim();
    const file = fileInput.files[0];
    clearMessage(uploadMessage);

    if (!phoneNumber || !displayName || !file) {
        showMessage(uploadMessage, 'Please fill in all fields and select a file', 'error');
        return;
    }
    phoneNumber = normalizePhoneNumber(phoneNumber);

    if (file.type !== 'application/pdf') {
        showMessage(uploadMessage, 'Please select a PDF file', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showMessage(uploadMessage, 'File size exceeds 5MB limit', 'error');
        return;
    }
    setLoading(uploadLoading, uploadBtnText, uploadBtn, true, 'Uploading...', 'Upload Certificate');

    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('phoneNumber', phoneNumber);
        formData.append('displayName', displayName);

        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            showMessage(uploadMessage, `Certificate "${displayName}" uploaded successfully for ${phoneNumber}`, 'success');
            uploadForm.reset();
            setTimeout(() => {
                loadCertificates();
            }, 500);
        } else {
            showMessage(uploadMessage, result.message || 'Upload failed.', 'error');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showMessage(uploadMessage, 'Upload failed. Please check your connection and try again.', 'error');
    }
    setLoading(uploadLoading, uploadBtnText, uploadBtn, false, 'Uploading...', 'Upload Certificate');
});

function normalizePhoneNumber(phone) {
    let cleaned = phone.replace(/[^\d+]/g, '');
    if (cleaned.startsWith('0')) {
        cleaned = '+234' + cleaned.substring(1);
    } else if (cleaned.startsWith('234') && !cleaned.startsWith('+234')) {
        cleaned = '+' + cleaned;
    } else if (!cleaned.startsWith('+')) {
        cleaned = '+234' + cleaned;
    }
    return cleaned;
}

async function loadCertificates() {
    try {
        certificatesList.innerHTML = '<div class="empty-state">Loading certificates...</div>';
        
        // Also update past certificates section if it exists
        const pastCertificatesList = document.getElementById('pastCertificatesList');
        if (pastCertificatesList) {
            pastCertificatesList.innerHTML = '<div class="empty-state">Loading past certificates...</div>';
        }
        
        const res = await fetch('./data/certificates.json?' + Date.now());
        if (!res.ok) {
            if (res.status === 404) {
                certificatesList.innerHTML = '<div class="empty-state">No certificates uploaded yet</div>';
                if (pastCertificatesList) {
                    pastCertificatesList.innerHTML = '<div class="empty-state">No past certificates found</div>';
                }
                return;
            }
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        const data = await res.json();
        let certificates = [];
        
        if (Array.isArray(data)) {
            certificates = data.map(cert => ({
                phoneNumber: cert.phoneNumber,
                ...cert
            }));
        } else {
            // New format - convert object to array for easier processing
            certificates = Object.keys(data).map(phoneNumber => ({
                phoneNumber: phoneNumber,
                ...data[phoneNumber]
            }));
        }

        // Separate active and deleted certificates
        const activeCertificates = certificates.filter(cert => !cert.deleted);
        const deletedCertificates = certificates.filter(cert => cert.deleted);

        // Handle active certificates
        if (activeCertificates.length === 0) {
            certificatesList.innerHTML = '<div class="empty-state">No active certificates found</div>';
        } else {
            // Sort certificates by display name
            activeCertificates.sort((a, b) => a.displayName.localeCompare(b.displayName));
            certificatesList.innerHTML = generateCertificateHTML(activeCertificates, false);
        }

        // Handle past certificates
        if (pastCertificatesList) {
            if (deletedCertificates.length === 0) {
                pastCertificatesList.innerHTML = '<div class="empty-state">No past certificates found</div>';
            } else {
                // Sort past certificates by display name
                deletedCertificates.sort((a, b) => a.displayName.localeCompare(b.displayName));
                pastCertificatesList.innerHTML = generateCertificateHTML(deletedCertificates, true);
            }
        }
        
    } catch (error) {
        console.error('Error loading certificates:', error);
        certificatesList.innerHTML = '<div class="error">Failed to load certificates. Please refresh the page.</div>';
        
        const pastCertificatesList = document.getElementById('pastCertificatesList');
        if (pastCertificatesList) {
            pastCertificatesList.innerHTML = '<div class="error">Failed to load past certificates.</div>';
        }
    }
}

// Generate HTML for certificates (both active and past)
function generateCertificateHTML(certificates, isPastCertificate = false) {
    return certificates.map(cert => {
        const remainingDownloads = cert.maxDownloads - cert.downloadCount;
        const statusColor = isPastCertificate ? '#666' : (remainingDownloads > 0 ? '#34c759' : '#ff3b30');
        const statusText = isPastCertificate ? 'Deleted' : (remainingDownloads > 0 ? 'Active' : 'Expired');
        const progressPercentage = Math.round((cert.downloadCount / cert.maxDownloads) * 100);
        
        // Different styling and actions for past certificates
        const itemClass = isPastCertificate ? 'certificate-item past-certificate' : 'certificate-item';
        const actionButtons = isPastCertificate ? 
            `<button class="restore-btn" onclick="restoreCertificate('${cert.phoneNumber}', '${escapeHtml(cert.displayName)}')" title="Restore Certificate">
                <span class="restore-icon">↻</span>
                Restore
            </button>
            <button class="permanent-delete-btn" onclick="permanentDeleteCertificate('${cert.phoneNumber}', '${escapeHtml(cert.displayName)}')" title="Permanently Delete">
                <span class="delete-icon">🗑️</span>
                Delete Forever
            </button>` :
            `<button class="delete-btn" onclick="deleteCertificate('${cert.phoneNumber}', '${escapeHtml(cert.displayName)}')" title="Delete Certificate">
                <span class="delete-icon">🗑️</span>
                Delete
            </button>`;
        
        return `
            <div class="${itemClass}" data-phone="${cert.phoneNumber}">
                <div class="cert-header">
                    <div class="cert-info">
                        <div class="cert-name">${escapeHtml(cert.displayName)}</div>
                        <div class="cert-phone">${escapeHtml(cert.phoneNumber)}</div>
                    </div>
                    <div class="cert-actions">
                        ${actionButtons}
                    </div>
                </div>
                <div class="cert-stats">
                    <div class="stat-item">
                        <span class="stat-label">Downloads:</span>
                        <span class="stat-value"><strong>${cert.downloadCount}</strong> / ${cert.maxDownloads}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Remaining:</span>
                        <span class="stat-value"><strong>${remainingDownloads}</strong></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">File:</span>
                        <span class="stat-value"><strong>${escapeHtml(cert.filename)}</strong></span>
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
            </div>
        `;
    }).join('');
}

// Restore certificate function
async function restoreCertificate(phoneNumber, displayName) {
    if (!confirm(`Are you sure you want to restore the certificate for "${displayName}" (${phoneNumber})?`)) {
        return;
    }
    
    const certificateItem = document.querySelector(`[data-phone="${phoneNumber}"]`);
    const restoreBtn = certificateItem?.querySelector('.restore-btn');
    const originalText = restoreBtn?.innerHTML;
    
    if (restoreBtn) {
        restoreBtn.disabled = true;
        restoreBtn.innerHTML = '<span class="restore-icon">⏳</span> Restoring...';
        restoreBtn.style.opacity = '0.6';
    }
    
    try {
        const formData = new FormData();
        formData.append('phoneNumber', phoneNumber);
        formData.append('action', 'restore');
        
        const response = await fetch('./restore_certificate.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
            // Try to get error message from response
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // If JSON parsing fails, use default error message
                console.error('Failed to parse error response:', e);
            }
            throw new Error(errorMessage);
        }
        
        // Check if response is valid JSON
        let result;
        try {
            result = await response.json();
        } catch (e) {
            console.error('Invalid JSON response:', e);
            throw new Error('Invalid response format from server');
        }
        
        // Validate response structure
        if (typeof result !== 'object' || result === null) {
            throw new Error('Invalid response structure');
        }
        
        if (result.success === true) {
            showToast(`Certificate for "${displayName}" restored successfully`, 'success');
            
            // Remove from past certificates with animation
            if (certificateItem) {
                certificateItem.style.transition = 'all 0.3s ease-out';
                certificateItem.style.opacity = '0';
                certificateItem.style.transform = 'translateX(100%)';
                
                setTimeout(() => {
                    loadCertificates(); // Refresh both lists
                }, 300);
            }
            
        } else {
            // Handle server-side errors
            const errorMessage = result.message || 'Restore failed - unknown error';
            throw new Error(errorMessage);
        }
        
    } catch (error) {
        console.error('Restore error:', error);
        
        // Show appropriate error message
        let errorMsg = 'Failed to restore certificate';
        if (error.message) {
            errorMsg += `: ${error.message}`;
        }
        
        showToast(errorMsg, 'error');
        
        // Reset button state
        if (restoreBtn && originalText) {
            restoreBtn.disabled = false;
            restoreBtn.innerHTML = originalText;
            restoreBtn.style.opacity = '1';
        }
    }
}

// Helper function to check if showToast exists
function safeShowToast(message, type) {
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        // Fallback to alert if showToast doesn't exist
        alert(`${type.toUpperCase()}: ${message}`);
        console.log(`${type.toUpperCase()}: ${message}`);
    }
}

// Permanent delete certificate function
async function permanentDeleteCertificate(phoneNumber, displayName) {
    if (!confirm(`Are you sure you want to PERMANENTLY delete the certificate for "${displayName}" (${phoneNumber})?\n\nThis will completely remove the certificate and its file from the system. This action cannot be undone!`)) {
        return;
    }
    
    const certificateItem = document.querySelector(`[data-phone="${phoneNumber}"]`);
    const deleteBtn = certificateItem?.querySelector('.permanent-delete-btn');
    const originalText = deleteBtn?.innerHTML;
    
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="delete-icon">⏳</span> Deleting...';
        deleteBtn.style.opacity = '0.6';
    }
    
    try {
        const formData = new FormData();
        formData.append('phoneNumber', phoneNumber);
        formData.append('action', 'permanent');
        
        const response = await fetch('./delete_certificate.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Certificate for "${displayName}" permanently deleted`, 'success');
            
            if (certificateItem) {
                certificateItem.style.transition = 'all 0.3s ease-out';
                certificateItem.style.opacity = '0';
                certificateItem.style.transform = 'translateX(-100%)';
                
                setTimeout(() => {
                    loadCertificates(); // Refresh both lists
                }, 300);
            }
            
        } else {
            throw new Error(result.message || 'Permanent delete failed');
        }
        
    } catch (error) {
        console.error('Permanent delete error:', error);
        showToast(`Failed to permanently delete certificate: ${error.message}`, 'error');
        
        if (deleteBtn && originalText) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            deleteBtn.style.opacity = '1';
        }
    }
}

// Delete certificate function (modified to just mark as deleted)
async function deleteCertificate(phoneNumber, displayName) {
    if (!confirm(`Are you sure you want to delete the certificate for "${displayName}" (${phoneNumber})?\n\nThe certificate will be moved to past certificates and can be restored later.`)) {
        return;
    }
    
    const certificateItem = document.querySelector(`[data-phone="${phoneNumber}"]`);
    const deleteBtn = certificateItem?.querySelector('.delete-btn');
    const originalText = deleteBtn?.innerHTML;
    
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="delete-icon">⏳</span> Deleting...';
        deleteBtn.style.opacity = '0.6';
    }
    
    try {
        const formData = new FormData();
        formData.append('phoneNumber', phoneNumber);
        
        const response = await fetch('delete_certificate.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Certificate for "${displayName}" moved to past certificates`, 'success');
            
            if (certificateItem) {
                certificateItem.style.transition = 'all 0.3s ease-out';
                certificateItem.style.opacity = '0';
                certificateItem.style.transform = 'translateX(-100%)';
                
                setTimeout(() => {
                    loadCertificates(); // Refresh both lists
                }, 300);
            }
            
        } else {
            throw new Error(result.message || 'Delete failed');
        }
        
    } catch (error) {
        console.error('Delete error:', error);
        showToast(`Failed to delete certificate: ${error.message}`, 'error');
        
        if (deleteBtn && originalText) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            deleteBtn.style.opacity = '1';
        }
    }
}

// Search/filter certificates (updated to handle both sections)
function filterCertificates(searchTerm) {
    const activeItems = document.querySelectorAll('#certificatesList .certificate-item');
    const pastItems = document.querySelectorAll('#pastCertificatesList .certificate-item');
    const term = searchTerm.toLowerCase();
    
    // Filter active certificates
    activeItems.forEach(item => {
        const name = item.querySelector('.cert-name').textContent.toLowerCase();
        const phone = item.querySelector('.cert-phone').textContent.toLowerCase();
        
        if (name.includes(term) || phone.includes(term)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
    
    // Filter past certificates
    pastItems.forEach(item => {
        const name = item.querySelector('.cert-name').textContent.toLowerCase();
        const phone = item.querySelector('.cert-phone').textContent.toLowerCase();
        
        if (name.includes(term) || phone.includes(term)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 3000);
}

// Simple message display
function showMessage(target, text, type) {
    target.className = `message ${type}`;
    target.innerHTML = text;
    target.classList.add('show');
    
    if (type === 'success') {
        setTimeout(() => {
            clearMessage(target);
        }, 5000);
    }
}

// Clear message
function clearMessage(target) {
    target.className = 'message';
    target.innerHTML = '';
    target.classList.remove('show');
}

// Simple loading toggle
function setLoading(loadingEl, textEl, btnEl, isLoading, loadingText, defaultText) {
    if (loadingEl) loadingEl.style.display = isLoading ? 'inline-block' : 'none';
    if (btnEl) btnEl.disabled = isLoading;
    if (textEl) textEl.textContent = isLoading ? loadingText : defaultText;
}

// HTML escape function to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Refresh certificates list
function refreshCertificates() {
    showToast('Refreshing certificates...', 'info');
    loadCertificates();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadCertificates();
    
    const searchInput = document.getElementById('certificateSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            filterCertificates(e.target.value);
        });
    }
    
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshCertificates);
    }
    
    // Auto-refresh every 30 seconds
    setInterval(loadCertificates, 30000);
});

// Handle page visibility change to refresh when page becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        loadCertificates();
    }
});
</script>
</body>
</html>