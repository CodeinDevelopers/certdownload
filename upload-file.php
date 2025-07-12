<?php
require_once 'auth.php';
protectPage('login.php');
if (!checkAuthTimeout(30)) {
    header('Location: login.php');
    exit();
}

// Get current user data
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Upload Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .user-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .user-info h2 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info p {
            margin: 4px 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .form-group.disabled {
            opacity: 0.7;
        }
        
        .form-group.disabled input {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #666666;
        }


        
        .info-note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #1565c0;
        }
    </style>
</head>
<body>
    <a href="index.html" class="nav-link">← Download Portal</a>
    <a href="logout.php" class="logout-btn">Logout</a>
    
    <div class="main-container">
        <div class="header">
            <h1>Certificate Upload Portal</h1>
            <p class="subtitle">Upload new certificates and manage existing ones</p>
        </div>

        <!-- User Information Section -->
        <div class="user-info">
            <h2>Welcome, <?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?>!</h2>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($currentUser['mobile']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email']); ?></p>
        </div>

        <div class="upload-section">
            <div class="info-note">
                <strong>Note:</strong>Accepted file formats: PDF, JPG, JPEG, PNG (up to 5MB).
            </div>
            
            <form id="uploadForm" class="upload-form">  
                <div class="form-group full-width">
                    <label for="certificateFile">Certificate File</label>
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
                        <span id="uploadBtnText">Upload Certificate</span>
                    </button>
                </div>
            </form>
            <div class="message" id="uploadMessage"></div>
        </div>

        <div class="certificates-list">
            <div class="list-header">My Files</div>
            <div id="certificatesList">
                <div class="empty-state">Loading files...</div>
            </div>
        </div>


    </div>



    <script src="certificates.js"></script>
    <script>
        const uploadForm = document.getElementById('uploadForm');
        const fileInput = document.getElementById('certificateFile');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadLoading = document.getElementById('uploadLoading');
        const uploadBtnText = document.getElementById('uploadBtnText');
        const uploadMessage = document.getElementById('uploadMessage');
        const certificatesList = document.getElementById('certificatesList');

        // Handle form submission
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const file = fileInput.files[0];
            clearMessage(uploadMessage);

            // Validate file selection
            if (!file) {
                showMessage(uploadMessage, 'Please select a file to upload', 'error');
                return;
            }

            // Validate file type
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showMessage(uploadMessage, 'Please select a PDF, JPG, JPEG, or PNG file', 'error');
                return;
            }

            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                showMessage(uploadMessage, 'File size exceeds 5MB limit', 'error');
                return;
            }

            // Set loading state
            setLoading(uploadLoading, uploadBtnText, uploadBtn, true, 'Uploading...', 'Upload Certificate');

            try {
                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showMessage(uploadMessage, 
                        `File uploaded successfully! File: ${result.original_filename}`, 
                        'success'
                    );
                    uploadForm.reset();
                    
                    // Refresh certificates list after successful upload
                    setTimeout(() => {
                        loadCertificates();
                    }, 1000);
                } else {
                    showMessage(uploadMessage, result.message || 'Upload failed.', 'error');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showMessage(uploadMessage, 'Upload failed. Please check your connection and try again.', 'error');
            }

            // Reset loading state
            setLoading(uploadLoading, uploadBtnText, uploadBtn, false, 'Uploading...', 'Upload Certificate');
        });

        // Load certificates function (simplified for your PHP backend)
        async function loadCertificates() {
            try {
                certificatesList.innerHTML = '<div class="empty-state">Loading files...</div>';
                
                // Try to load from your certificates endpoint
                const res = await fetch('./data/certificates.json?' + Date.now());
                if (!res.ok) {
                    if (res.status === 404) {
                        certificatesList.innerHTML = '<div class="empty-state">No files uploaded yet</div>';
                        return;
                    }
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                let certificates = [];
                
                if (Array.isArray(data)) {
                    certificates = data;
                } else {
                    certificates = Object.keys(data).map(key => data[key]);
                }

                const activeCertificates = certificates.filter(cert => !cert.deleted);

                if (activeCertificates.length === 0) {
                    certificatesList.innerHTML = '<div class="empty-state">No files uploaded yet</div>';
                } else {
                    certificatesList.innerHTML = generateCertificateHTML(activeCertificates);
                }
                
            } catch (error) {
                console.error('Error loading files:', error);
                certificatesList.innerHTML = '<div class="empty-state">Your files will appear here after upload</div>';
            }
        }

        // Generate certificate HTML
        function generateCertificateHTML(certificates) {
            return certificates.map(cert => {
                const remainingDownloads = cert.max_downloads - cert.download_count;
                const statusColor = remainingDownloads > 0 ? '#34c759' : '#ff3b30';
                const statusText = remainingDownloads > 0 ? 'Active' : 'Expired';
                const progressPercentage = Math.round((cert.download_count / cert.max_downloads) * 100);
                
                return `
                    <div class="certificate-item">
                        <div class="cert-header">
                            <div class="cert-info">
                                <div class="cert-name">${escapeHtml(cert.original_filename || cert.filename)}</div>
                                <div class="cert-phone">Uploaded: ${new Date(cert.created_at).toLocaleDateString()}</div>
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
                    </div>
                `;
            }).join('');
        }

        // Utility functions
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

        function clearMessage(target) {
            target.className = 'message';
            target.innerHTML = '';
            target.classList.remove('show');
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

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadCertificates();
        });

        // Auto-refresh every 30 seconds
        setInterval(loadCertificates, 30000);

        // Handle page visibility change to refresh when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadCertificates();
            }
        });
    </script>
</body>
</html>