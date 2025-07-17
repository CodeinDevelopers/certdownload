<?php
require_once 'auth00/user_auth.php';
require_once 'posts.php';

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
    
    // Updated query with proper JOIN to get post title
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.user_id,
            c.post_id,
            c.filename,
            c.original_filename,
            c.file_path,
            c.file_size,
            c.mime_type,
            c.download_count,
            c.max_downloads,
            c.device_identifier,
            c.imei,
            c.vin_number,
            c.serial_number,
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
    
    // Debug logging
    error_log("Retrieved certificates for user {$currentUser['id']}: " . count($certificates));
    
    if (!empty($certificates)) {
        foreach ($certificates as $index => $cert) {
            error_log("Certificate {$index}: ID={$cert['id']}, Post ID={$cert['post_id']}, Post Title='{$cert['post_title']}'");
            
            // Ensure all required fields are present
            if (empty($cert['post_title'])) {
                // Try to get post title separately if JOIN failed
                $postStmt = $pdo->prepare("SELECT title FROM ad_lists WHERE id = ?");
                $postStmt->execute([$cert['post_id']]);
                $postData = $postStmt->fetch();
                
                if ($postData) {
                    $certificates[$index]['post_title'] = $postData['title'];
                    error_log("Fixed post title for certificate {$cert['id']}: '{$postData['title']}'");
                } else {
                    error_log("Could not find post with ID {$cert['post_id']} for certificate {$cert['id']}");
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'certificates' => $certificates
    ]);

} catch (Exception $e) {
    error_log("Error loading certificates: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load certificates: ' . $e->getMessage()
    ]);
}
?>