<?php
require_once 'admin_auth.php';
function isAdmin() {
    return isAdminAuthenticated();
}
function protectAdminPageWithTimeout($redirectTo = 'admin_login') {
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
            $whereClause = "WHERE (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        $countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalUsers = $countStmt->fetch()['total'];
        $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.mobile, 
                       u.status, u.ev, u.sv, u.balance, u.created_at, u.updated_at,
                       COALESCE(c.file_count, 0) as file_count,
                       COALESCE(c.total_file_size, 0) as total_file_size,
                       COALESCE(c.active_file_count, 0) as active_file_count,
                       COALESCE(c.disabled_file_count, 0) as disabled_file_count
                FROM users u
                LEFT JOIN (
                    SELECT user_id, 
                           COUNT(*) as file_count,
                           SUM(file_size) as total_file_size,
                           SUM(CASE WHEN deleted = 0 THEN 1 ELSE 0 END) as active_file_count,
                           SUM(CASE WHEN deleted = 1 THEN 1 ELSE 0 END) as disabled_file_count
                    FROM certificates 
                    GROUP BY user_id
                ) c ON u.id = c.user_id
                $whereClause 
                ORDER BY u.created_at DESC 
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
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates");
        $stmt->execute();
        $stats['total_certificates'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates WHERE deleted = 0");
        $stmt->execute();
        $stats['active_certificates'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates WHERE deleted = 1");
        $stmt->execute();
        $stats['disabled_certificates'] = $stmt->fetch()['total'];
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as total FROM certificates");
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
        $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.mobile, 
                       u.status, u.ev, u.sv, u.balance, u.created_at, u.updated_at,
                       COALESCE(c.file_count, 0) as file_count,
                       COALESCE(c.total_file_size, 0) as total_file_size,
                       COALESCE(c.active_file_count, 0) as active_file_count,
                       COALESCE(c.disabled_file_count, 0) as disabled_file_count
                FROM users u
                LEFT JOIN (
                    SELECT user_id, 
                           COUNT(*) as file_count,
                           SUM(file_size) as total_file_size,
                           SUM(CASE WHEN deleted = 0 THEN 1 ELSE 0 END) as active_file_count,
                           SUM(CASE WHEN deleted = 1 THEN 1 ELSE 0 END) as disabled_file_count
                    FROM certificates 
                    GROUP BY user_id
                ) c ON u.id = c.user_id
                WHERE (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.username LIKE ?)
                ORDER BY u.created_at DESC 
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
/**
 * Disable a file by marking it as deleted in the database
 * @param int $fileId The ID of the file to disable
 * @return bool True if successful, false otherwise
 */
function disableFile($fileId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT original_filename, user_id, deleted FROM certificates WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            throw new Exception("File not found");
        }
        
        if ($file['deleted'] == 1) {
            throw new Exception("File is already disabled");
        }
        $updateStmt = $pdo->prepare("UPDATE certificates SET deleted = 1, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$fileId]);
        
        $result = $updateStmt->rowCount() > 0;
        
        if ($result) {
            logAdminActivity('Disable File', "File ID: $fileId, File: {$file['original_filename']}, User ID: {$file['user_id']}");
        }
        return $result;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to disable file $fileId: " . $e->getMessage());
        throw new Exception("Error disabling file: " . $e->getMessage());
    }
}

/**
 * Restore a file by marking it as not deleted in the database
 * @param int $fileId The ID of the file to restore
 * @return bool True if successful, false otherwise
 */
function restoreFile($fileId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT original_filename, user_id, deleted FROM certificates WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            throw new Exception("File not found");
        }
        
        if ($file['deleted'] == 0) {
            throw new Exception("File is already active");
        }
        $updateStmt = $pdo->prepare("UPDATE certificates SET deleted = 0, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$fileId]);
        $result = $updateStmt->rowCount() > 0;
        
        if ($result) {
            logAdminActivity('Restore File', "File ID: $fileId, File: {$file['original_filename']}, User ID: {$file['user_id']}");
        }
        return $result;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to restore file $fileId: " . $e->getMessage());
        throw new Exception("Error restoring file: " . $e->getMessage());
    }
}

/**
 * @param int $userId The user ID
 * @return array Array of certificates
 */
function getUserCertificates($userId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $sql = "SELECT id, imei, filename, original_filename, file_path, file_size, mime_type, 
                       download_count, max_downloads, deleted, created_at, updated_at 
                FROM certificates 
                WHERE user_id = ? 
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
/**
 * Renew file downloads by resetting download count to 0
 * @param int $fileId The ID of the file to renew
 * @return bool True if successful, false otherwise
 */
function renewFileDownloads($fileId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT original_filename, user_id, deleted, download_count, max_downloads FROM certificates WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            throw new Exception("File not found");
        }
        if ($file['deleted'] == 1) {
            throw new Exception("Cannot renew downloads for a disabled file");
        }
        $userStmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
        $userStmt->execute([$file['user_id']]);
        $user = $userStmt->fetch();
        $updateStmt = $pdo->prepare("UPDATE certificates SET download_count = 0, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$fileId]);
        $result = $updateStmt->rowCount() > 0;
        if ($result) {
            $userName = $user ? $user['firstname'] . ' ' . $user['lastname'] : 'Unknown User';
            $userEmail = $user ? $user['email'] : 'N/A';
            logAdminActivity('Renew File Downloads', 
                "File ID: $fileId, File: {$file['original_filename']}, " .
                "User: $userName ($userEmail), " .
                "Previous downloads: {$file['download_count']}/{$file['max_downloads']}, " .
                "Reset to: 0/{$file['max_downloads']}"
            );
            $logEntry = date('Y-m-d H:i:s') . " - ADMIN RENEW DOWNLOADS: " .
                "File ID: $fileId - {$file['original_filename']} - " .
                "User: $userName ($userEmail) - " .
                "Downloads reset from {$file['download_count']} to 0 (max: {$file['max_downloads']})\n";
            if (!is_dir('./logs')) {
                mkdir('./logs', 0755, true);
            }
            file_put_contents('./logs/admin_renew_downloads_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        }
        return $result;
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to renew downloads for file $fileId: " . $e->getMessage());
        throw new Exception("Error renewing file downloads: " . $e->getMessage());
    }
}
/**
 * Permanently delete a file - removes from both database and storage
 * @param int $fileId The ID of the file to permanently delete
 * @return bool True if successful, false otherwise
 */
/**
 * Permanently delete a file - removes from both database and storage
 * @param int $fileId The ID of the file to permanently delete
 * @return bool True if successful, false otherwise
 */
function permanentDeleteFile($fileId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, filename, original_filename, file_path, imei, user_id, deleted, created_at, updated_at FROM certificates WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            throw new Exception("File not found");
        }
        
        $logData = [
            'id' => $file['id'],
            'filename' => $file['filename'],
            'original_filename' => $file['original_filename'],
            'file_path' => $file['file_path'],
            'imei' => $file['imei'],
            'user_id' => $file['user_id'],
            'was_soft_deleted' => $file['deleted'] == 1,
            'created_at' => $file['created_at'],
            'updated_at' => $file['updated_at']
        ];
        
        $userStmt = $pdo->prepare("SELECT firstname, lastname, mobile FROM users WHERE id = ?");
        $userStmt->execute([$file['user_id']]);
        $user = $userStmt->fetch();
        
        $pdo->beginTransaction();
        
        try {
            $fileDeleted = false;
            $fileDeleteError = '';
            if (file_exists($file['file_path'])) {
                $fileInfo = [
                    'exists' => true,
                    'is_file' => is_file($file['file_path']),
                    'is_readable' => is_readable($file['file_path']),
                    'is_writable' => is_writable($file['file_path']),
                    'file_size' => filesize($file['file_path']),
                    'file_perms' => substr(sprintf('%o', fileperms($file['file_path'])), -4),
                    'parent_dir_writable' => is_writable(dirname($file['file_path'])),
                    'parent_dir_perms' => substr(sprintf('%o', fileperms(dirname($file['file_path']))), -4)
                ];
                error_log("File deletion attempt - File info: " . json_encode($fileInfo) . " - Path: {$file['file_path']}");

                if (!is_writable($file['file_path'])) {
                    $fileDeleteError = "File is not writable. File perms: {$fileInfo['file_perms']}, Parent dir perms: {$fileInfo['parent_dir_perms']}";
                    error_log("File deletion failed: " . $fileDeleteError);
                    throw new Exception('Failed to delete physical file: ' . $fileDeleteError);
                }
                if (!is_writable(dirname($file['file_path']))) {
                    $fileDeleteError = "Parent directory is not writable. Dir perms: {$fileInfo['parent_dir_perms']}";
                    error_log("File deletion failed: " . $fileDeleteError);
                    throw new Exception('Failed to delete physical file: ' . $fileDeleteError);
                }
                error_clear_last();
                if (unlink($file['file_path'])) {
                    $fileDeleted = true;
                    error_log("File successfully deleted: {$file['file_path']}");
                } else {
                    $lastError = error_get_last();
                    $fileDeleteError = $lastError ? $lastError['message'] : 'Unknown error during unlink()';
                    error_log("unlink() failed: " . $fileDeleteError . " - Path: {$file['file_path']}");
                    throw new Exception('Failed to delete physical file: ' . $fileDeleteError);
                }
            } else {
                error_log("Warning: Physical file not found during permanent delete: {$file['file_path']}");
                $fileDeleted = false;
                $fileDeleteError = 'File does not exist';
            }
            $deleteStmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
            $deleteStmt->execute([$fileId]);
            if ($deleteStmt->rowCount() === 0) {
                throw new Exception('Failed to delete file from database');
            }
            $verifyStmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificates WHERE id = ?");
            $verifyStmt->execute([$fileId]);
            $verifyResult = $verifyStmt->fetch();
            if ($verifyResult['count'] > 0) {
                throw new Exception('Database deletion verification failed - record still exists');
            }
            $pdo->commit();
            $userName = $user ? $user['firstname'] . ' ' . $user['lastname'] : 'Unknown User';
            $userMobile = $user ? $user['mobile'] : 'N/A';
            $logEntry = date('Y-m-d H:i:s') . " - ADMIN PERMANENT DELETE: ID: {$logData['id']} - {$logData['filename']} (Original: {$logData['original_filename']}) for User ID: {$logData['user_id']} ({$userName}) - Mobile: {$userMobile} - IMEI: {$logData['imei']} - File Path: {$logData['file_path']} - Created: {$logData['created_at']} - Was Soft Deleted: " . ($logData['was_soft_deleted'] ? 'Yes' : 'No') . " - Physical File Deleted: " . ($fileDeleted ? 'Yes' : 'No');
            if (!$fileDeleted && $fileDeleteError) {
                $logEntry .= " - Error: " . $fileDeleteError;
            }
            $logEntry .= " - Admin Action\n";
            if (!is_dir('./logs')) {
                mkdir('./logs', 0755, true);
            }
            file_put_contents('./logs/admin_permanent_deletion_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
            logAdminActivity('Permanent Delete File', "File ID: {$fileId}, File: {$logData['original_filename']}, User ID: {$logData['user_id']}, Physical File Deleted: " . ($fileDeleted ? 'Yes' : 'No') . ($fileDeleteError ? ', Error: ' . $fileDeleteError : ''));
            
            return true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        logAdminActivity('Error', "Failed to permanently delete file $fileId: " . $e->getMessage());
        throw new Exception("Error permanently deleting file: " . $e->getMessage());
    }
}
?>