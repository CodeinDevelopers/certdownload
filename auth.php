<?php
// auth.php - Main authentication handler
session_start();

/**
 * Load environment variables from .env file
 */
function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        $_ENV[$name] = $value;
        putenv(sprintf('%s=%s', $name, $value));
    }
}

/**
 * Check if user is authenticated for protected pages
 */
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Protect a page with password authentication
 */
function protectPage($redirectTo = 'login.php') {
    if (!isAuthenticated()) {
        // Store the original requested page
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Authenticate user with password
 */
function authenticate($password) {
    loadEnv();
    
    $correctPassword = $_ENV['PROTECTED_PAGE_PASSWORD'] ?? '';
    
    if (empty($correctPassword)) {
        throw new Exception('Password not configured in .env file');
    }
    
    if ($password === $correctPassword) {
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logout() {
    unset($_SESSION['authenticated']);
    unset($_SESSION['auth_time']);
    session_destroy();
}

/**
 * Check if authentication has expired (optional - 1 hour timeout)
 */
function checkAuthTimeout($timeoutMinutes = 60) {
    if (isAuthenticated() && isset($_SESSION['auth_time'])) {
        if ((time() - $_SESSION['auth_time']) > ($timeoutMinutes * 60)) {
            logout();
            return false;
        }
        // Update last activity time
        $_SESSION['auth_time'] = time();
    }
    return isAuthenticated();
}
?>