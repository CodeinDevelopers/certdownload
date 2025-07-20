<?php
/**
 * Generate base URL for the application
 * @param string $path Optional path to append to base URL
 * @return string Complete URL
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol . $domainName . '/' . ltrim($path, '/');
}
session_start();
function loadEnv($path = './../.env') {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        $_ENV[$name] = $value;
        putenv(sprintf('%s=%s', $name, $value));
    }
}

class AdminDatabaseConfig {
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;
    
    public static function initialize() {
        loadEnv();
        self::$host = $_ENV['DB_HOST'] ?? 'localhost';
        self::$dbname = $_ENV['DB_NAME'] ?? 'your_database_name';
        self::$username = $_ENV['DB_USER'] ?? 'your_username';
        self::$password = $_ENV['DB_PASS'] ?? 'your_password';
    }
    public static function getConnection() {
        try {
            if (!self::$host) {
                self::initialize();
            }
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            return new PDO($dsn, self::$username, self::$password, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}
function isAdminAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && 
           $_SESSION['admin_logged_in'] === true && 
           isset($_SESSION['admin_id']);
}
function protectAdminPage($redirectTo = 'admin_login') {
    if (!isAdminAuthenticated()) {
        $_SESSION['admin_redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Authenticate admin with email and password
 * @param string $email - Admin email
 * @param string $password - Plain text password
 * @return array|false - Admin data on success, false on failure
 */
function authenticateAdmin($email, $password) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        
        // Get admin by email
        $stmt = $pdo->prepare("SELECT id, name, email, username, password, access, image, created_at, updated_at FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && verifyAdminPassword($password, $admin['password'])) {
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE admins SET updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$admin['id']]);
            
            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_access'] = $admin['access'];
            $_SESSION['admin_image'] = $admin['image'];
            $_SESSION['admin_auth_time'] = time();
            unset($admin['password']);
            return $admin;
        }
        return false;
    } catch (Exception $e) {
        throw new Exception("Admin authentication error: " . $e->getMessage());
    }
}

/**
 * Hash password using Laravel-compatible method
 * @param string $password - Plain text password
 * @return string - Hashed password
 */
function hashAdminPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash (Laravel-compatible)
 * @param string $password - Plain text password
 * @param string $hash - Hashed password from database
 * @return bool - True if password matches, false otherwise
 */
function verifyAdminPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Change admin password
 * @param int $adminId - Admin ID
 * @param string $newPassword - New plain text password
 * @return bool - True on success, false on failure
 */
function changeAdminPassword($adminId, $newPassword) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $hashedPassword = hashAdminPassword($newPassword);
        
        $stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $adminId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        throw new Exception("Admin password change error: " . $e->getMessage());
    }
}
/**
 * Validate admin login credentials
 * @param string $email - Admin email
 * @param string $password - Plain text password
 * @return bool - True if credentials are valid, false otherwise
 */
function validateAdminLogin($email, $password) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && verifyAdminPassword($password, $admin['password'])) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function getCurrentAdmin() {
    if (!isAdminAuthenticated()) {
        return null;
    }
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, username, access, image, email_verified_at, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
function getAdminById($adminId) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, username, access, image, email_verified_at, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
function adminExistsByEmail($email) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}
function adminExistsByUsername($username) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}
function createAdmin($name, $email, $username, $password, $access = null, $image = null) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        
        if (adminExistsByEmail($email)) {
            throw new Exception("Admin with this email already exists");
        }
        if (adminExistsByUsername($username)) {
            throw new Exception("Admin with this username already exists");
        }
        
        $hashedPassword = hashAdminPassword($password);
        $stmt = $pdo->prepare("INSERT INTO admins (name, email, username, password, access, image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $email, $username, $hashedPassword, $access, $image]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw new Exception("Admin creation error: " . $e->getMessage());
    }
}
function updateAdmin($adminId, $data) {
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        
        $allowedFields = ['name', 'email', 'username', 'access', 'image'];
        $updateFields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $values[] = $value;
            }
        }
        if (empty($updateFields)) {
            throw new Exception("No valid fields to update");
        }
        $values[] = $adminId;
        $sql = "UPDATE admins SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        throw new Exception("Admin update error: " . $e->getMessage());
    }
}
function adminLogout() {
    $keysToUnset = ['admin_logged_in', 'admin_id', 'admin_name', 'admin_email', 'admin_username', 'admin_access', 'admin_image', 'admin_auth_time'];
    
    foreach ($keysToUnset as $key) {
        unset($_SESSION[$key]);
    }
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
       setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}
function checkAdminAuthTimeout($timeoutMinutes = 120) { 
    if (isAdminAuthenticated() && isset($_SESSION['admin_auth_time'])) {
        if ((time() - $_SESSION['admin_auth_time']) > ($timeoutMinutes * 60)) {
            adminLogout();
            return false;
        }
        $_SESSION['admin_auth_time'] = time();
    }
    return isAdminAuthenticated();
}
function getAdminAccess() {
    if (!isAdminAuthenticated()) {
        return null;
    }
    return $_SESSION['admin_access'] ?? null;
}
function hasAdminAccess($requiredAccess) {
    if (!isAdminAuthenticated()) {
        return false;
    }
    $adminAccess = getAdminAccess();
    if (!$adminAccess) {
        return false;
    }
    if (is_string($adminAccess)) {
        $accessArray = json_decode($adminAccess, true);
        return is_array($accessArray) && in_array($requiredAccess, $accessArray);
    }
    return false;
}
function getAdminDisplayName() {
    if (!isAdminAuthenticated()) {
        return null;
    }
    return $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
}
function logAdminActivity($activity, $details = null) {
    if (!isAdminAuthenticated()) {
        return false;
    }
    try {
        $pdo = AdminDatabaseConfig::getConnection();
        $createTable = "CREATE TABLE IF NOT EXISTS admin_activity_log (
            id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id bigint UNSIGNED NOT NULL,
            activity varchar(255) NOT NULL,
            details text,
            ip_address varchar(45),
            user_agent text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_id (admin_id),
            INDEX idx_created_at (created_at)
        )";
        $pdo->exec($createTable);
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, activity, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            $activity,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Admin activity logging error: " . $e->getMessage());
        return false;
    }
}
function authenticateAdminLegacy($email, $username, $password) {
    throw new Exception("authenticateAdminLegacy() function is deprecated. Use authenticateAdmin(\$email, \$password) instead.");
}
?>