<?php
/**
 * Get all post titles for a specific user
 * @param int $userId - User ID to get posts for
 * @param bool $activeOnly - Whether to get only active posts (status = 1), default true
 * @return array - Array of post titles with id and title, or empty array if none found
 */
function getUserPostTitles($userId, $activeOnly = true) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $sql = "SELECT id, title FROM ad_lists WHERE user_id = ? AND deleted_at IS NULL";
        $params = [$userId];
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        throw new Exception("Error fetching user post titles: " . $e->getMessage());
    }
}
/**
 * Get post titles for the currently authenticated user
 * @param bool $activeOnly - Whether to get only active posts (status = 1), default true
 * @return array - Array of post titles with id and title, or empty array if none found
 */
function getCurrentUserPostTitles($activeOnly = true) {
    if (!isAuthenticated()) {
        throw new Exception("User not authenticated");
    }
    
    return getUserPostTitles($_SESSION['user_id'], $activeOnly);
}

/**
 * Get post titles formatted for dropdown selection
 * @param int $userId - User ID to get posts for (optional, uses current user if not provided)
 * @param bool $activeOnly - Whether to get only active posts (status = 1), default true
 * @return array - Array formatted for dropdown with value and text keys
 */
function getPostTitlesForDropdown($userId = null, $activeOnly = true) {
    try {
        if ($userId === null) {
            if (!isAuthenticated()) {
                throw new Exception("User not authenticated");
            }
            $userId = $_SESSION['user_id'];
        }
        
        $posts = getUserPostTitles($userId, $activeOnly);
        $dropdown = [];
        
        foreach ($posts as $post) {
            $dropdown[] = [
                'value' => $post['id'],
                'text' => $post['title']
            ];
        }
        
        return $dropdown;
    } catch (Exception $e) {
        throw new Exception("Error formatting post titles for dropdown: " . $e->getMessage());
    }
}
/**
 * Get count of posts for a user
 * @param int $userId - User ID to count posts for
 * @param bool $activeOnly - Whether to count only active posts (status = 1), default true
 * @return int - Number of posts
 */
function getUserPostCount($userId, $activeOnly = true) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $sql = "SELECT COUNT(*) as count FROM ad_lists WHERE user_id = ? AND deleted_at IS NULL";
        $params = [$userId];
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        throw new Exception("Error counting user posts: " . $e->getMessage());
    }
}
?>