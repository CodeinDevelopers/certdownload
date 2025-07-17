<?php
require_once 'auth00/user_auth.php';
require_once 'posts.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
    error_log("Upload attempt by user: " . $currentUser['id'] . " (" . $currentUser['firstname'] . " " . $currentUser['lastname'] . ")");
    error_log("POST data: " . print_r($_POST, true));
    if (!isset($_POST['post_id']) || empty(trim($_POST['post_id']))) {
        throw new Exception('Post selection is required');
    }
    $postId = intval($_POST['post_id']);
    error_log("Processing post ID: $postId");
    $pdo = DatabaseConfig::getConnection();
    $postStmt = $pdo->prepare("SELECT id, title, user_id FROM ad_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND status = 1");
    $postStmt->execute([$postId, $currentUser['id']]);
    $post = $postStmt->fetch();

    if (!$post) {
        throw new Exception('Invalid post selection or post not found');
    }
    error_log("Post verified: " . $post['title']);
    $deviceIdentifier = null;
    $identifierType = null;
    if (isset($_POST['device_identifier']) && !empty(trim($_POST['device_identifier']))) {
        $deviceIdentifier = trim($_POST['device_identifier']);
        error_log("Device identifier from form: '$deviceIdentifier'");
        $postTitle = $post['title'];
        $patterns = [
            'IMEI' => '/IMEI:\s*([A-Za-z0-9]+)/i',
            'VIN' => '/VIN:\s*([A-Za-z0-9]+)/i',
            'SERIAL' => '/serial:\s*([A-Za-z0-9]+)/i'
        ];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $postTitle, $matches)) {
                if (strtolower($matches[1]) === strtolower($deviceIdentifier)) {
                    $identifierType = $type;
                    error_log("Identifier type determined: $type");
                    break;
                }
            }
        }
        if (!$identifierType) {
            error_log("Could not determine identifier type from post title: '$postTitle'");
            error_log("Device identifier: '$deviceIdentifier'");
        }
    }
    if ($deviceIdentifier && $identifierType) {
        switch ($identifierType) {
            case 'IMEI':
                if (!preg_match('/^\d{15}$/', $deviceIdentifier)) {
                    throw new Exception("Invalid IMEI format. IMEI must be exactly 15 digits");
                }
                break;
            case 'VIN':
                $vinUpper = strtoupper($deviceIdentifier);
                if (!preg_match('/^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]{17}$/', $vinUpper)) {
                    throw new Exception("Invalid VIN format. VIN must be exactly 17 characters (letters A-Z except I, O, Q and numbers 0-9)");
                }
                $deviceIdentifier = $vinUpper;
                break;
            case 'SERIAL':
                if (!preg_match('/^[A-Za-z0-9_\/.#\s-]{1,50}$/', $deviceIdentifier)) {
                    throw new Exception("Invalid serial number format. Serial number can contain letters, numbers, spaces, and these special characters: - _ / . #");
                }
                break;
        }
        error_log("Device identifier validated successfully: '$deviceIdentifier' (Type: $identifierType)");
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
        error_log("File upload error: " . $error_message);
        throw new Exception($error_message);
    }

    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024;
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png'
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mimeType === 'image/jpg') {
        $mimeType = 'image/jpeg';
    }
    if (!in_array($mimeType, $allowedTypes)) {
        error_log("Invalid file type: $mimeType");
        throw new Exception('Only PDF, JPG, JPEG, and PNG files are allowed. Detected file type: ' . $mimeType);
    }
    if ($file['size'] > $maxSize) {
        error_log("File too large: " . $file['size'] . " bytes");
        throw new Exception('File size exceeds 5MB limit');
    }
    $checkUserStmt = $pdo->prepare("SELECT COUNT(*) as cert_count FROM certificates WHERE user_id = ? AND deleted = 0");
    $checkUserStmt->execute([$currentUser['id']]);
    $result = $checkUserStmt->fetch();
    $certificateCount = $result['cert_count'];
    if ($certificateCount >= 3) {
        throw new Exception('You have reached the maximum limit of 3 certificates. Please delete an existing certificate before uploading a new one.');
    }
    $checkPostStmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND post_id = ? AND deleted = 0");
    $checkPostStmt->execute([$currentUser['id'], $postId]);
    $existingCert = $checkPostStmt->fetch();
    
    if ($existingCert) {
        throw new Exception('A certificate for this post already exists. Please delete the existing certificate first or select a different post.');
    }
    if ($deviceIdentifier && $identifierType) {
        $columnName = '';
        switch ($identifierType) {
            case 'IMEI':
                $columnName = 'imei';
                break;
            case 'VIN':
                $columnName = 'vin_number';
                break;
            case 'SERIAL':
                $columnName = 'serial_number';
                break;
        }
        
        if ($columnName) {
            $checkIdentifierStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE $columnName = ? AND deleted = 0");
            $checkIdentifierStmt->execute([$deviceIdentifier]);
            $existingIdentifier = $checkIdentifierStmt->fetch();
            
            if ($existingIdentifier && $existingIdentifier['user_id'] != $currentUser['id']) {
                throw new Exception("This $identifierType is already associated with another certificate");
            }
        }
    }
    $uploadDir = './certificates/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    $timestamp = time();
    $randomNum = rand(100, 999);
    $extension = '';
    switch ($mimeType) {
        case 'application/pdf':
            $extension = '.pdf';
            break;
        case 'image/jpeg':
            $extension = '.jpg';
            break;
        case 'image/png':
            $extension = '.png';
            break;
        default:
            $extension = '.pdf';
    }
    $filename = "cert_{$currentUser['id']}_{$timestamp}_{$randomNum}{$extension}";
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("Failed to move uploaded file from {$file['tmp_name']} to $filepath");
        throw new Exception('Failed to save uploaded file');
    }
    $imei = ($identifierType === 'IMEI') ? $deviceIdentifier : null;
    $vinNumber = ($identifierType === 'VIN') ? $deviceIdentifier : null;
    $serialNumber = ($identifierType === 'SERIAL') ? $deviceIdentifier : null;
    $insertStmt = $pdo->prepare("
        INSERT INTO certificates 
        (user_id, post_id, imei, vin_number, serial_number, device_identifier, filename, original_filename, file_path, file_size, mime_type, download_count, max_downloads, deleted, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 5, 0, NOW(), NOW())
    ");
    $insertStmt->execute([
        $currentUser['id'],
        $postId,
        $imei,
        $vinNumber,
        $serialNumber,
        $deviceIdentifier,
        $filename,
        $file['name'],
        $filepath,
        $file['size'],
        $mimeType
    ]);
    $certificateId = $pdo->lastInsertId();
    $logDir = './logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logEntry = date('Y-m-d H:i:s') . " - Uploaded: {$filename} for User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']}";
    $logEntry .= " - Post ID: {$postId} - Post Title: {$post['title']}";
    if ($deviceIdentifier) {
        $logEntry .= " - Device Identifier: {$deviceIdentifier}";
        if ($identifierType) {
            $logEntry .= " (Type: {$identifierType})";
        }
    }
    $logEntry .= " - Type: {$mimeType}\n";
    file_put_contents($logDir . 'upload_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    $response = [
        'success' => true,
        'certificate_id' => $certificateId,
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $mimeType,
        'post_id' => $postId,
        'post_title' => $post['title'],
        'user_name' => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
        'user_mobile' => $currentUser['mobile'],
        'upload_date' => date('c'),
        'message' => 'Certificate uploaded successfully',
        'certificates_remaining' => 3 - ($certificateCount + 1)
    ];
    if ($deviceIdentifier) {
        $response['device_identifier'] = $deviceIdentifier;
        if ($identifierType) {
            $response['identifier_type'] = $identifierType;
        }
    }
    if ($imei) {
        $response['imei'] = $imei;
    }
    if ($vinNumber) {
        $response['vin_number'] = $vinNumber;
    }
    if ($serialNumber) {
        $response['serial_number'] = $serialNumber;
    }
    echo json_encode($response);
} catch (Exception $e) {
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    error_log("Upload error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>