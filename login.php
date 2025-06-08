<?php
// login.php - Login form and handler with lockout mechanism
require_once 'auth.php';

$error = '';
$success = '';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session variables for lockout system
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}
if (!isset($_SESSION['lockout_time'])) {
    $_SESSION['lockout_time'] = null;
}

// Function to check if user is locked out
function isLockedOut() {
    if ($_SESSION['failed_attempts'] >= 3 && $_SESSION['lockout_time'] !== null) {
        $lockoutDuration = 10 * 60; // 10 minutes in seconds
        $currentTime = time();
        
        // Check if lockout period has expired
        if (($currentTime - $_SESSION['lockout_time']) >= $lockoutDuration) {
            // Reset failed attempts and lockout time
            $_SESSION['failed_attempts'] = 0;
            $_SESSION['lockout_time'] = null;
            return false;
        }
        
        return true;
    }
    
    return false;
}

// Function to get remaining lockout time
function getRemainingLockoutTime() {
    if ($_SESSION['lockout_time'] !== null) {
        $lockoutDuration = 10 * 60; // 10 minutes in seconds
        $elapsed = time() - $_SESSION['lockout_time'];
        $remaining = $lockoutDuration - $elapsed;
        
        if ($remaining > 0) {
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            return sprintf("%d:%02d", $minutes, $seconds);
        }
    }
    return "0:00";
}

// Check current lockout status
$currentlyLockedOut = isLockedOut();
$remainingTime = $currentlyLockedOut ? getRemainingLockoutTime() : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is locked out
    if ($currentlyLockedOut) {
        $error = "Account locked due to multiple failed attempts. Please try again in $remainingTime.";
    } else {
        $password = $_POST['password'] ?? '';
        
        try {
            if (authenticate($password)) {
                // Reset failed attempts on successful login
                $_SESSION['failed_attempts'] = 0;
                $_SESSION['lockout_time'] = null;
                
                $success = 'Authentication successful!';
                
                // Redirect to originally requested page or default
                $redirectTo = $_SESSION['redirect_after_login'] ?? 'upload-file.php';
                unset($_SESSION['redirect_after_login']);
                
                header("Location: $redirectTo");
                exit();
            } else {
                // Increment failed attempts
                $_SESSION['failed_attempts']++;
                
                // Set lockout time if this is the 3rd failed attempt
                if ($_SESSION['failed_attempts'] >= 3) {
                    $_SESSION['lockout_time'] = time();
                    $error = 'Account locked due to 3 failed login attempts. Please try again in 10 minutes.';
                    $currentlyLockedOut = true;
                    $remainingTime = "10:00";
                } else {
                    $remainingAttempts = 3 - $_SESSION['failed_attempts'];
                    $error = "Invalid password. $remainingAttempts attempt(s) remaining before lockout.";
                }
            }
        } catch (Exception $e) {
            // Increment failed attempts for exceptions too
            $_SESSION['failed_attempts']++;
            
            if ($_SESSION['failed_attempts'] >= 3) {
                $_SESSION['lockout_time'] = time();
                $error = 'Account locked due to multiple failed attempts. Please try again in 10 minutes.';
                $currentlyLockedOut = true;
                $remainingTime = "10:00";
            } else {
                $error = 'Authentication error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protected Area - Login Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input[type="password"]:disabled {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #c33;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #3c3;
        }
        
        .lockout-info {
            background: #fff3cd;
            color: #856404;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #ffc107;
            text-align: center;
        }
        
        .lockout-timer {
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
        }
        
        .attempts-info {
            font-size: 0.9rem;
            color: #666;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
    
    <?php if ($currentlyLockedOut): ?>
    <script>
        // Auto-refresh countdown timer for lockout
        let remainingSeconds = <?php 
            $lockoutDuration = 10 * 60;
            $elapsed = time() - $_SESSION['lockout_time'];
            $remaining = $lockoutDuration - $elapsed;
            echo max(0, $remaining);
        ?>;
        
        function updateTimer() {
            if (remainingSeconds <= 0) {
                location.reload();
                return;
            }
            
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            const timerElement = document.getElementById('lockout-timer');
            
            if (timerElement) {
                timerElement.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
            
            remainingSeconds--;
        }
        
        setInterval(updateTimer, 1000);
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>🔒 Protected Area</h2>
            <p>Please enter the password to continue</p>
        </div>
        
        <?php if ($currentlyLockedOut): ?>
            <div class="lockout-info">
                <strong>Account Locked</strong><br>
                Too many failed attempts. Please wait:<br>
                <span class="lockout-timer" id="lockout-timer"><?php echo $remainingTime; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!$currentlyLockedOut && $_SESSION['failed_attempts'] > 0): ?>
            <div class="attempts-info">
                Failed attempts: <?php echo $_SESSION['failed_attempts']; ?>/3
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autofocus
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            
            <button type="submit" 
                    class="btn" 
                    <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                <?php echo $currentlyLockedOut ? 'Account Locked' : 'Access Protected Area'; ?>
            </button>
        </form>
    </div>
</body>
</html>