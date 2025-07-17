<?php
require_once 'auth00/user_auth.php';
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
        $password = trim($_POST['password'] ?? '');
        if (empty($mobile) || empty($password)) {
            $error = 'Please enter both mobile number and password.';
        } elseif (!preg_match('/^\d{11}$/', $mobile)) {
            $error = 'Mobile number must be exactly 11 digits.';
        } else {
            try {
                $user = authenticateUser($mobile, $password);
                if ($user) {
                    $_SESSION['failed_attempts'] = 0;
                    $_SESSION['lockout_time'] = null;
                    $success = 'Authentication successful!';
                    $redirectTo = $_SESSION['redirect_after_login'] ?? 'index';
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
                        $error = "Invalid mobile number or password. $remainingAttempts attempt(s) remaining before lockout.";
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
input[type="email"],
input[type="password"] {
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
input[type="email"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: #0070f3;
    box-shadow: 0 0 0 3px rgba(0, 112, 243, 0.1);
}

input[type="text"]::placeholder,
input[type="email"]::placeholder,
input[type="password"]::placeholder {
    color: #6c757d;
}

input[type="text"]:disabled, 
input[type="email"]:disabled,
input[type="password"]:disabled {
    background-color: #f5f5f5;
    color: #6c757d;
    cursor: not-allowed;
    opacity: 0.7;
}

.password-container {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: #6c757d;
    padding: 4px;
    border-radius: 4px;
    transition: color 0.2s ease;
}
.password-toggle:hover {
    color: #212529;
}
.password-toggle:focus {
    outline: none;
    color: #0070f3;
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

.forgot-password {
    text-align: center;
    margin-top: 16px;
}

.forgot-password a {
    color: #0070f3;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s ease;
}

.forgot-password a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.security-info {
    background: rgba(13, 110, 253, 0.1);
    border: 1px solid rgba(13, 110, 253, 0.3);
    color: #0a58ca;
    padding: 10px 12px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 12px;
    text-align: center;
    border-left: 4px solid #0d6efd;
}

.input-hint {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
    display: block;
}

.input-counter {
    font-size: 12px;
    color: #6c757d;
    text-align: right;
    margin-top: 4px;
}

.input-counter.valid {
    color: #28a745;
}

.input-counter.invalid {
    color: #dc3545;
}

@media (max-width: 480px) {
    body {
        padding: 16px;
    }
    
    .login-container {
        padding: 24px;
    }
    
    .logo-section img {
        width: auto;
        padding: 8px;
    }
    
    .login-header h2 {
        font-size: 24px;
    }
    
    .login-header p {
        font-size: 14px;
    }
    
    input[type="text"], 
    input[type="email"],
    input[type="password"] {
        font-size: 14px;
        padding: 10px 14px;
    }
    
    .btn {
        font-size: 14px;
        padding: 10px 20px;
    }
    
    .password-toggle {
        font-size: 16px;
        right: 10px;
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
        <img src="assets/images/logo.png" alt="Company Logo" id="logo">
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <p>Confirm your identity to proceed</p>
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
        <form method="POST" action="">
            <div class="form-group">
                <label for="mobile">Mobile Number:</label>
                <input type="text" 
                       id="mobile" 
                       name="mobile" 
                       required 
                       autofocus
                       placeholder="Enter 11-digit mobile number"
                       value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>"
                       maxlength="11"
                       pattern="[0-9]{11}"
                       inputmode="numeric"
                       <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                <span class="input-hint">Enter exactly 11 digits (numbers only)</span>
                <div class="input-counter" id="mobile-counter">0/11</div>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-container">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Enter your password"
                           <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                    <button type="button" 
                            class="password-toggle" 
                            onclick="togglePassword()"
                            <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                        👁️
                    </button>
                </div>
            </div>
            
            <button type="submit" 
                    class="btn" 
                    id="submit-btn"
                    <?php echo $currentlyLockedOut ? 'disabled' : ''; ?>>
                <?php echo $currentlyLockedOut ? 'Account Locked' : 'Sign In'; ?>
            </button>
        </form>
        
        <?php if (!$currentlyLockedOut): ?>
            <div class="forgot-password">
                <a href="https://www.safernaija.com.ng/user/password/reset">Forgot your password?</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.textContent = '🙈';
                toggleButton.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                toggleButton.textContent = '👁️';
                toggleButton.setAttribute('aria-label', 'Show password');
            }
        }
        
        // Mobile number validation and formatting
        document.getElementById('mobile').addEventListener('input', function(e) {
            // Remove all non-digit characters
            let value = e.target.value.replace(/\D/g, '');
            
            // Limit to 11 digits
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            // Update the input value
            e.target.value = value;
            
            // Update counter
            const counter = document.getElementById('mobile-counter');
            const submitBtn = document.getElementById('submit-btn');
            
            counter.textContent = value.length + '/11';
            
            // Update counter styling and button state
            if (value.length === 11) {
                counter.classList.add('valid');
                counter.classList.remove('invalid');
                submitBtn.disabled = false;
            } else if (value.length > 0) {
                counter.classList.add('invalid');
                counter.classList.remove('valid');
                submitBtn.disabled = true;
            } else {
                counter.classList.remove('valid', 'invalid');
                submitBtn.disabled = false;
            }
        });
        
        // Prevent non-numeric input
        document.getElementById('mobile').addEventListener('keypress', function(e) {
            // Allow only numeric keys, backspace, delete, tab, escape, enter
            if (!/[0-9]/.test(e.key) && 
                !['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                e.preventDefault();
            }
        });
        
        // Prevent paste of non-numeric content
        document.getElementById('mobile').addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const numericOnly = paste.replace(/\D/g, '').slice(0, 11);
            e.target.value = numericOnly;
            
            // Trigger input event to update counter
            e.target.dispatchEvent(new Event('input'));
        });
        
        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const mobileInput = document.getElementById('mobile');
            const submitBtn = document.querySelector('.btn');
            
            // Final validation before submission
            if (mobileInput.value.length !== 11) {
                e.preventDefault();
                alert('Please enter exactly 11 digits for your mobile number.');
                mobileInput.focus();
                return;
            }
            
            if (!submitBtn.disabled) {
                submitBtn.innerHTML = '<span class="loading"></span>Signing in...';
                submitBtn.disabled = true;
            }
        });
        
        // Auto-focus on mobile input when page loads and initialize counter
        document.addEventListener('DOMContentLoaded', function() {
            const mobileInput = document.getElementById('mobile');
            if (mobileInput && !mobileInput.disabled) {
                mobileInput.focus();
                // Initialize counter
                const counter = document.getElementById('mobile-counter');
                counter.textContent = mobileInput.value.length + '/11';
                
                // Check if current value is valid
                if (mobileInput.value.length === 11) {
                    counter.classList.add('valid');
                } else if (mobileInput.value.length > 0) {
                    counter.classList.add('invalid');
                }
            }
        });
    </script>
</body>
</html>