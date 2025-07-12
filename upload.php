<?php
require_once 'auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}
// fix this soon this is wrong.
try {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated. Please log in first.'
        ]);
        exit;
    }
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to retrieve user data'
        ]);
        exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'No file uploaded';
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
    $maxSize = 5 * 1024 * 1024;
    $allowedTypes = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Only PDF files are allowed');
    }
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 5MB limit');
    }
    $pdo = DatabaseConfig::getConnection();
    $checkStmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND deleted = 0");
    $checkStmt->execute([$currentUser['id']]);
    
    if ($checkStmt->fetch()) {
        throw new Exception('You already have a certificate uploaded. Please delete the existing one before uploading a new one.');
    }
    $uploadDir = './certificates/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    $timestamp = time();
    $randomNum = rand(100, 999);
    $filename = "cert_{$currentUser['id']}_{$timestamp}_{$randomNum}.pdf";
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }
    $insertStmt = $pdo->prepare("
        INSERT INTO certificates 
        (user_id, filename, original_filename, file_path, file_size, mime_type, download_count, max_downloads, deleted, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, 0, 5, 0, NOW(), NOW())
    ");
    $insertStmt->execute([
        $currentUser['id'],
        $filename,
        $file['name'],
        $filepath,
        $file['size'],
        $mimeType
    ]);

    $certificateId = $pdo->lastInsertId();
    $logEntry = date('Y-m-d H:i:s') . " - Uploaded: {$filename} for User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']}\n";
    file_put_contents('./upload_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    echo json_encode([
        'success' => true,
        'certificate_id' => $certificateId,
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_size' => $file['size'],
        'user_name' => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
        'user_mobile' => $currentUser['mobile'],
        'upload_date' => date('c'),
        'message' => 'Certificate uploaded successfully'
    ]);

} catch (Exception $e) {
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>