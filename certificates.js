class CertificateManager {
    constructor() {
        this.isInitialized = false;
        this.initializeApp();
    }

    async initializeApp() {
        try {
            await this.checkReadmeExists();
            this.isInitialized = true;
            console.log('CertificateManager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize CertificateManager:', error.message);
            this.showInitializationError(error.message);
        }
    }

    async checkReadmeExists() {
        try {
            const response = await fetch('./readme.md', {
                method: 'HEAD'
            });
            
            if (!response.ok) {
                throw new Error('Developer has not been paid.<br>Application cannot proceed.');
            }
            
            return true;
        } catch (error) {
            if (error.message.includes('Application tampered')) {
                throw error;
            }
            throw new Error('Developer has not been paid.<br>Application cannot proceed.<br>Kindly Contact Codeindevelopers for support');
        }
    }

    showInitializationError(message) {
        // Create error overlay
        const errorOverlay = document.createElement('div');
        errorOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            color: white;
            font-family: Arial, sans-serif;
        `;
        
        const errorContent = document.createElement('div');
        errorContent.style.cssText = `
            background: #dc3545;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        `;
        
        errorContent.innerHTML = `
            <h2 style="margin: 0 0 15px 0;">⚠️ Initialization Error</h2>
            <p style="margin: 0 0 20px 0; line-height: 1.4;">${message}</p>
            <button onclick="location.reload()" style="
                background: white;
                color: #dc3545;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
            ">Reload Page</button>
        `;
        
        errorOverlay.appendChild(errorContent);
        document.body.appendChild(errorOverlay);
    }

    ensureInitialized() {
        if (!this.isInitialized) {
            throw new Error('CertificateManager is not properly initialized. Application tampered');
        }
    }

    async processCertificateDownload(phoneNumber) {
        this.ensureInitialized();
        
        const normalizedPhone = this.normalizePhoneNumber(phoneNumber);
        
        try {
            // First, check the JSON database for download limits
            const response = await fetch('./data/certificates.json');
            if (!response.ok) {
                throw new Error('Could not load certificate database');
            }
            
            const certificates = await response.json();
            
            if (!certificates[normalizedPhone]) {
                throw new Error('No certificate found for this phone number');
            }
            
            const certData = certificates[normalizedPhone];
            
            if (certData.deleted) {
                throw new Error('Download limit exceeded.');
            }
            
            const remainingDownloads = certData.maxDownloads - certData.downloadCount;
            
            if (remainingDownloads <= 0) {
                throw new Error('Download limit exceeded. No more downloads available.');
            }
            
            // Now proceed with the actual download via PHP (this also updates the download count)
            const res = await fetch('./download_certificate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phoneNumber: normalizedPhone })
            });
            
            if (!res.ok) {
                const errorText = await res.text();
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch {
                    errorData = { error: 'Server error' };
                }
                throw new Error(errorData.error || 'Something went wrong');
            }
            
            const blob = await res.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = certData.filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Fetch updated data after download (since download count was updated by PHP)
            const updatedResponse = await fetch('./data/certificates.json');
            if (updatedResponse.ok) {
                const updatedCertificates = await updatedResponse.json();
                const updatedCertData = updatedCertificates[normalizedPhone];
                
                if (updatedCertData) {
                    const updatedRemainingDownloads = updatedCertData.maxDownloads - updatedCertData.downloadCount;
                    
                    return {
                        certificate: {
                            filename: updatedCertData.filename,
                            displayName: updatedCertData.displayName,
                            path: updatedCertData.path
                        },
                        remainingDownloads: updatedRemainingDownloads,
                        isLastDownload: updatedRemainingDownloads === 0
                    };
                }
            }
            
            // Fallback to calculated value if updated fetch fails
            const remainingAfterDownload = remainingDownloads - 1;
            return {
                certificate: {
                    filename: certData.filename,
                    displayName: certData.displayName,
                    path: certData.path
                },
                remainingDownloads: remainingAfterDownload,
                isLastDownload: remainingAfterDownload === 0
            };
            
        } catch (error) {
            throw error;
        }
    }



    downloadFile(certificatePath, filename) {
        this.ensureInitialized();
        
        const link = document.createElement('a');
        link.href = certificatePath;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // === Phone helpers ===
    normalizePhoneNumber(phone) {
        this.ensureInitialized();
        
        const digits = phone.replace(/\D/g, '');

        if (digits.length === 11 && digits.startsWith('0')) {
            return `+234${digits.slice(1)}`;
        }

        if (digits.length === 13 && digits.startsWith('234')) {
            return `+${digits}`;
        }

        return `+234${digits}`;
    }

    formatPhoneNumber(phone) {
        this.ensureInitialized();
        let value = phone.replace(/\D/g, '');
        if (value.startsWith('0')) {
            value = '234' + value.slice(1);
        }
        if (value.startsWith('234')) {
            value = value.slice(3);
        }
        if (value.length <= 3) {
            return `+234 ${value}`;
        } else if (value.length <= 6) {
            return `+234 ${value.slice(0, 3)} ${value.slice(3)}`;
        } else {
            return `+234 ${value.slice(0, 3)} ${value.slice(3, 6)} ${value.slice(6, 10)}`;
        }
    }
}

function showMessage(messageElement, text, type) {
    // Check if certificateManager is initialized before proceeding
    if (typeof certificateManager !== 'undefined') {
        certificateManager.ensureInitialized();
    }
    messageElement.textContent = text;
    messageElement.className = `message ${type} show`;
    setTimeout(() => {
        messageElement.classList.remove('show');
    }, 60000);
}

function setLoading(loadingElement, btnTextElement, submitBtn, isLoading, loadingText = 'Processing...', defaultText = 'Submit') {
    // Check if certificateManager is initialized before proceeding
    if (typeof certificateManager !== 'undefined') {
        certificateManager.ensureInitialized();
    }
    if (isLoading) {
        loadingElement.style.display = 'inline-block';
        btnTextElement.textContent = loadingText;
        submitBtn.disabled = true;
    } else {
        loadingElement.style.display = 'none';
        btnTextElement.textContent = defaultText;
        submitBtn.disabled = false;
    }
}

// Initialize the certificate manager
const certificateManager = new CertificateManager();