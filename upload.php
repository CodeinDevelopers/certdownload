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

    if (!isset($_POST['imei']) || empty(trim($_POST['imei']))) {
        throw new Exception('IMEI is required');
    }

    $imei = trim($_POST['imei']);
    if (!preg_match('/^\d{15}$/', $imei)) {
        throw new Exception('Invalid IMEI format. IMEI must be exactly 15 digits');
    }

    // Handle VIN number (optional)
    $vinNumber = null;
    if (isset($_POST['vin_number']) && !empty(trim($_POST['vin_number']))) {
        $vinNumber = trim($_POST['vin_number']);
        // VIN validation: exactly 17 alphanumeric characters (excluding I, O, Q)
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vinNumber)) {
            throw new Exception('Invalid VIN format. VIN must be exactly 17 characters (letters and numbers, excluding I, O, Q)');
        }
    }

    // Handle serial number (optional)
    $serialNumber = null;
    if (isset($_POST['serial_number']) && !empty(trim($_POST['serial_number']))) {
        $serialNumber = trim($_POST['serial_number']);
        // Basic serial number validation (alphanumeric and common special characters)
        if (!preg_match('/^[A-Za-z0-9\-_\/\.#]{1,50}$/', $serialNumber)) {
            throw new Exception('Invalid serial number format. Serial number can contain letters, numbers, and common special characters (-, _, /, ., #)');
        }
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
        throw new Exception('Only PDF, JPG, JPEG, and PNG files are allowed. Detected file type: ' . $mimeType);
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 5MB limit');
    }

    $pdo = DatabaseConfig::getConnection();
    
    // Check if user has reached the 3 certificate limit
    $checkUserStmt = $pdo->prepare("SELECT COUNT(*) as cert_count FROM certificates WHERE user_id = ? AND deleted = 0");
    $checkUserStmt->execute([$currentUser['id']]);
    $result = $checkUserStmt->fetch();
    $certificateCount = $result['cert_count'];

    if ($certificateCount >= 3) {
        throw new Exception('You have reached the maximum limit of 3 certificates. Please delete an existing certificate before uploading a new one.');
    }

    // Check if IMEI is already associated with another user
    $checkImeiStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE imei = ? AND deleted = 0");
    $checkImeiStmt->execute([$imei]);
    $existingImei = $checkImeiStmt->fetch();
    
    if ($existingImei && $existingImei['user_id'] != $currentUser['id']) {
        throw new Exception('This IMEI is already associated with another certificate');
    }

    // Check if VIN number is already associated with another user (if provided)
    if ($vinNumber) {
        $checkVinStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE vin_number = ? AND deleted = 0");
        $checkVinStmt->execute([$vinNumber]);
        $existingVin = $checkVinStmt->fetch();
        
        if ($existingVin && $existingVin['user_id'] != $currentUser['id']) {
            throw new Exception('This VIN number is already associated with another certificate');
        }
    }

    // Check if serial number is already associated with another user (if provided)
    if ($serialNumber) {
        $checkSerialStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE serial_number = ? AND deleted = 0");
        $checkSerialStmt->execute([$serialNumber]);
        $existingSerial = $checkSerialStmt->fetch();
        
        if ($existingSerial && $existingSerial['user_id'] != $currentUser['id']) {
            throw new Exception('This serial number is already associated with another certificate');
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
        throw new Exception('Failed to save uploaded file');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO certificates 
        (user_id, imei, vin_number, serial_number, filename, original_filename, file_path, file_size, mime_type, download_count, max_downloads, deleted, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 5, 0, NOW(), NOW())
    ");
    
    $insertStmt->execute([
        $currentUser['id'],
        $imei,
        $vinNumber,
        $serialNumber,
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

    $logEntry = date('Y-m-d H:i:s') . " - Uploaded: {$filename} for User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']} - IMEI: {$imei}";
    if ($vinNumber) {
        $logEntry .= " - VIN: {$vinNumber}";
    }
    if ($serialNumber) {
        $logEntry .= " - Serial: {$serialNumber}";
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
        'imei' => $imei,
        'user_name' => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
        'user_mobile' => $currentUser['mobile'],
        'upload_date' => date('c'),
        'message' => 'Certificate uploaded successfully',
        'certificates_remaining' => 3 - ($certificateCount + 1)
    ];

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
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>