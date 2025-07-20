<?php
require_once './../auth00/admin_auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}
try {
    if (!isAdminAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Admin not authenticated. Please log in first.'
        ]);
        exit;
    }
    $currentAdmin = getCurrentAdmin();
    if (!$currentAdmin) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to retrieve admin data'
        ]);
        exit;
    }
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
    $filename = $input['filename'] ?? '';
    $certificateId = $input['certificate_id'] ?? null;

    if (empty($filename)) {
        http_response_code(400);
        echo json_encode(["error" => "Filename is required"]);
        exit;
    }
    $filename = basename($filename);
    $filePath = './../certificates/' . $filename;
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(["error" => "File not found on server"]);
        exit;
    }
    if (!is_readable($filePath)) {
        http_response_code(403);
        echo json_encode(["error" => "File not readable"]);
        exit;
    }
    $pdo = AdminDatabaseConfig::getConnection();
    $stmt = $pdo->prepare("
        SELECT id, filename, original_filename, file_path, file_size, 
               mime_type, user_id, imei, created_at, deleted
        FROM certificates 
        WHERE filename = ?
    ");
    $stmt->execute([$filename]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate) {
        http_response_code(404);
        echo json_encode(["error" => "File not found in database"]);
        exit;
    }
    if ($certificateId && $certificate['id'] != $certificateId) {
        http_response_code(404);
        echo json_encode(["error" => "Certificate ID mismatch"]);
        exit;
    }

    // Enhanced logging mechanism matching delete function
    $logDir = './logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $deletedStatus = $certificate['deleted'] ? ' (DELETED)' : '';
    $logEntry = date('Y-m-d H:i:s') . " - ADMIN DOWNLOAD: {$certificate['filename']} (Original: {$certificate['original_filename']}){$deletedStatus} by Admin ID: {$currentAdmin['id']} ({$currentAdmin['name']}) - Email: {$currentAdmin['email']} - Original User ID: {$certificate['user_id']} - IMEI: {$certificate['imei']} - File path: {$certificate['file_path']}\n";
    file_put_contents($logDir . 'admin_download_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

    // Log activity in database (if you have the function)
    if (function_exists('logAdminActivity')) {
        $activityNote = "Downloaded certificate: {$certificate['original_filename']} (ID: {$certificate['id']}) from User ID: {$certificate['user_id']}" . $deletedStatus;
        logAdminActivity('Download File', $activityNote);
    }

    // Determine download filename
    $downloadFilename = $certificate['original_filename'] ?: $certificate['filename'];

    // Clean any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set download headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($downloadFilename) . '"');
    header('Content-Length: ' . filesize($certificate['file_path'] ?: $filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');

    // Output the file
    $actualFilePath = $certificate['file_path'] ?: $filePath;
    if (readfile($actualFilePath) === false) {
        throw new Exception("Failed to read certificate file");
    }

    exit;

} catch (Exception $e) {
    error_log("Admin certificate download error: " . $e->getMessage());
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Internal server error: " . $e->getMessage()]);
}
?>