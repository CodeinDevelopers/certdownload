<?php
require_once 'auth.php';

// Enable error reporting for debugging
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
    // Authentication check
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

    // Debug logging
    error_log("Upload attempt by user: " . $currentUser['id'] . " (" . $currentUser['firstname'] . " " . $currentUser['lastname'] . ")");
    error_log("POST data: " . print_r($_POST, true));

    // IMEI validation
    $imei = null;
    if (isset($_POST['imei']) && !empty(trim($_POST['imei']))) {
        $imei = trim($_POST['imei']);
        error_log("Processing IMEI: '$imei' (length: " . strlen($imei) . ")");
        
        if (!preg_match('/^\d{15}$/', $imei)) {
            error_log("IMEI validation failed: '$imei'");
            if (strlen($imei) !== 15) {
                throw new Exception("Invalid IMEI length. IMEI must be exactly 15 digits, got " . strlen($imei));
            } else {
                throw new Exception('Invalid IMEI format. IMEI must contain only numbers');
            }
        }
        error_log("IMEI validated successfully: '$imei'");
    }

    // VIN validation - Fixed with better debugging
    $vinNumber = null;
    if (isset($_POST['vin_number']) && !empty(trim($_POST['vin_number']))) {
        $vinNumber = strtoupper(trim($_POST['vin_number']));
        error_log("Processing VIN: '$vinNumber' (length: " . strlen($vinNumber) . ")");
        
        // More comprehensive VIN validation - allows A-Z except I, O, Q and numbers 0-9
        if (!preg_match('/^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]{17}$/', $vinNumber)) {
            error_log("VIN validation failed: '$vinNumber'");
            error_log("VIN length: " . strlen($vinNumber));
            
            // Debug each character
            for ($i = 0; $i < strlen($vinNumber); $i++) {
                $char = $vinNumber[$i];
                if (!preg_match('/^[ABCDEFGHJKLMNPRSTUVWXYZ0-9]$/', $char)) {
                    error_log("Invalid VIN character at position $i: '$char' (ASCII: " . ord($char) . ")");
                }
            }
            
            // More specific error messages
            if (strlen($vinNumber) !== 17) {
                throw new Exception("Invalid VIN length. VIN must be exactly 17 characters, got " . strlen($vinNumber));
            } else {
                throw new Exception('Invalid VIN format. VIN must contain only letters A-Z (except I, O, Q) and numbers 0-9');
            }
        }
        error_log("VIN validated successfully: '$vinNumber'");
    }

    // Serial number validation - Fixed regex pattern
    $serialNumber = null;
    if (isset($_POST['serial_number']) && !empty(trim($_POST['serial_number']))) {
        $serialNumber = trim($_POST['serial_number']);
        error_log("Processing Serial: '$serialNumber' (length: " . strlen($serialNumber) . ")");
        
        // Fixed pattern - hyphen at the end to avoid escaping issues
        if (!preg_match('/^[A-Za-z0-9_\/.#\s-]{1,50}$/', $serialNumber)) {
            error_log("Serial validation failed: '$serialNumber'");
            error_log("Serial length: " . strlen($serialNumber));
            
            // Debug each character
            for ($i = 0; $i < strlen($serialNumber); $i++) {
                $char = $serialNumber[$i];
                if (!preg_match('/^[A-Za-z0-9_\/.#\s-]$/', $char)) {
                    error_log("Invalid serial character at position $i: '$char' (ASCII: " . ord($char) . ")");
                }
            }
            
            // More specific error messages
            if (strlen($serialNumber) > 50) {
                throw new Exception("Serial number too long. Maximum 50 characters allowed, got " . strlen($serialNumber));
            } else {
                throw new Exception('Invalid serial number format. Serial number can contain letters, numbers, spaces, and these special characters: - _ / . #');
            }
        }
        error_log("Serial validated successfully: '$serialNumber'");
    }

    // Additional debugging for the entire validation process
    error_log("Final validation results - IMEI: " . ($imei ? "valid" : "not provided") . 
             ", VIN: " . ($vinNumber ? "valid" : "not provided") . 
             ", Serial: " . ($serialNumber ? "valid" : "not provided"));

    // Check if at least one identifier is provided
    if (!$imei && !$vinNumber && !$serialNumber) {
        throw new Exception('At least one identifier is required: IMEI, VIN Number, or Serial Number');
    }

    // File validation
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
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png'
    ];

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Handle jpg vs jpeg
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

    // Database connection
    $pdo = DatabaseConfig::getConnection();

    // Check certificate count limit
    $checkUserStmt = $pdo->prepare("SELECT COUNT(*) as cert_count FROM certificates WHERE user_id = ? AND deleted = 0");
    $checkUserStmt->execute([$currentUser['id']]);
    $result = $checkUserStmt->fetch();
    $certificateCount = $result['cert_count'];

    if ($certificateCount >= 3) {
        throw new Exception('You have reached the maximum limit of 3 certificates. Please delete an existing certificate before uploading a new one.');
    }

    // Check for duplicate identifiers
    if ($imei) {
        $checkImeiStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE imei = ? AND deleted = 0");
        $checkImeiStmt->execute([$imei]);
        $existingImei = $checkImeiStmt->fetch();
        
        if ($existingImei && $existingImei['user_id'] != $currentUser['id']) {
            throw new Exception('This IMEI is already associated with another certificate');
        }
    }

    if ($vinNumber) {
        $checkVinStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE vin_number = ? AND deleted = 0");
        $checkVinStmt->execute([$vinNumber]);
        $existingVin = $checkVinStmt->fetch();
        
        if ($existingVin && $existingVin['user_id'] != $currentUser['id']) {
            throw new Exception('This VIN number is already associated with another certificate');
        }
    }

    if ($serialNumber) {
        $checkSerialStmt = $pdo->prepare("SELECT id, user_id FROM certificates WHERE serial_number = ? AND deleted = 0");
        $checkSerialStmt->execute([$serialNumber]);
        $existingSerial = $checkSerialStmt->fetch();
        
        if ($existingSerial && $existingSerial['user_id'] != $currentUser['id']) {
            throw new Exception('This serial number is already associated with another certificate');
        }
    }

    // Create upload directory if it doesn't exist
    $uploadDir = './certificates/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
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

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("Failed to move uploaded file from {$file['tmp_name']} to $filepath");
        throw new Exception('Failed to save uploaded file');
    }

    // Insert into database
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

    // Create log directory if it doesn't exist
    $logDir = './logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Log the upload
    $logEntry = date('Y-m-d H:i:s') . " - Uploaded: {$filename} for User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']}";
    if ($imei) {
        $logEntry .= " - IMEI: {$imei}";
    }
    if ($vinNumber) {
        $logEntry .= " - VIN: {$vinNumber}";
    }
    if ($serialNumber) {
        $logEntry .= " - Serial: {$serialNumber}";
    }
    $logEntry .= " - Type: {$mimeType}\n";
    file_put_contents($logDir . 'upload_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

    // Prepare response
    $response = [
        'success' => true,
        'certificate_id' => $certificateId,
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $mimeType,
        'user_name' => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
        'user_mobile' => $currentUser['mobile'],
        'upload_date' => date('c'),
        'message' => 'Certificate uploaded successfully',
        'certificates_remaining' => 3 - ($certificateCount + 1)
    ];
    
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