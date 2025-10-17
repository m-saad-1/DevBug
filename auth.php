<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$name = $username = $email = $password = $confirm_password = "";
$login_email = $login_password = "";
$errors = [];
$success_msg = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['register'])) {
        // Registration form submitted
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $terms_agreed = isset($_POST['terms_agree']);
        
        // Validate registration data
        if (empty($name)) {
            $errors[] = "Name is required";
        }
        
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        }
        
        if (empty($password) || strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if (!$terms_agreed) {
            $errors[] = "You must agree to the terms and conditions";
        }
        
        // Check if email or username already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email or username already registered";
            }
        }
        
        // If no errors, register user
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $avatar_color = '#' . substr(md5(rand()), 0, 6); // Random color for avatar
            
            $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, avatar_color, title) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $username, $email, $hashed_password, $avatar_color, 'Developer'])) {
                $user_id = $pdo->lastInsertId();
                
                // Store registration data in session for profile completion
                $_SESSION['pending_user_id'] = $user_id;
                $_SESSION['pending_name'] = $name;
                $_SESSION['pending_username'] = $username;
                $_SESSION['pending_email'] = $email;
                
                // Redirect to profile completion page
                header("Location: complete-profile.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    } elseif (isset($_POST['login'])) {
        // Login form submitted
        $login_email = trim($_POST['login_email']);
        $login_password = $_POST['login_password'];
        $remember_me = isset($_POST['remember_me']);
        
        // Validate login data
        if (empty($login_email)) {
            $errors[] = "Email is required";
        }
        
        if (empty($login_password)) {
            $errors[] = "Password is required";
        }
        
        // If no errors, verify login
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$login_email, $login_email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($login_password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_title'] = $user['title'];
                $_SESSION['avatar_color'] = $user['avatar_color'];
                $_SESSION['created_at'] = $user['created_at'];
                $_SESSION['reputation'] = $user['reputation'];
                $_SESSION['username'] = $user['username'] ?? null;
                $_SESSION['bio'] = $user['bio'] ?? null;
                $_SESSION['location'] = $user['location'] ?? null;
                $_SESSION['company'] = $user['company'] ?? null;
                $_SESSION['website'] = $user['website'] ?? null;
                $_SESSION['github'] = $user['github'] ?? null;
                $_SESSION['twitter'] = $user['twitter'] ?? null;
                $_SESSION['linkedin'] = $user['linkedin'] ?? null;
                $_SESSION['skills'] = $user['skills'] ?? null;
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;
                $_SESSION['email_notifications'] = $user['email_notifications'] ?? 1;
                $_SESSION['email_solutions'] = $user['email_solutions'] ?? 1;
                $_SESSION['email_comments'] = $user['email_comments'] ?? 1;
                $_SESSION['email_newsletter'] = $user['email_newsletter'] ?? 1;
                session_write_close();
                
                // Create session token for "remember me"
                if ($remember_me) {
                    $session_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $session_token, $expires_at]);
                    
                    setcookie('remember_token', $session_token, time() + (30 * 24 * 60 * 60), "/");
                }
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Invalid email/username or password";
            }
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Check for remember me cookie
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
    $session_token = $_COOKIE['remember_token'];
    
    $stmt = $pdo->prepare("SELECT u.* FROM users u JOIN sessions s ON u.id = s.user_id WHERE s.session_token = ? AND s.expires_at > NOW()");
    $stmt->execute([$session_token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_title'] = $user['title'];
        $_SESSION['avatar_color'] = $user['avatar_color'];
        
        header("Location: dashboard.php");
        exit();
    }
}
include(__DIR__ . '/Components/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Auth Section */
        .auth-section {
            flex: 1;
            display: flex;
            align-items: start;
            padding: 60px 0;
        }

        .auth-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: start;
            width: 100%;
        }

        .auth-content {
            padding-right: 30px;
        }

        .auth-content h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-content p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
            line-height: 1.7;
        }

        .auth-features {
            list-style: none;
            margin-top: 40px;
        }

        .auth-features li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: var(--text-secondary);
        }

        .auth-features i {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
        }

        .auth-form-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .auth-form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .form-header p {
            color: var(--text-muted);
        }

        .auth-tabs {
            display: flex;
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 30px;
        }

        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            cursor: pointer;
            border-radius: 8px;
            transition: var(--transition);
            color: var(--text-muted);
            font-weight: 500;
        }

        .auth-tab.active {
            background: var(--accent-primary);
            color: white;
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .input-with-icon input {
            padding-left: 45px;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
        }

        .remember-me input {
            accent-color: var(--accent-primary);
        }

        .forgot-password {
            color: var(--accent-primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .form-submit {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 20px;
        }

        .form-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: var(--text-muted);
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            padding: 0 15px;
            font-size: 0.9rem;
        }

        .social-auth {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .social-btn:hover {
            transform: translateY(-2px);
            border-color: var(--accent-primary);
        }

        .github-btn:hover {
            background: rgba(36, 41, 46, 0.2);
        }

        .google-btn:hover {
            background: rgba(66, 133, 244, 0.1);
        }

        .form-footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-footer a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateX(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification.error {
            background: var(--danger);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.info {
            background: var(--accent-primary);
        }
  

        /* Responsive Design */
        @media (max-width: 968px) {
            .auth-container {
                grid-template-columns: 1fr;
            }
            
            .auth-content {
                padding-right: 0;
                text-align: center;
            }
            
            .auth-features {
                max-width: 500px;
                margin: 40px auto 0;
            }
            
            .social-auth {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .auth-content h1 {
                font-size: 2.5rem;
            }
            
            .auth-form-container {
                padding: 30px 20px;
            }
            
            .form-header h2 {
                font-size: 1.8rem;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Auth Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-content">
                    <h1>Join Our Developer Community</h1>
                    <p>Connect with thousands of developers worldwide to solve bugs, share knowledge, and advance your coding skills together.</p>
                    
                    <ul class="auth-features">
                        <li>
                            <i class="fas fa-code"></i>
                            <span>Get help with your coding challenges</span>
                        </li>
                        <li>
                            <i class="fas fa-medal"></i>
                            <span>Earn reputation and badges</span>
                        </li>
                        <li>
                            <i class="fas fa-users"></i>
                            <span>Join a supportive community</span>
                        </li>
                        <li>
                            <i class="fas fa-bolt"></i>
                            <span>Fast and helpful responses</span>
                        </li>
                    </ul>
                </div>
                
                <div class="auth-form-container">
                    <div class="form-header">
                        <h2 id="form-title">Welcome Back</h2>
                        <p id="form-subtitle">Sign in to your account to continue</p>
                    </div>
                    
                    <div class="auth-tabs">
                        <div class="auth-tab active" data-tab="login">Login</div>
                        <div class="auth-tab" data-tab="register">Register</div>
                    </div>
                    
                    <!-- Login Form -->
                    <form class="auth-form active" id="login-form" method="POST">
                        <input type="hidden" name="login" value="1">
                        <div class="form-group">
                            <label for="login-email">Email or Username</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="login-email" name="login_email" class="form-control" placeholder="Enter your email or username" value="<?php echo htmlspecialchars($login_email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="login-password" name="login_password" class="form-control" placeholder="Enter your password" required>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" id="remember-me" name="remember_me">
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="forgot-password">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="form-submit">Sign In</button>
                        
                        <div class="divider">
                            <span>Or continue with</span>
                        </div>
                        
                        <div class="social-auth">
                            <button type="button" class="social-btn github-btn">
                                <i class="fab fa-github"></i>
                                <span>GitHub</span>
                            </button>
                            <button type="button" class="social-btn google-btn">
                                <i class="fab fa-google"></i>
                                <span>Google</span>
                            </button>
                        </div>
                        
                        <div class="form-footer">
                            Don't have an account? <a href="#" id="switch-to-register">Sign up</a>
                        </div>
                    </form>
                    
                    <!-- Register Form -->
                    <form class="auth-form" id="register-form" method="POST">
                        <input type="hidden" name="register" value="1">
                        <div class="form-group">
                            <label for="register-name">Full Name</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="register-name" name="name" class="form-control" placeholder="Enter your full name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-username">Username</label>
                            <div class="input-with-icon">
                                <i class="fas fa-at"></i>
                                <input type="text" id="register-username" name="username" class="form-control" placeholder="Choose a username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <small style="color: var(--text-muted);">Letters, numbers, and underscores only</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-email">Email Address</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="register-email" name="email" class="form-control" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-password">Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="register-password" name="password" class="form-control" placeholder="Create a password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-confirm">Confirm Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="register-confirm" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" id="terms-agree" name="terms_agree" required>
                                <span>I agree to the <a href="#" style="color: var(--accent-primary);">Terms</a> and <a href="#" style="color: var(--accent-primary);">Privacy Policy</a></span>
                            </label>
                        </div>
                        
                        <button type="submit" class="form-submit">Create Account</button>
                        
                        <div class="divider">
                            <span>Or continue with</span>
                        </div>
                        
                        <div class="social-auth">
                            <button type="button" class="social-btn github-btn">
                                <i class="fab fa-github"></i>
                                <span>GitHub</span>
                            </button>
                            <button type="button" class="social-btn google-btn">
                                <i class="fab fa-google"></i>
                                <span>Google</span>
                            </button>
                        </div>
                        
                        <div class="form-footer">
                            Already have an account? <a href="#" id="switch-to-login">Sign in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include(__DIR__ . '/footer.html'); ?>

    <!-- Display notifications -->
    <?php if (!empty($errors)): ?>
        <div class="notification error">
            <?php echo $errors[0]; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_msg)): ?>
        <div class="notification success">
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.auth-tab');
            const forms = document.querySelectorAll('.auth-form');
            const formTitle = document.getElementById('form-title');
            const formSubtitle = document.getElementById('form-subtitle');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding form
                    forms.forEach(form => {
                        if (form.id === `${tabName}-form`) {
                            form.classList.add('active');
                            // Update form header based on active tab
                            if (tabName === 'login') {
                                formTitle.textContent = 'Welcome Back';
                                formSubtitle.textContent = 'Sign in to your account to continue';
                            } else {
                                formTitle.textContent = 'Create Account';
                                formSubtitle.textContent = 'Join our developer community today';
                            }
                        } else {
                            form.classList.remove('active');
                        }
                    });
                });
            });
            
            // Switch to register form
            document.getElementById('switch-to-register').addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('[data-tab="register"]').click();
            });
            
            // Switch to login form
            document.getElementById('switch-to-login').addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('[data-tab="login"]').click();
            });
            
            // Show notifications
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.classList.add('show');
                
                // Remove after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            });
            
            // Social login buttons
            const socialButtons = document.querySelectorAll('.social-btn');
            socialButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const provider = this.classList.contains('github-btn') ? 'GitHub' : 'Google';
                    showNotification(`Signing in with ${provider}...`, 'info');
                    
                    // In a real application, this would redirect to OAuth flow
                    setTimeout(() => {
                        showNotification(`${provider} authentication would be implemented here`, 'info');
                    }, 1000);
                });
            });
            
            // Forgot password
            document.querySelector('.forgot-password').addEventListener('click', function(e) {
                e.preventDefault();
                showNotification('Password reset functionality would be implemented here', 'info');
            });
            
            // Notification function
            function showNotification(message, type) {
                // Create notification element
                const notification = document.createElement('div');
                notification.textContent = message;
                notification.className = `notification ${type}`;
                
                // Add to page
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.classList.add('show');
                }, 10);
                
                // Remove after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>