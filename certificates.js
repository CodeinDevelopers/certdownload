class CertificateManager {
    constructor() {
       
    }
  async processCertificateDownload(phoneNumber) {
    const normalizedPhone = this.normalizePhoneNumber(phoneNumber);
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
    link.download = `certificate_${normalizedPhone}.pdf`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    return {
        certificate: {
            filename: `certificate_${normalizedPhone}.pdf`,
            displayName: 'Certificate',
            path: url
        },
        remainingDownloads: 0, // Can't return this info with direct file serving
        isLastDownload: false
    };
}
    downloadFile(certificatePath, filename) {
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
    messageElement.textContent = text;
    messageElement.className = `message ${type} show`;
    setTimeout(() => {
        messageElement.classList.remove('show');
    }, 5000);
}
function setLoading(loadingElement, btnTextElement, submitBtn, isLoading, loadingText = 'Processing...', defaultText = 'Submit') {
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
const certificateManager = new CertificateManager();
