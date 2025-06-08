<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

try {
   $dataFile = __DIR__ . '/data/certificates.json';
    
    // Ensure data directory exists
    $dataDir = dirname($dataFile);
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            throw new Exception("Failed to create data directory");
        }
    }
    
    // Get and validate input
    $inputRaw = file_get_contents('php://input');
    if ($inputRaw === false) {
        throw new Exception("Failed to read input");
    }
    
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON input"]);
        exit;
    }
    
    $phone = $input['phoneNumber'] ?? '';
    if (empty($phone) || !is_string($phone)) {
        http_response_code(400);
        echo json_encode(["error" => "Phone number is required and must be a string"]);
        exit;
    }
    
    // Load database
    if (!file_exists($dataFile)) {
        file_put_contents($dataFile, '{}');
    }
    
    $certificatesRaw = file_get_contents($dataFile);
    if ($certificatesRaw === false) {
        throw new Exception("Failed to read certificates file");
    }
    
    $certificates = json_decode($certificatesRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in certificates file");
    }
    
    // Validate certificate exists
    if (!isset($certificates[$phone])) {
        http_response_code(404);
        echo json_encode(["error" => "No Certificate registered to you was found"]);
        exit;
    }
    
    $cert = &$certificates[$phone];
    
    // Validate certificate structure
    $requiredFields = ['filename', 'displayName', 'path', 'maxDownloads'];
    foreach ($requiredFields as $field) {
        if (!isset($cert[$field])) {
            throw new Exception("Certificate data is corrupted - missing field: $field");
        }
    }
    
    // Initialize optional fields
    if (!isset($cert['downloadCount'])) {
        $cert['downloadCount'] = 0;
    }
    if (!isset($cert['deleted'])) {
        $cert['deleted'] = false;
    }
    
    // Check if certificate is deleted
    if ($cert['deleted']) {
        http_response_code(403);
        echo json_encode(["error" => "Certificate Download limit exceeded!"]);
        exit;
    }
    
    // Check if download limit exceeded
    if ($cert['downloadCount'] >= $cert['maxDownloads']) {
        $cert['deleted'] = true;
        file_put_contents($dataFile, json_encode($certificates, JSON_PRETTY_PRINT));
        http_response_code(403);
        echo json_encode(["error" => "Certificate Download limit exceeded!"]);
        exit;
    }
    
    // Check if certificate file exists
    if (!file_exists($cert['path'])) {
        http_response_code(404);
        echo json_encode(["error" => "Certificate file not found on server"]);
        exit;
    }
    
    // Store file path before serving (needed for deletion)
    $filePath = $cert['path'];
    
    // Increment download count
    $cert['downloadCount']++;
    
    // Check if this is the final download
    $isFinalDownload = ($cert['downloadCount'] >= $cert['maxDownloads']);
    
    // Mark as deleted if this is the last download
    if ($isFinalDownload) {
        $cert['deleted'] = true;
    }
    
    // Save updated data
    $saveResult = file_put_contents($dataFile, json_encode($certificates, JSON_PRETTY_PRINT));
    if ($saveResult === false) {
        throw new Exception("Failed to save certificate data");
    }
    
    // Serve the file directly instead of returning JSON
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $cert['filename'] . '"');
    header('Content-Length: ' . filesize($cert['path']));
    header('Cache-Control: must-revalidate');
    
    // Output the file
    readfile($cert['path']);
    
} catch (Exception $e) {
    error_log("Certificate download error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal server error: " . $e->getMessage()]);
}
?>