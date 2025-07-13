<?php
require_once 'admin_auth.php';

function isAdmin() {
    return isAdminAuthenticated();
}
function protectAdminPageWithTimeout($redirectTo = 'admin_login.php') {
    if (!isAdminAuthenticated()) {
        $_SESSION['admin_redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectTo");
        exit();
    }
    if (!checkAdminAuthTimeout()) {
        header("Location: $redirectTo");
        exit();
    }
}

function getUsers($page = 1, $perPage = 20, $search = '') {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $offset = ($page - 1) * $perPage;
        
        $whereClause = '';
        $params = [];
        if (!empty($search)) {
            $whereClause = "WHERE (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR mobile LIKE ?)";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalUsers = $countStmt->fetch()['total'];
        $sql = "SELECT id, firstname, lastname, username, email, mobile, status, ev, sv, balance, created_at, updated_at 
                FROM users $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        logAdminActivity('View Users', "Page: $page, Search: $search");
        return [
            'users' => $users,
            'total' => $totalUsers,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($totalUsers / $perPage)
        ];
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to fetch users: " . $e->getMessage());
        throw new Exception("Error fetching users: " . $e->getMessage());
    }
}

function getUserCertificates($userId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $sql = "SELECT id, imei, filename, original_filename, file_path, file_size, mime_type, 
                       download_count, max_downloads, created_at, updated_at 
                FROM certificates 
                WHERE user_id = ? AND deleted = 0 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $certificates = $stmt->fetchAll();
        
        logAdminActivity('View User Certificates', "User ID: $userId");
        return $certificates;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to fetch certificates for user $userId: " . $e->getMessage());
        throw new Exception("Error fetching certificates: " . $e->getMessage());
    }
}

function getUserStats() {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stats = [];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE status = 1");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE status = 0");
        $stmt->execute();
        $stats['inactive_users'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates WHERE deleted = 0");
        $stmt->execute();
        $stats['total_certificates'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as total FROM certificates WHERE deleted = 0");
        $stmt->execute();
        $stats['users_with_certificates'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE ev = 1");
        $stmt->execute();
        $stats['email_verified'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE sv = 1");
        $stmt->execute();
        $stats['sms_verified'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $stats['recent_registrations'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT SUM(download_count) as total FROM certificates WHERE deleted = 0");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_downloads'] = $result['total'] ?? 0;
        logAdminActivity('View Dashboard Stats', 'Dashboard statistics accessed');
        return $stats;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to fetch stats: " . $e->getMessage());
        throw new Exception("Error fetching stats: " . $e->getMessage());
    }
}

function deleteCertificate($certificateId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT file_path, original_filename, user_id FROM certificates WHERE id = ? AND deleted = 0");
        $stmt->execute([$certificateId]);
        $certificate = $stmt->fetch();
        if (!$certificate) {
            throw new Exception("Certificate not found");
        }
        $updateStmt = $pdo->prepare("UPDATE certificates SET deleted = 1, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$certificateId]);
        if (file_exists($certificate['file_path'])) {
            unlink($certificate['file_path']);
        }
        logAdminActivity('Delete Certificate', "Certificate ID: $certificateId, File: {$certificate['original_filename']}, User ID: {$certificate['user_id']}");
        return true;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to delete certificate $certificateId: " . $e->getMessage());
        throw new Exception("Error deleting certificate: " . $e->getMessage());
    }
}

function updateUserStatus($userId, $status) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $userStmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $userId]);
        $result = $stmt->rowCount() > 0;
        if ($result && $user) {
            $statusText = $status ? 'Activated' : 'Deactivated';
            logAdminActivity('Update User Status', "User: {$user['email']} ({$user['firstname']} {$user['lastname']}) - Status: $statusText");
        }
        return $result;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to update user status for user $userId: " . $e->getMessage());
        throw new Exception("Error updating user status: " . $e->getMessage());
    }
}

function getUserById($userId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            logAdminActivity('View User Details', "User ID: $userId, Email: {$user['email']}");
        }
        return $user;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to fetch user $userId: " . $e->getMessage());
        throw new Exception("Error fetching user: " . $e->getMessage());
    }
}

function updateUserBalance($userId, $balance) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
    
        $userStmt = $pdo->prepare("SELECT email, firstname, lastname, balance as old_balance FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $stmt = $pdo->prepare("UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$balance, $userId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result && $user) {
            logAdminActivity('Update User Balance', "User: {$user['email']} - Balance updated from {$user['old_balance']} to $balance");
        }
        return $result;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to update balance for user $userId: " . $e->getMessage());
        throw new Exception("Error updating user balance: " . $e->getMessage());
    }
}

function getRecentActivity($limit = 50) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $sql = "SELECT * FROM admin_activity_log 
                ORDER BY created_at DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function searchUsers($query, $page = 1, $perPage = 20) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $offset = ($page - 1) * $perPage;
        $searchParam = "%$query%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
        $countSql = "SELECT COUNT(*) as total FROM users 
                     WHERE (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR mobile LIKE ? OR username LIKE ?)";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalUsers = $countStmt->fetch()['total'];
        $sql = "SELECT id, firstname, lastname, username, email, mobile, status, ev, sv, balance, created_at, updated_at 
                FROM users 
                WHERE (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR mobile LIKE ? OR username LIKE ?)
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        logAdminActivity('Search Users', "Query: $query, Results: $totalUsers");
        return [
            'users' => $users,
            'total' => $totalUsers,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($totalUsers / $perPage),
            'query' => $query
        ];
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to search users with query '$query': " . $e->getMessage());
        throw new Exception("Error searching users: " . $e->getMessage());
    }
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getFileIcon($mimeType) {
    switch ($mimeType) {
        case 'application/pdf':
            return '📄';
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/png':
        case 'image/gif':
            return '🖼️';
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return '📝';
        case 'application/vnd.ms-excel':
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            return '📊';
        case 'application/zip':
        case 'application/x-rar-compressed':
            return '📦';
        default:
            return '📎';
    }
}

function formatDate($date) {
    return date('M j, Y \a\t g:i A', strtotime($date));
}

function getStatusBadge($status) {
    return $status ? 
        '<span class="badge bg-success">Active</span>' : 
        '<span class="badge bg-danger">Inactive</span>';
}

function getVerificationBadge($verified) {
    return $verified ? 
        '<span class="badge bg-success">✓</span>' : 
        '<span class="badge bg-secondary">✗</span>';
}

function requireAdminAccess($access) {
    if (!hasAdminAccess($access)) {
        logAdminActivity('Access Denied', "Attempted to access restricted area: $access");
        header("HTTP/1.0 403 Forbidden");
        die("Access denied. You don't have permission to access this resource.");
    }
}

function getAdminNavigation() {
    $nav = [
        'dashboard' => [
            'title' => 'Dashboard',
            'url' => 'index.php',
            'icon' => '🏠',
            'access' => 'dashboard'
        ],
        'users' => [
            'title' => 'Users',
            'url' => 'admin_users.php',
            'icon' => '👥',
            'access' => 'users'
        ],
        'certificates' => [
            'title' => 'Certificates',
            'url' => 'admin_certificates.php',
            'icon' => '📄',
            'access' => 'certificates'
        ],
        'reports' => [
            'title' => 'Reports',
            'url' => 'admin_reports.php',
            'icon' => '📊',
            'access' => 'reports'
        ],
        'settings' => [
            'title' => 'Settings',
            'url' => 'admin_settings.php',
            'icon' => '⚙️',
            'access' => 'settings'
        ]
    ];
    $filteredNav = [];
    foreach ($nav as $key => $item) {
        if (hasAdminAccess($item['access'])) {
            $filteredNav[$key] = $item;
        }
    }
    return $filteredNav;
}
?>