<?php
require_once 'auth.php';
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
    $certificateId = $input['certificate_id'] ?? '';
    if (empty($certificateId) || !is_numeric($certificateId)) {
        http_response_code(400);
        echo json_encode(["error" => "Certificate ID is required and must be a number"]);
        exit;
    }
    $pdo = DatabaseConfig::getConnection();
    $stmt = $pdo->prepare("
        SELECT id, user_id, filename, original_filename, file_path, file_size, 
               mime_type, download_count, max_downloads, deleted, imei
        FROM certificates 
        WHERE id = ? AND user_id = ? AND deleted = 0
    ");
    $stmt->execute([$certificateId, $currentUser['id']]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        http_response_code(404);
        echo json_encode(["error" => "Certificate not found or you don't have permission to download it"]);
        exit;
    }
    if ($certificate['download_count'] >= $certificate['max_downloads']) {
        http_response_code(403);
        echo json_encode(["error" => "Certificate download limit exceeded!"]);
        exit;
    }
    if (!file_exists($certificate['file_path'])) {
        http_response_code(404);
        echo json_encode(["error" => "Certificate file not found on server"]);
        exit;
    }
    $newDownloadCount = $certificate['download_count'] + 1;
    $updateStmt = $pdo->prepare("
        UPDATE certificates 
        SET download_count = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$newDownloadCount, $certificateId]);
    $logEntry = date('Y-m-d H:i:s') . " - Downloaded: {$certificate['filename']} by User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']} - IMEI: {$certificate['imei']} - Download #{$newDownloadCount}/{$certificate['max_downloads']}\n";
    file_put_contents('./logs/download_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    $downloadFilename = $certificate['original_filename'] ?: $certificate['filename'];
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($downloadFilename) . '"');
    header('Content-Length: ' . filesize($certificate['file_path']));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    if (readfile($certificate['file_path']) === false) {
        throw new Exception("Failed to read certificate file");
    }
    exit;
} catch (Exception $e) {
    error_log("Certificate download error: " . $e->getMessage());
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Internal server error: " . $e->getMessage()]);
}
?>