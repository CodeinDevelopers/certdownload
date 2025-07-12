<?php
require_once 'auth.php';
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
    $pdo = DatabaseConfig::getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            id,
            imei,
            filename,
            original_filename,
            file_size,
            mime_type,
            download_count,
            max_downloads,
            created_at,
            updated_at
        FROM certificates 
        WHERE user_id = ? AND deleted = 0 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$currentUser['id']]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response = [
        'success' => true,
        'certificates' => $certificates,
        'count' => count($certificates)
    ];
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching certificates: ' . $e->getMessage()
    ]);
}
?>