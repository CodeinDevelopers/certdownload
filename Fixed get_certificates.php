<?php
require_once 'auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
            'message' => 'User not authenticated'
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

    $pdo = DatabaseConfig::getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.user_id,
            c.post_id,
            c.imei,
            c.vin_number,
            c.serial_number,
            c.device_identifier,
            c.filename,
            c.original_filename,
            c.file_path,
            c.file_size,
            c.mime_type,
            c.download_count,
            c.max_downloads,
            c.created_at,
            c.updated_at,
            p.title as post_title
        FROM certificates c
        LEFT JOIN ad_lists p ON c.post_id = p.id
        WHERE c.user_id = ? AND c.deleted = 0
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$currentUser['id']]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $processedCertificates = [];
    foreach ($certificates as $cert) {
        $processedCert = [
            'id' => (int)$cert['id'],
            'user_id' => (int)$cert['user_id'],
            'post_id' => (int)$cert['post_id'],
            'imei' => $cert['imei'],
            'vin_number' => $cert['vin_number'],
            'serial_number' => $cert['serial_number'],
            'device_identifier' => $cert['device_identifier'],
            'filename' => $cert['filename'],
            'original_filename' => $cert['original_filename'],
            'file_path' => $cert['file_path'],
            'file_size' => (int)$cert['file_size'],
            'mime_type' => $cert['mime_type'],
            'download_count' => (int)$cert['download_count'],
            'max_downloads' => (int)$cert['max_downloads'],
            'created_at' => $cert['created_at'],
            'updated_at' => $cert['updated_at'],
            'post_title' => $cert['post_title'] ?? 'Unknown Post' // This is the key fix
        ];
        $processedCertificates[] = $processedCert;
    }

    echo json_encode([
        'success' => true,
        'certificates' => $processedCertificates,
        'count' => count($processedCertificates)
    ]);

} catch (Exception $e) {
    error_log("Get certificates error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load certificates: ' . $e->getMessage()
    ]);
}
?>