function generateCertificateHTML(certificates) {

    return certificates.map(cert => {

        const remainingDownloads = cert.max_downloads - cert.download_count;
        const statusColor = remainingDownloads > 0 ? '#34c759' : '#ff3b30';
        const statusText = remainingDownloads > 0 ? 'Active' : 'Expired';
        const progressPercentage = Math.round((cert.download_count / cert.max_downloads) * 100);
        const canDownload = remainingDownloads > 0;
        const postTitle = cert.post_title || 'Unknown Post';
        return `
            <div class="certificate-item">
                <div class="cert-header">
                    <div class="cert-info">
                        <div class="cert-name">${escapeHtml(cert.original_filename || cert.filename)}</div>
                        <div class="cert-phone">Uploaded: ${new Date(cert.created_at).toLocaleDateString()}</div>
                        <div class="cert-identifiers">Associated Post: ${escapeHtml(postTitle)}</div>
                        ${cert.device_identifier ? <div class="cert-identifiers">Device ID: ${escapeHtml(cert.device_identifier)}</div> : ''}
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

function filterCertificates(query) {
    const filteredCertificates = allCertificates.filter(cert => {
        const filename = (cert.original_filename || cert.filename || '').toLowerCase();
        const postTitle = (cert.post_title || '').toLowerCase();
        const deviceIdentifier = (cert.device_identifier || '').toLowerCase();
        const searchQuery = query.toLowerCase();
        return filename.includes(searchQuery) || 
               postTitle.includes(searchQuery) || 
               deviceIdentifier.includes(searchQuery);

    });
    displayCertificates(filteredCertificates);

}