<?php
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer_host = parse_url($referer, PHP_URL_HOST);
if (empty($referer) || $referer_host !== $_SERVER['HTTP_HOST']) {
    header('HTTP/1.0 403 Forbidden');
    exit('What are you looking for here?');
}
require_once 'auth00/user_auth.php';
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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['certificate_id']) || empty($input['certificate_id'])) {
        throw new Exception('Certificate ID is required');
    }

    $certificateId = intval($input['certificate_id']);
    
    if ($certificateId <= 0) {
        throw new Exception('Invalid certificate ID');
    }
    $pdo = DatabaseConfig::getConnection();
    $checkStmt = $pdo->prepare("
        SELECT id, user_id, filename, original_filename, file_path, imei, deleted 
        FROM certificates 
        WHERE id = ? AND deleted = 0
    ");
    $checkStmt->execute([$certificateId]);
    $certificate = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        throw new Exception('Certificate not found or already deleted');
    }
    if ($certificate['user_id'] != $currentUser['id']) {
        throw new Exception('You do not have permission to delete this certificate');
    }
    $pdo->beginTransaction();
    try {
        $deleteStmt = $pdo->prepare("
            UPDATE certificates 
            SET deleted = 1, updated_at = NOW() 
            WHERE id = ? AND user_id = ? AND deleted = 0
        ");
        $deleteStmt->execute([$certificateId, $currentUser['id']]);
        if ($deleteStmt->rowCount() === 0) {
            throw new Exception('Failed to delete certificate from database. Certificate may already be deleted or does not exist.');
        }
        $verifyStmt = $pdo->prepare("SELECT deleted FROM certificates WHERE id = ?");
        $verifyStmt->execute([$certificateId]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        if (!$verifyResult || $verifyResult['deleted'] != 1) {
            throw new Exception('Database update verification failed');
        }
        $filePath = $certificate['file_path'];
        if (file_exists($filePath)) {
            // Optional: You could rename the file to indicate it's "deleted"
            // $deletedPath = $filePath . '.deleted.' . time();
            // rename($filePath, $deletedPath);
            error_log("Info: Physical file preserved for deleted certificate: {$filePath}");
        }
        $pdo->commit();


        $logDir = './logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logEntry = date('Y-m-d H:i:s') . " - SOFT DELETED: {$certificate['filename']} (Original: {$certificate['original_filename']}) for User ID: {$currentUser['id']} ({$currentUser['firstname']} {$currentUser['lastname']}) - Mobile: {$currentUser['mobile']} - IMEI: {$certificate['imei']} - File preserved at: {$certificate['file_path']}\n";
        file_put_contents($logDir . 'deletion_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        echo json_encode([
            'success' => true,
            'message' => 'Certificate deleted successfully (file preserved)',
            'certificate_id' => $certificateId,
            'filename' => $certificate['original_filename'] ?: $certificate['filename'],
            'deleted_at' => date('c'),
            'file_preserved' => true
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Delete certificate error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>