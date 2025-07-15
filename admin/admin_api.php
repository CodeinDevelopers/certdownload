<?php
require_once 'admin_functions.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin privileges required.'
    ]);
    exit;
}
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    switch ($action) {
        case 'get_stats':
            $stats = getUserStats();
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
        case 'get_users':
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $perPage = 20;
            $result = getUsers($page, $perPage, $search);
            echo json_encode([
                'success' => true,
                'users' => $result['users'],
                'pagination' => [
                    'page' => $result['page'],
                    'perPage' => $result['perPage'],
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages']
                ]
            ]);
            break;
        case 'get_user_files':
            $userId = (int)($_GET['user_id'] ?? 0);
            
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            $certificates = getUserCertificates($userId);
            echo json_encode([
                'success' => true,
                'files' => $certificates
            ]);
            break;
        case 'disable_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new Exception('Invalid file ID');
            }
            $result = disableFile($fileId);
            
            echo json_encode([
                'success' => $result,
                'message' => 'File disabled successfully'
            ]);
            break;
        case 'restore_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new Exception('Invalid file ID');
            }
            $result = restoreFile($fileId);
            
            echo json_encode([
                'success' => $result,
                'message' => 'File restored successfully'
            ]);
            break;
        case 'delete_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new Exception('Invalid file ID');
            }
            $result = deleteCertificate($fileId);
            
            echo json_encode([
                'success' => $result,
                'message' => 'File deleted successfully'
            ]);
            break;
        case 'renew_file_downloads':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new Exception('Invalid file ID');
            }
            $result = renewFileDownloads($fileId);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Download count renewed successfully' : 'Failed to renew download count'
            ]);
            break;
        case 'update_user_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $userId = (int)($_POST['user_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            $result = updateUserStatus($userId, $status);
            echo json_encode([
                'success' => $result,
                'message' => 'User status updated successfully'
            ]);
            break;
        case 'update_user_balance':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $userId = (int)($_POST['user_id'] ?? 0);
            $balance = (float)($_POST['balance'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            $result = updateUserBalance($userId, $balance);
            
            echo json_encode([
                'success' => $result,
                'message' => 'User balance updated successfully'
            ]);
            break;
        case 'get_user_details':
            $userId = (int)($_GET['user_id'] ?? 0);
            
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            $user = getUserById($userId);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
            break;
        case 'search_users':
            $query = $_GET['query'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 20;
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            $result = searchUsers($query, $page, $perPage);
            echo json_encode([
                'success' => true,
                'users' => $result['users'],
                'pagination' => [
                    'page' => $result['page'],
                    'perPage' => $result['perPage'],
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages']
                ],
                'query' => $result['query']
            ]);
            break;
        case 'get_recent_activity':
            $limit = (int)($_GET['limit'] ?? 50);
            $activities = getRecentActivity($limit);
            
            echo json_encode([
                'success' => true,
                'activities' => $activities
            ]);
            break;
        case 'download_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('GET method required');
            }
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception('Filename is required');
            }
            $filename = basename($filename);
            $filePath = 'certificates/' . $filename;
            if (!file_exists($filePath)) {
                throw new Exception('File not found');
            }
            $pdo = AdminDatabaseConfig::getConnection();
            $stmt = $pdo->prepare("SELECT original_filename, mime_type FROM certificates WHERE filename = ? AND deleted = 0");
            $stmt->execute([$filename]);
            $fileInfo = $stmt->fetch();
            if (!$fileInfo) {
                throw new Exception('File not found in database');
            }
            header('Content-Type: ' . $fileInfo['mime_type']);
            header('Content-Disposition: attachment; filename="' . $fileInfo['original_filename'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($filePath);
            logAdminActivity('Download File', "Downloaded file: {$fileInfo['original_filename']} ($filename)");
            exit; 
            break;
        case 'bulk_update_users':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $userIds = $_POST['user_ids'] ?? [];
            $updateType = $_POST['update_type'] ?? '';
            $updateValue = $_POST['update_value'] ?? '';
            if (empty($userIds) || !is_array($userIds)) {
                throw new Exception('User IDs array is required');
            }
            if (empty($updateType)) {
                throw new Exception('Update type is required');
            }
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            foreach ($userIds as $userId) {
                try {
                    $userId = (int)$userId;
                    if ($userId <= 0) {
                        continue;
                    }
                    switch ($updateType) {
                        case 'status':
                            $result = updateUserStatus($userId, (int)$updateValue);
                            break;
                        case 'balance':
                            $result = updateUserBalance($userId, (float)$updateValue);
                            break;
                        default:
                            throw new Exception('Invalid update type');
                    }
                    if ($result) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "User ID $userId: " . $e->getMessage();
                }
            }
            echo json_encode([
                'success' => $successCount > 0,
                'message' => "Updated $successCount users successfully" . ($errorCount > 0 ? ", $errorCount failed" : ""),
                'details' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors
                ]
            ]);
            break;
        case 'permanent_delete_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new Exception('Invalid file ID');
            }
            $result = permanentDeleteFile($fileId);
            
            echo json_encode([
                'success' => $result,
                'message' => 'File permanently deleted successfully'
            ]);
            break;
        case 'export_users':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('GET method required');
            }
            $format = $_GET['format'] ?? 'csv';
            $search = $_GET['search'] ?? '';
            $result = getUsers(1, 10000, $search);
            $users = $result['users'];
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
                $output = fopen('php://output', 'w');
                fputcsv($output, [
                    'ID', 'First Name', 'Last Name', 'Username', 'Email', 'Mobile', 
                    'Status', 'Email Verified', 'SMS Verified', 'Balance', 'Created At', 'Updated At'
                ]);
                foreach ($users as $user) {
                    fputcsv($output, [
                        $user['id'],
                        $user['firstname'],
                        $user['lastname'],
                        $user['username'],
                        $user['email'],
                        $user['mobile'],
                        $user['status'] ? 'Active' : 'Inactive',
                        $user['ev'] ? 'Yes' : 'No',
                        $user['sv'] ? 'Yes' : 'No',
                        $user['balance'],
                        $user['created_at'],
                        $user['updated_at']
                    ]);
                }
                fclose($output);
                logAdminActivity('Export Users', "Exported " . count($users) . " users to CSV");
                exit;
            } else {
                throw new Exception('Unsupported export format');
            }
            break;
        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    logAdminActivity('API Error', "Action: $action, Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>