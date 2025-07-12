<?php
require_once 'auth.php';
$error = '';
$success = '';
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}
if (!isset($_SESSION['lockout_time'])) {
    $_SESSION['lockout_time'] = null;
}
function isLockedOut() {
    if ($_SESSION['failed_attempts'] >= 3 && $_SESSION['lockout_time'] !== null) {
        $lockoutDuration = 10 * 60;
        $currentTime = time();
        if (($currentTime - $_SESSION['lockout_time']) >= $lockoutDuration) {
            $_SESSION['failed_attempts'] = 0;
            $_SESSION['lockout_time'] = null;
            return false;
        }
        return true;
    }
    return false;
}
function getRemainingLockoutTime() {
    if ($_SESSION['lockout_time'] !== null) {
        $lockoutDuration = 10 * 60;
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
$currentlyLockedOut = isLockedOut();
$remainingTime = $currentlyLockedOut ? getRemainingLockoutTime() : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentlyLockedOut) {
        $error = "Account locked due to multiple failed attempts. Please try again in $remainingTime.";
    } else {
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($mobile) || empty($email)) {
            $error = 'Please enter both mobile number and email address.';
        } else {
            try {
                $user = authenticateUser($mobile, $email);
                
                if ($user) {
                    $_SESSION['failed_attempts'] = 0;
                    $_SESSION['lockout_time'] = null;
                    $success = 'Authentication successful!';
                    $redirectTo = $_SESSION['redirect_after_login'] ?? 'upload-file.php';
                    unset($_SESSION['redirect_after_login']);
                    
                    header("Location: $redirectTo");
                    exit();
                } else {
                    $_SESSION['failed_attempts']++;
                    if ($_SESSION['failed_attempts'] >= 3) {
                        $_SESSION['lockout_time'] = time();
                        $error = 'Account locked due to 3 failed login attempts. Please try again in 10 minutes.';
                        $currentlyLockedOut = true;
                        $remainingTime = "10:00";
                    } else {
                        $remainingAttempts = 3 - $_SESSION['failed_attempts'];
                        $error = "Invalid mobile number or email address. $remainingAttempts attempt(s) remaining before lockout.";
                    }
                }
            } catch (Exception $e) {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protected Area - Login Required</title>
    <style>
       * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #212529;
    padding: 20px;
}

.login-container {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
}

.logo-section {
    text-align: center;
}

.logo-section img {
    height: 70px;
    border-radius: 12px;
    transition: transform 0.3s ease;
    padding: 12px;
}

.logo-section img:hover {
    transform: scale(1.05);
}

.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.login-header h2 {
    font-size: 32px;
    font-weight: 600;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #212529 0%, #6c757d 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-header p {
    color: #6c757d;
    font-size: 16px;
    margin: 0;
}

.form-group {
    margin-bottom: 24px;
}

label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
    color: #212529;
}

input[type="text"], 
input[type="email"] {
    width: 100%;
    padding: 12px 16px;
    font-size: 16px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    color: #212529;
    transition: all 0.2s ease;
    font-family: inherit;
}

input[type="text"]:focus, 
input[type="email"]:focus {
    outline: none;
    border-color: #0070f3;
    box-shadow: 0 0 0 3px rgba(0, 112, 243, 0.1);
}

input[type="text"]::placeholder,
input[type="email"]::placeholder {
    color: #6c757d;
}

input[type="text"]:disabled, 
input[type="email"]:disabled {
    background-color: #f5f5f5;
    color: #6c757d;
    cursor: not-allowed;
    opacity: 0.7;
}

.btn {
    width: 100%;
    padding: 12px 24px;
    font-size: 16px;
    font-weight: 500;
    background: linear-gradient(135deg, #34c759 0%, #28a745 100%);
    border: none;
    border-radius: 8px;
    color: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    font-family: inherit;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 25px rgba(52, 199, 89, 0.3);
}

.btn:active:not(:disabled) {
    transform: translateY(0);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.error {
    background: rgba(255, 59, 48, 0.1);
    border: 1px solid rgba(255, 59, 48, 0.3);
    color: #ff3b30;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    border-left: 4px solid #ff3b30;
}

.success {
    background: rgba(52, 199, 89, 0.1);
    border: 1px solid rgba(52, 199, 89, 0.3);
    color: #34c759;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    border-left: 4px solid #34c759;
}

.lockout-info {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    color: #856404;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    border-left: 4px solid #ffc107;
    text-align: center;
    font-size: 14px;
}

.lockout-timer {
    font-size: 18px;
    font-weight: 600;
    color: #ff3b30;
    margin-top: 8px;
}

.attempts-info {
    font-size: 14px;
    color: #6c757d;
    text-align: center;
    margin-bottom: 16px;
    background: rgba(108, 117, 125, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
}
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top: 2px solid #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@media (max-width: 480px) {
    body {
        padding: 16px;
    }
    
    .login-container {
        padding: 24px;
    }
    
    .logo-section img {
        max-width: 120px;
        padding: 8px;
    }
    
    .login-header h2 {
        font-size: 24px;
    }
    
    .login-header p {
        font-size: 14px;
    }
    
    input[type="text"], 
    input[type="email"] {
        font-size: 14px;
        padding: 10px 14px;
    }
    
    .btn {
        font-size: 14px;
        padding: 10px 20px;
    }
}
    </style>
    <?php if ($currentlyLockedOut): ?>
    <script>
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
    <div class="logo-section">
        <img src="images/logo.png" alt="Company Logo" id="logo">
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <p>Please enter your mobile number and email to continue</p>
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
                <label for="mobile">Mobile Number:</label>
                <input type="text" 
                       id="mobile" 
                       name="mobile" 
                       required 
                       autofocus
                       placeholder="Enter your mobile number"
                       value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required 
                       placeholder="Enter your email address"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
            </div>
            <button type="submit" 
                    class="btn" 
                    <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                <?php echo $currentlyLockedOut ? 'Account Locked' : 'Login'; ?>
            </button>
        </form>
    </div>
</body>
</html>