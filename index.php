<?php
require_once 'auth.php';
require_once 'posts.php';
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
                    <path d="M11.76 20C7.34001 20 3.76001 17 3.76001 4" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path>
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
                    placeholder="Search by IMEI, VIN, Serial Number or filename..."
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
                <strong>Required:</strong> Fill in at least one of the following fields: IMEI, VIN Number, or Serial Number.
            </div>
            <form id="uploadForm" class="upload-form">
                <div class="form-group full-width">
                    <label for="imeiInput">IMEI Number (Optional)</label>
                    <input
                        type="text"
                        id="imeiInput"
                        name="imei"
                        pattern="[0-9]{15}"
                        maxlength="15"
                        placeholder="Enter 15-digit IMEI number" />
                    <div class="file-info">Enter the 15-digit IMEI number of your device</div>
                </div>
                
                <div class="form-group full-width">
                    <label for="vinInput">VIN Number (Optional)</label>
                    <input
                        type="text"
                        id="vinInput"
                        name="vin_number"
                        pattern="[A-HJ-NPR-Z0-9]{17}"
                        maxlength="17"
                        placeholder="Enter 17-character VIN number"
                        style="text-transform: uppercase;" />
                    <div class="file-info">Enter the 17-character Vehicle Identification Number (excludes I, O, Q)</div>
                </div>
                
                <div class="form-group full-width">
                    <label for="serialInput">Serial Number (Optional)</label>
                    <input
                        type="text"
                        id="serialInput"
                        name="serial_number"
                        maxlength="50"
                        placeholder="Enter device serial number" />
                    <div class="file-info">Enter the device serial number (letters, numbers, and -, _, /, ., # allowed)</div>
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

// Auto-format VIN input to uppercase
document.getElementById('vinInput').addEventListener('input', function(e) {
    this.value = this.value.toUpperCase();
});

// Fixed validation functions with proper patterns and debugging
function validateIMEI(imei) {
    const pattern = /^\d{15}$/;
    return pattern.test(imei);
}

function validateVIN(vin) {
    // Ensure VIN is uppercase and exactly 17 characters
    // Exclude I, O, Q as per VIN standards
    const upperVin = vin.toUpperCase();
    const vinPattern = /^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]{17}$/;
    return vinPattern.test(upperVin);
}

function validateSerial(serial) {
    // Fixed regex - hyphen at the end to avoid escaping issues
    // Allows letters, numbers, spaces, and common special characters
    const serialPattern = /^[A-Za-z0-9_\/.#\s-]{1,50}$/;
    return serialPattern.test(serial);
}

// Enhanced debug function to check validation
function debugValidation(value, type) {
    console.log(`=== Validating ${type} ===`);
    console.log(`Original value: "${value}"`);
    console.log(`Length: ${value.length}`);
    console.log(`Characters: [${value.split('').join(', ')}]`);
    
    // Check for hidden characters
    const charCodes = value.split('').map(char => char.charCodeAt(0));
    console.log(`Character codes: [${charCodes.join(', ')}]`);
    
    // Check for non-printable characters
    const nonPrintable = value.split('').filter(char => {
        const code = char.charCodeAt(0);
        return code < 32 || code > 126;
    });
    if (nonPrintable.length > 0) {
        console.warn(`Non-printable characters found: [${nonPrintable.map(c => c.charCodeAt(0)).join(', ')}]`);
    }
    
    switch(type) {
        case 'VIN':
            const upperVin = value.toUpperCase();
            console.log(`Uppercase VIN: "${upperVin}"`);
            console.log(`VIN validation result: ${validateVIN(value)}`);
            
            // Check each character against VIN pattern
            const invalidVinChars = upperVin.split('').filter((char, index) => {
                const isValid = /^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]$/.test(char);
                if (!isValid) {
                    console.error(`Invalid VIN character at position ${index}: "${char}" (code: ${char.charCodeAt(0)})`);
                }
                return !isValid;
            });
            
            if (invalidVinChars.length > 0) {
                console.error(`Invalid VIN characters: [${invalidVinChars.join(', ')}]`);
            }
            break;
            
        case 'Serial':
            console.log(`Serial validation result: ${validateSerial(value)}`);
            
            // Check each character against serial pattern
            const invalidSerialChars = value.split('').filter((char, index) => {
                const isValid = /^[A-Za-z0-9_\/.#\s-]$/.test(char);
                if (!isValid) {
                    console.error(`Invalid serial character at position ${index}: "${char}" (code: ${char.charCodeAt(0)})`);
                }
                return !isValid;
            });
            
            if (invalidSerialChars.length > 0) {
                console.error(`Invalid serial characters: [${invalidSerialChars.join(', ')}]`);
            }
            break;
            
        case 'IMEI':
            console.log(`IMEI validation result: ${validateIMEI(value)}`);
            
            // Check each character against IMEI pattern
            const invalidImeiChars = value.split('').filter((char, index) => {
                const isValid = /^\d$/.test(char);
                if (!isValid) {
                    console.error(`Invalid IMEI character at position ${index}: "${char}" (code: ${char.charCodeAt(0)})`);
                }
                return !isValid;
            });
            
            if (invalidImeiChars.length > 0) {
                console.error(`Invalid IMEI characters: [${invalidImeiChars.join(', ')}]`);
            }
            break;
    }
    console.log(`=== End ${type} validation ===`);
}

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

// Enhanced form submission handler with comprehensive validation
uploadForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    console.log('=== Form Submission Started ===');
    
    const file = fileInput.files[0];
    const imeiInput = document.getElementById('imeiInput');
    const vinInput = document.getElementById('vinInput');
    const serialInput = document.getElementById('serialInput');
    
    const imei = imeiInput.value.trim();
    const vin = vinInput.value.trim().toUpperCase(); // Ensure uppercase
    const serial = serialInput.value.trim();

    console.log('Form submission values:', { imei, vin, serial });
    
    // Detailed validation logging
    console.log('=== Validation Checks ===');
    console.log('IMEI provided:', !!imei);
    console.log('VIN provided:', !!vin);
    console.log('Serial provided:', !!serial);
    
    if (imei) {
        console.log('IMEI valid:', validateIMEI(imei));
    }
    if (vin) {
        console.log('VIN valid:', validateVIN(vin));
    }
    if (serial) {
        console.log('Serial valid:', validateSerial(serial));
    }

    if (!file) {
        console.error('No file selected');
        showToast('Please select a file to upload', 'error');
        return;
    }

    // Check if at least one field is filled
    if (!imei && !vin && !serial) {
        console.error('No identifier fields filled');
        showToast('Please fill in at least one field: IMEI, VIN Number, or Serial Number', 'error');
        return;
    }

    // Validate filled fields with detailed debugging
    if (imei && !validateIMEI(imei)) {
        console.error('IMEI validation failed');
        debugValidation(imei, 'IMEI');
        showToast('IMEI must be exactly 15 digits', 'error');
        imeiInput.focus();
        return;
    }

    if (vin && !validateVIN(vin)) {
        console.error('VIN validation failed');
        debugValidation(vin, 'VIN');
        showToast('VIN must be exactly 17 characters (letters and numbers, excluding I, O, Q)', 'error');
        vinInput.focus();
        return;
    }

    if (serial && !validateSerial(serial)) {
        console.error('Serial validation failed');
        debugValidation(serial, 'Serial');
        showToast('Serial number contains invalid characters. Only letters, numbers, spaces, and -, _, /, ., # are allowed', 'error');
        serialInput.focus();
        return;
    }

    // File validation
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        console.error('Invalid file type:', file.type);
        showToast('Please select a PDF, JPG, JPEG, or PNG file', 'error');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        console.error('File too large:', file.size);
        showToast('File size exceeds 5MB limit', 'error');
        return;
    }

    console.log('All validations passed, starting upload...');
    setLoading(uploadLoading, uploadBtnText, uploadBtn, true, 'Uploading...', 'Upload Purchase Receipt');
    showToast('Starting upload...', 'info');

    try {
        const formData = new FormData();
        formData.append('file', file);
        
        // Only append fields that have values
        if (imei) {
            formData.append('imei', imei);
            console.log('Added IMEI to FormData:', imei);
        }
        if (vin) {
            formData.append('vin_number', vin);
            console.log('Added VIN to FormData:', vin);
        }
        if (serial) {
            formData.append('serial_number', serial);
            console.log('Added Serial to FormData:', serial);
        }

        // Debug FormData contents
        console.log('=== FormData Contents ===');
        for (let [key, value] of formData.entries()) {
            if (key === 'file') {
                console.log(`${key}: File object - ${value.name} (${value.size} bytes)`);
            } else {
                console.log(`${key}: "${value}"`);
            }
        }

        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData
        });

        // Get response text first to see what's actually returned
        const responseText = await response.text();
        console.log('=== Server Response ===');
        console.log('Status:', response.status);
        console.log('Raw response:', responseText);

        if (!response.ok) {
            console.error('HTTP error:', response.status);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result;
        try {
            result = JSON.parse(responseText);
            console.log('Parsed response:', result);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid response format from server');
        }

        if (result.success) {
            console.log('Upload successful!');
            let identifierInfo = [];
            if (result.imei) identifierInfo.push(`IMEI: ${result.imei}`);
            if (result.vin_number) identifierInfo.push(`VIN: ${result.vin_number}`);
            if (result.serial_number) identifierInfo.push(`Serial: ${result.serial_number}`);
            
            let successMessage = `File uploaded successfully! File: ${result.original_filename}`;
            if (identifierInfo.length > 0) {
                successMessage += ` (${identifierInfo.join(', ')})`;
            }
            
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
            console.error('Upload failed:', result);
            showToast(result.message || 'Upload failed.', 'error');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('Upload failed. Please check your connection and try again.', 'error');
    }

    setLoading(uploadLoading, uploadBtnText, uploadBtn, false, 'Uploading...', 'Upload Purchase Receipt');
    console.log('=== Form Submission Ended ===');
});

// Search functionality
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
        const vin = (cert.vin_number || '').toLowerCase();
        const serial = (cert.serial_number || '').toLowerCase();
        const searchQuery = query.toLowerCase();
        
        return filename.includes(searchQuery) || 
               imei.includes(searchQuery) || 
               vin.includes(searchQuery) || 
               serial.includes(searchQuery);
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

        // Build identifier info
        let identifierInfo = [];
        if (cert.imei) identifierInfo.push(`IMEI: ${escapeHtml(cert.imei)}`);
        if (cert.vin_number) identifierInfo.push(`VIN: ${escapeHtml(cert.vin_number)}`);
        if (cert.serial_number) identifierInfo.push(`Serial: ${escapeHtml(cert.serial_number)}`);

        return `
            <div class="certificate-item">
                <div class="cert-header">
                    <div class="cert-info">
                        <div class="cert-name">${escapeHtml(cert.original_filename || cert.filename)}</div>
                        <div class="cert-phone">Uploaded: ${new Date(cert.created_at).toLocaleDateString()}</div>
                        <div class="cert-identifiers">${identifierInfo.join(' • ')}</div>
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

function clearMessage(target) {}

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