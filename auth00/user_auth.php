<?php
session_start();
function loadEnv($path = '.env') {
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
class DatabaseConfig {
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
function isAuthenticated() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}
function protectPage($redirectTo = 'login.php') {
    if (!isAuthenticated()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Authenticate user with mobile number and password
 * @param string $mobile - Mobile number
 * @param string $password - Plain text password
 * @return array|false - User data on success, false on failure
 */
function authenticateUser($mobile, $password) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, username, email, mobile, password, status, ev, sv, balance FROM users WHERE mobile = ? AND status = 1");
        $stmt->execute([$mobile]);
        $user = $stmt->fetch();
        if ($user && verifyPassword($password, $user['password'])) {
            $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['mobile'] = $user['mobile'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['balance'] = $user['balance'];
            $_SESSION['auth_time'] = time();
            unset($user['password']);
            return $user;
        }
        return false;
    } catch (Exception $e) {
        throw new Exception("Authentication error: " . $e->getMessage());
    }
}
/**
 * Hash password using Laravel-compatible method
 * @param string $password - Plain text password
 * @return string - Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
/**
 * Verify password against hash (Laravel-compatible)
 * @param string $password - Plain text password
 * @param string $hash - Hashed password from database
 * @return bool - True if password matches, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
/**
 * Change user password
 * @param int $userId - User ID
 * @param string $newPassword - New plain text password
 * @return bool - True on success, false on failure
 */
function changePassword($userId, $newPassword) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $hashedPassword = hashPassword($newPassword);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        throw new Exception("Password change error: " . $e->getMessage());
    }
}
/**
 * Validate login credentials
 * @param string $mobile - Mobile number
 * @param string $password - Plain text password
 * @return bool - True if credentials are valid, false otherwise
 */
function validateLogin($mobile, $password) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE mobile = ? AND status = 1");
        $stmt->execute([$mobile]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, username, email, mobile, status, ev, sv, balance, created_at, updated_at FROM users WHERE id = ? AND status = 1");
        $stmt->execute([$_SESSION['user_id']]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
function getUserById($userId) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, username, email, mobile, status, ev, sv, balance, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
function userExistsByMobile($mobile) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}
function userExistsByEmail($email) {
    try {
        $pdo = DatabaseConfig::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}
function createUser($firstname, $lastname, $username, $email, $mobile, $password, $country_name = null, $dial_code = null) {
    try {
        $pdo = DatabaseConfig::getConnection();
        if (userExistsByMobile($mobile)) {
            throw new Exception("Mobile number already exists");
        }
        if (userExistsByEmail($email)) {
            throw new Exception("Email already exists");
        }
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, username, email, mobile, password, country_name, dial_code, status, ev, sv, profile_complete, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 0, NOW(), NOW())");
        $stmt->execute([$firstname, $lastname, $username, $email, $mobile, $hashedPassword, $country_name, $dial_code]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        throw new Exception("User creation error: " . $e->getMessage());
    }
}
function updateUser($userId, $data) {
    try {
        $pdo = DatabaseConfig::getConnection();
        
        $allowedFields = ['firstname', 'lastname', 'username', 'email', 'mobile', 'country_name', 'dial_code', 'city', 'state', 'zip', 'country_code', 'address'];
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
        $values[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        throw new Exception("User update error: " . $e->getMessage());
    }
}
function logout() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}
function checkAuthTimeout($timeoutMinutes = 60) {
    if (isAuthenticated() && isset($_SESSION['auth_time'])) {
        if ((time() - $_SESSION['auth_time']) > ($timeoutMinutes * 60)) {
            logout();
            return false;
        }
        $_SESSION['auth_time'] = time();
    }
    return isAuthenticated();
}
function getUserFullName() {
    if (!isAuthenticated()) {
        return null;
    }
    
    $firstname = $_SESSION['firstname'] ?? '';
    $lastname = $_SESSION['lastname'] ?? '';
    
    return trim($firstname . ' ' . $lastname);
}
function isEmailVerified() {
    if (!isAuthenticated()) {
        return false;
    }
    $user = getCurrentUser();
    return $user && $user['ev'] == 1;
}
function isSMSVerified() {
    if (!isAuthenticated()) {
        return false;
    }
    $user = getCurrentUser();
    return $user && $user['sv'] == 1;
}
function getUserBalance() {
    if (!isAuthenticated()) {
        return 0;
    }
    
    $user = getCurrentUser();
    return $user ? $user['balance'] : 0;
}
function authenticate($password) {
    throw new Exception("authenticate() function is deprecated. Use authenticateUser(\$mobile, \$password) instead.");
}
?>