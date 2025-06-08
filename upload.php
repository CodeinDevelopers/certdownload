<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Validate required fields
    if (empty($_POST['phoneNumber']) || empty($_POST['displayName'])) {
        throw new Exception('Phone number and display name are required');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'No file uploaded';

        // Handle specific upload errors
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File upload was interrupted';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file was selected';
                break;
            default:
                $error_message = 'File upload failed';
        }

        throw new Exception($error_message);
    }

    $file = $_FILES['file'];
    $phoneNumber = trim($_POST['phoneNumber']);
    $displayName = trim($_POST['displayName']);

    // Validate file type
    $allowedTypes = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Only PDF files are allowed');
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 5MB limit');
    }

    // Create certificates directory if it doesn't exist
    $uploadDir = './certificates/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $timestamp = time();
    $randomNum = rand(100, 999);
    $filename = "cert_{$timestamp}_{$randomNum}.pdf";
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Load and update /data/certificates.json
    $jsonFile = './data/certificates.json';
    $certificates = [];

    if (file_exists($jsonFile)) {
        $certificates = json_decode(file_get_contents($jsonFile), true) ?: [];
    }

    // Check if phone number already exists
    if (isset($certificates[$phoneNumber])) {
        throw new Exception('This phone number already has a certificate uploaded.');
    }

    // Add new certificate entry with the correct structure
    $certificates[$phoneNumber] = [
        'filename' => $filename,
        'displayName' => $displayName,
        'path' => $filepath,
        'downloadCount' => 0,
        'maxDownloads' => 5,
        'deleted' => false
    ];

    // Save updated JSON
    if (!file_put_contents($jsonFile, json_encode($certificates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        throw new Exception('Failed to save certificate data');
    }

    // Log the upload
    $logEntry = date('Y-m-d H:i:s') . " - Uploaded: {$filename} for {$phoneNumber} ({$displayName})\n";
    file_put_contents('./upload_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

    // Return success response
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => $filepath,
        'phoneNumber' => $phoneNumber,
        'uploadDate' => date('c'),
        'message' => 'File uploaded and recorded successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>