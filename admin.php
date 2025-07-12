<?php
require_once 'admin_auth.php';

$error = '';
$success = '';

// Initialize failed attempts tracking
if (!isset($_SESSION['admin_failed_attempts'])) {
    $_SESSION['admin_failed_attempts'] = 0;
}
if (!isset($_SESSION['admin_lockout_time'])) {
    $_SESSION['admin_lockout_time'] = null;
}

function isAdminLockedOut() {
    if ($_SESSION['admin_failed_attempts'] >= 3 && $_SESSION['admin_lockout_time'] !== null) {
        $lockoutDuration = 15 * 60; // 15 minutes lockout for admin
        $currentTime = time();
        if (($currentTime - $_SESSION['admin_lockout_time']) >= $lockoutDuration) {
            $_SESSION['admin_failed_attempts'] = 0;
            $_SESSION['admin_lockout_time'] = null;
            return false;
        }
        return true;
    }
    return false;
}

function getAdminRemainingLockoutTime() {
    if ($_SESSION['admin_lockout_time'] !== null) {
        $lockoutDuration = 15 * 60; // 15 minutes
        $elapsed = time() - $_SESSION['admin_lockout_time'];
        $remaining = $lockoutDuration - $elapsed;
        if ($remaining > 0) {
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            return sprintf("%d:%02d", $minutes, $seconds);
        }
    }
    return "0:00";
}

$currentlyLockedOut = isAdminLockedOut();
$remainingTime = $currentlyLockedOut ? getAdminRemainingLockoutTime() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentlyLockedOut) {
        $error = "Admin account locked due to multiple failed attempts. Please try again in $remainingTime.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($email) || empty($username) || empty($password)) {
            $error = 'Please enter email, username, and password.';
        } else {
            try {
                $admin = authenticateAdmin($email, $username, $password);
                
                if ($admin) {
                    $_SESSION['admin_failed_attempts'] = 0;
                    $_SESSION['admin_lockout_time'] = null;
                    $success = 'Admin authentication successful!';
                    
                    $redirectTo = $_SESSION['admin_redirect_after_login'] ?? 'admin_dashboard.php';
                    unset($_SESSION['admin_redirect_after_login']);
                    
                    header("Location: $redirectTo");
                    exit();
                } else {
                    $_SESSION['admin_failed_attempts']++;
                    if ($_SESSION['admin_failed_attempts'] >= 3) {
                        $_SESSION['admin_lockout_time'] = time();
                        $error = 'Admin account locked due to 3 failed login attempts. Please try again in 15 minutes.';
                        $currentlyLockedOut = true;
                        $remainingTime = "15:00";
                    } else {
                        $remainingAttempts = 3 - $_SESSION['admin_failed_attempts'];
                        $error = "Invalid credentials. $remainingAttempts attempt(s) remaining before lockout.";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['admin_failed_attempts']++;
                if ($_SESSION['admin_failed_attempts'] >= 3) {
                    $_SESSION['admin_lockout_time'] = time();
                    $error = 'Admin account locked due to multiple failed attempts. Please try again in 15 minutes.';
                    $currentlyLockedOut = true;
                    $remainingTime = "15:00";
                } else {
                    $error = 'Authentication error: ' . $e->getMessage();
                }
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
    <title>Admin Login - Secure Access</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .admin-login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .admin-login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1);
            border-radius: 15px 15px 0 0;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .login-header p {
            color: #7f8c8d;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        input[type="email"], input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background: #f8f9fa;
        }
        
        input[type="email"]:focus, input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        input[type="email"]:disabled, input[type="text"]:disabled, input[type="password"]:disabled {
            background-color: #ecf0f1;
            color: #7f8c8d;
            cursor: not-allowed;
        }
        
        .btn {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        
        .error {
            background: #fee;
            color: #e74c3c;
            padding: 0.9rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #e74c3c;
            font-size: 0.9rem;
        }
        
        .success {
            background: #eafaf1;
            color: #27ae60;
            padding: 0.9rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #27ae60;
            font-size: 0.9rem;
        }
        
        .lockout-info {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #ffc107;
            text-align: center;
        }
        
        .lockout-timer {
            font-size: 1.3rem;
            font-weight: bold;
            color: #dc3545;
            margin-top: 0.5rem;
        }
        
        .attempts-info {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .security-note {
            font-size: 0.8rem;
            color: #95a5a6;
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ecf0f1;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background-size: contain;
            background-repeat: no-repeat;
            z-index: 1;
        }
        
        .input-icon input {
            padding-left: 2.5rem;
        }
    </style>
    <?php if ($currentlyLockedOut): ?>
    <script>
        let remainingSeconds = <?php 
            $lockoutDuration = 15 * 60; // 15 minutes
            $elapsed = time() - $_SESSION['admin_lockout_time'];
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
    <div class="admin-login-container">
        <div class="login-header">
            <div class="admin-badge">👑 ADMIN ACCESS</div>
            <h2>🔐 Admin Login</h2>
            <p>Secure administrative access portal</p>
        </div>
        
        <?php if ($currentlyLockedOut): ?>
            <div class="lockout-info">
                <strong>🚫 Account Locked</strong><br>
                Multiple failed login attempts detected.<br>
                Please wait: <span class="lockout-timer" id="lockout-timer"><?php echo $remainingTime; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!$currentlyLockedOut && $_SESSION['admin_failed_attempts'] > 0): ?>
            <div class="attempts-info">
                ⚠️ Failed attempts: <?php echo $_SESSION['admin_failed_attempts']; ?>/3
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">📧 Email Address:</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required 
                       autofocus
                       placeholder="Enter your admin email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="username">👤 Username:</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       placeholder="Enter your admin username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="password">🔑 Password:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       placeholder="Enter your admin password"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            
            <button type="submit" 
                    class="btn" 
                    <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                <?php echo $currentlyLockedOut ? '🔒 Account Locked' : '🚀 Login to Admin Panel'; ?>
            </button>
        </form>
        
        <div class="security-note">
            🔒 This is a secure admin area. All access attempts are logged and monitored.
        </div>
    </div>
</body>
</html>