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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['certificate_id']) || empty($input['certificate_id'])) {
        throw new Exception('Certificate ID is required');
    }
    $certificateId = intval($input['certificate_id']);
    if ($certificateId <= 0) {
        throw new Exception('Invalid certificate ID');
    }
    if (!isset($input['confirm_permanent_delete']) || $input['confirm_permanent_delete'] !== true) {
        throw new Exception('Permanent deletion requires confirmation. Set confirm_permanent_delete to true.');
    }
    $pdo = DatabaseConfig::getConnection();
    $checkStmt = $pdo->prepare("
        SELECT id, user_id, filename, original_filename, file_path, imei, deleted, created_at, updated_at
        FROM certificates 
        WHERE id = ?
    ");
    $checkStmt->execute([$certificateId]);
    $certificate = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        throw new Exception('Certificate not found');
    }
    if ($certificate['user_id'] != $currentUser['id']) {
        throw new Exception('You do not have permission to delete this certificate');
    }
    $logData = [
        'id' => $certificate['id'],
        'filename' => $certificate['filename'],
        'original_filename' => $certificate['original_filename'],
        'file_path' => $certificate['file_path'],
        'imei' => $certificate['imei'],
        'was_soft_deleted' => $certificate['deleted'] == 1,
        'created_at' => $certificate['created_at'],
        'updated_at' => $certificate['updated_at'],
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
        'user_mobile' => $currentUser['mobile']
    ];
    $pdo->beginTransaction();
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM certificates WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$certificateId, $currentUser['id']]);
        if ($deleteStmt->rowCount() === 0) {
            throw new Exception('Failed to delete certificate from database. Certificate may not exist or permission denied.');
        }
        $verifyStmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificates WHERE id = ?");
        $verifyStmt->execute([$certificateId]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        if ($verifyResult['count'] > 0) {
            throw new Exception('Database deletion verification failed - record still exists');
        }
        $filePath = $certificate['file_path'];
        $fileDeleted = false;
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $fileDeleted = true;
            } else {
                throw new Exception('Failed to delete physical file. Operation aborted to maintain data consistency.');
            }
        } else {
            error_log("Warning: Physical file not found during hard delete: {$filePath}");
        }
        $pdo->commit();
        $logEntry = date('Y-m-d H:i:s') . " - HARD DELETED (PERMANENT): ID: {$logData['id']} - {$logData['filename']} (Original: {$logData['original_filename']}) for User ID: {$logData['user_id']} ({$logData['user_name']}) - Mobile: {$logData['user_mobile']} - IMEI: {$logData['imei']} - File Path: {$logData['file_path']} - Created: {$logData['created_at']} - Was Soft Deleted: " . ($logData['was_soft_deleted'] ? 'Yes' : 'No') . " - Physical File Deleted: " . ($fileDeleted ? 'Yes' : 'No') . "\n";
        file_put_contents('./logs/hard_deletion_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        echo json_encode([
            'success' => true,
            'message' => 'Certificate permanently deleted successfully',
            'certificate_id' => $certificateId,
            'filename' => $logData['original_filename'] ?: $logData['filename'],
            'deleted_at' => date('c'),
            'deletion_type' => 'permanent',
            'database_deleted' => true,
            'file_deleted' => $fileDeleted,
            'warning' => !$fileDeleted ? 'Database record deleted but physical file was not found' : null
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Hard delete certificate error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'deletion_type' => 'permanent_failed'
    ]);
}
?>