<?php
// Components/header.php

// This must be at the top before any output.
require_once __DIR__ . '/../includes/utils.php';

// Handle logout - redirect immediately if logout is requested
if (isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Delete remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, "/");
    }
    
    // Redirect to home page
    header("Location: auth.php");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$userTitle = $isLoggedIn ? ($_SESSION['user_title'] ?? 'Developer') : 'Developer';
$avatarColor = $isLoggedIn ? ($_SESSION['avatar_color'] ?? '#6366f1') : '#6366f1';
$avatarInitials = $isLoggedIn ? strtoupper(substr($userName, 0, 2)) : '';
$profilePicture = $isLoggedIn ? ($_SESSION['profile_picture'] ?? null) : null;

// Database connection for notifications
require_once __DIR__ . '/../config/database.php';

// Fetch notifications if user is logged in
$notifications = [];
$unreadCount = 0;
$unreadMessageCount = 0;

if ($isLoggedIn) {
    try {
        // Get notifications for the current user
        $stmt = $pdo->prepare("
            SELECT n.*, u.name as sender_name, u.avatar_color as sender_avatar_color, u.profile_picture as sender_profile_picture
            FROM notifications n 
            LEFT JOIN users u ON n.sender_id = u.id 
            WHERE n.user_id = ? 
            ORDER BY n.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count unread notifications
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Count unread messages specifically
        $msg_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = 'new_message' AND is_read = 0");
        $msg_stmt->execute([$_SESSION['user_id']]);
        $unreadMessageCount = $msg_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    } catch (PDOException $e) {
        $notifications = [];
        $unreadCount = 0;
        $unreadMessageCount = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevBug - Header</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐞</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a28;
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-tertiary: #06b6d4;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #2d2d3f;
            --card-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --glow: 0 0 15px rgba(99, 102, 241, 0.5);
        }

        /* Universal Loader */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.5s ease;
        }

        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid var(--border);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Header Styles */
        header {
            background: rgba(10, 10, 15, 0.9);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }

        .logo .dev-text {
            color: var(--text-primary);
        }

        .logo .bug-text {
            color: var(--accent-primary);
        }

        .logo-icon {
            font-size: 1.7rem;
            line-height: 1; /* Improves vertical alignment of the emoji */
            margin-top: -2px; /* Fine-tune vertical alignment */
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 16px;
            border-radius: 6px;
            position: relative;
        }

        nav a:hover {
            color: var(--text-primary);
        }

        nav a:hover::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 16px;
            right: 16px;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 2px;
        }

        .auth-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--accent-primary);
            color: var(--accent-primary);
        }

        .btn-outline:hover {
            background: rgba(99, 102, 241, 0.1);
            box-shadow: var(--glow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        .user-menu {
            position: relative;
        }

        /* User Profile Styles */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-btn {
            position: relative;
            background: var(--bg-card);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .notification-btn:hover {
            color: var(--accent-primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: var(--transition);
        }

        .user-profile:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1rem;
        }
        .user-avatar-wrapper {
            position: relative;
        }
        .message-dot {
            position: absolute;
            top: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background-color: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--bg-card);
            display: none; /* Hidden by default */
        }

        .user-profile .user-avatar {
            width: 40px;
            height: 40px;
        }
        .user-profile:hover {
            background: transparent;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .user-title {
            color: var(--accent-tertiary);
            font-size: 0.8rem;
        }

        /* Dropdown menu */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 0;
            min-width: 200px;
            box-shadow: var(--card-shadow);
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            padding: 10px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        .dropdown-item .message-counter {
            background-color: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .dropdown-item:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .dropdown-item.logout-item:hover {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }


        .dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: 5px 0;
        }

        /* Notification Modal */
        .notification-modal {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            box-shadow: var(--card-shadow);
            display: none;
            /* Hide scrollbar for Chrome, Safari and Opera */
            &::-webkit-scrollbar {
                display: none;
            }
            /* Hide scrollbar for IE, Edge and Firefox */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */

            z-index: 1000;
        }

        .notification-modal.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .notification-action {
            background: none;
            border: none;
            color: var(--accent-primary);
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .notification-action:hover {
            color: var(--accent-secondary);
        }

        .notification-list {
            list-style: none;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
            cursor: pointer;
        }

        .notification-item:hover {
            background: var(--bg-secondary);
        }

        .notification-item.unread {
            background: rgba(99, 102, 241, 0.1);
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .notification-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .notification-details {
            flex: 1;
        }

        .notification-message {
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .notification-time {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .notification-footer {
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid var(--border);
        }

        .view-all-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-all-link:hover {
            color: var(--accent-secondary);
        }

        .no-notifications {
            padding: 30px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .no-notifications i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        /* Responsive Design */
        .mobile-menu-button {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1001;
        }

        .hamburger-icon {
            width: 24px;
            height: 20px;
            position: relative;
            transform: rotate(0deg);
            transition: .5s ease-in-out;
        }

        .hamburger-icon span {
            display: block;
            position: absolute;
            height: 3px;
            width: 100%;
            background: var(--text-primary);
            border-radius: 3px;
            opacity: 1;
            left: 0;
            transform: rotate(0deg);
            transition: .25s ease-in-out;
        }

        .hamburger-icon span:nth-child(1) {
            top: 0px;
        }

        .hamburger-icon span:nth-child(2), .hamburger-icon span:nth-child(3) {
            top: 8px;
        }

        .hamburger-icon span:nth-child(4) {
            top: 16px;
        }

        .mobile-menu-button.active .hamburger-icon span:nth-child(1) {
            top: 8px;
            width: 0%;
            left: 50%;
        }

        .mobile-menu-button.active .hamburger-icon span:nth-child(2) {
            transform: rotate(45deg);
        }

        .mobile-menu-button.active .hamburger-icon span:nth-child(3) {
            transform: rotate(-45deg);
        }

        .mobile-menu-button.active .hamburger-icon span:nth-child(4) {
            top: 8px;
            width: 0%;
            left: 50%;
        }

        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            display: none; /* Hidden by default */
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(26, 26, 40, 0.85); /* Consistent with top header */
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border);
            z-index: 999;
            padding: 8px 15px;
            justify-content: space-between;
            align-items: center;
        }

        .bottom-nav-left, .bottom-nav-right {
            display: flex;
            justify-content: space-around;
            flex: 1;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.75rem;
            padding: 5px;
            border-radius: 6px;
            transition: var(--transition);
        }

        .bottom-nav-item:hover, .bottom-nav-item.active {
            color: var(--accent-primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .bottom-nav-item i {
            font-size: 1.2rem;
        }

        .bottom-nav-center {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: -30px 15px 0;
            box-shadow: none;
            border: 2px solid var(--border);
            transition: var(--transition);
            text-decoration: none;
        }

        .bottom-nav-center:hover {
            transform: translateY(-3px) scale(1.05);
        }

        .bottom-nav-item .user-avatar {
            width: 24px;
            height: 24px;
            font-size: 0.7rem;
        }

        @media (max-width: 1024px) {
            .header-content .user-details {
                display: none; /* Make selector more specific to header */
            }

            nav {
                display: none;
            }

            .mobile-menu-button {
                display: block;
            }
            .user-menu {
                gap: 10px;
            }
            .user-profile {
                padding: 0;
            }

            /* Reorder header items for mobile */
            .logo {
                order: 1;
                margin-right: auto; /* Pushes other items to the right */
            }

            .user-menu, .auth-buttons {
                order: 2;
                margin-right: 10px; /* Add gap between user menu and hamburger */
            }

            .mobile-menu-button {
                order: 3;
            }

            .auth-buttons {
                display: flex; /* Ensure auth buttons are visible on this breakpoint */
            }

            nav {
                display: none;
                position: absolute;
                top: calc(100% + 15px);
                right: 0;
                left: auto;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 10px 0;
                min-width: 220px;
                box-shadow: var(--card-shadow);
                z-index: 999;
            }

            nav.active {
                display: block;
            }

            nav ul {
                flex-direction: column;
                gap: 20px;
                align-items: stretch;
            }

            nav a {
                padding: 15px 25px;
                border-radius: 0;
                font-size: 1.3rem;
            }

            nav a:hover {
                background: var(--bg-secondary);
            }

            nav a:hover::after {
                display: none;
            }

            /* Show bottom nav on tablets and mobile */
            .mobile-bottom-nav {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                justify-content: space-between;
                position: relative;
            }

            /* Show mobile-specific auth buttons inside the nav menu */
            .auth-buttons-mobile {
                display: flex;
            }

            .auth-buttons-mobile {
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 10px 20px;
            }
            
            .dropdown-menu, .notification-modal {
                right: 0;
                left: auto;
            }
        }

        /* Hide mobile auth buttons by default on larger screens */
        .auth-buttons-mobile {
            display: none;
        }

       
        @media (max-width: 599px) {
            .logo {
                font-size: 1.2rem;
            }
            .logo i {
                font-size: 1.4rem;
            }
            .notification-modal {
                width: 100vw;
                max-width: 300px;
            }
            .auth-buttons {
                gap: 10px;
            }
            .btn {
                padding: 8px 16px;
                font-size: 0.8rem;
            }

            /* Hide user name and title in header on mobile */
            .header-content .user-profile .user-details {
                display: none; /* Make selector more specific */
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div id="universal-loader" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>


    <header>
        <div class="container">
            <div class="header-content">
                <button class="mobile-menu-button" id="mobileMenuBtn">
                    <div class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </button>

                <a href="index.php" class="logo">
                    <span class="logo-icon">🐞</span>
                    <span class="logo-text"><span class="dev-text">Dev</span><span class="bug-text">Bug</span></span>
                </a>

                <nav id="mainNav">
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="bug-post.php">Bugs</a></li>
                        <li><a href="solutions.php">Solutions</a></li>
                        <li><a href="leaderboard.php">Leaderboard</a></li>
                        <li><a href="about.php">About</a></li>
                         <?php if (!$isLoggedIn): ?>
                        <li class="auth-buttons-mobile">
                            <a href="auth.php" class="btn btn-outline">Login</a>
                            <a href="auth.php" class="btn btn-primary">Sign Up</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <?php if ($isLoggedIn): ?>
                <!-- User menu (shown when user is logged in) -->
                <div class="user-menu">
                    <div class="notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notification Modal -->
                    <div class="notification-modal" id="notificationModal">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <div class="notification-actions">
                                <button class="notification-action" id="markAllReadBtn">Mark all read</button>
                                <button class="notification-action" id="clearNotifications">Clear</button>
                            </div>
                        </div>
                        
                        <ul class="notification-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-content" style="text-decoration: none; display: flex;">
                                            <?php if ($notification['sender_id']): ?>
                                            <div class="notification-avatar" style="background: <?php echo $notification['sender_avatar_color']; ?>; overflow: hidden;">
                                                <?php if (!empty($notification['sender_profile_picture'])): ?>
                                                    <img src="<?php echo htmlspecialchars($notification['sender_profile_picture']); ?>" alt="<?php echo htmlspecialchars($notification['sender_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($notification['sender_name'], 0, 2)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                                <div class="notification-avatar" style="background: var(--accent-primary);">
                                                    <i class="fas fa-bug"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="notification-details">
                                                <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="no-notifications">
                                    <i class="far fa-bell"></i>
                                    <p>No notifications yet</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <?php if (!empty($notifications)): ?>
                        <div class="notification-footer">
                            <a href="notifications.php" class="view-all-link">View all notifications</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-profile" id="userProfile">
                        <div class="user-avatar-wrapper">
                            <div class="user-avatar" style="background: <?php echo $avatarColor; ?>">
                                <?php if ($profilePicture): ?>
                                    <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <?php echo $avatarInitials; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($unreadMessageCount > 0): ?>
                                <span class="message-dot" id="messageDot" style="display: block;"></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="user-title"><?php echo htmlspecialchars($userTitle); ?></div>
                        </div>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <a href="dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="dashboard.php?tab=my-profile" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                            <a href="dashboard.php?tab=my-bugs" class="dropdown-item">
                                <i class="fas fa-bug"></i>
                                <span>My Bugs</span>
                            </a>
                            <a href="chat.php" class="dropdown-item">
                                <i class="fas fa-envelope"></i>
                                <span>Messages</span>
                                <?php if ($unreadMessageCount > 0): ?>
                                    <span class="message-counter"><?php echo $unreadMessageCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="?logout=true" class="dropdown-item logout-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Auth buttons (shown when user is not logged in) -->
                <div class="auth-buttons">
                    <a href="auth.php" class="btn btn-outline">Login</a>
                    <a href="auth.php" class="btn btn-primary">Sign Up</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!--
    Mobile Bottom Navigation
    <div class="mobile-bottom-nav">
        <div class="bottom-nav-left">
            <a href="dashboard.php?tab=my-bugs" class="bottom-nav-item">
                <i class="fas fa-bug"></i>
                <span>Bugs</span>
            </a>
            <a href="dashboard.php?tab=solutions-tab" class="bottom-nav-item">
                <i class="fas fa-code"></i>
                <span>Solutions</span>
            </a>
        </div>
        <a href="dashboard.php?tab=report-tab" class="bottom-nav-center">
            <i class="fas fa-plus"></i>
        </a>
        <div class="bottom-nav-right">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php?tab=my-profile" class="bottom-nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="dashboard.php" class="bottom-nav-item">
                    <div class="user-avatar" style="background: <?php echo $avatarColor; ?>">
                        <?php if ($profilePicture): ?><img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile"><?php else: ?><?php echo $avatarInitials; ?><?php endif; ?>
                    </div>
                    <span>You</span>
                </a>
            <?php else: ?>
                <a href="auth.php" class="bottom-nav-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    -->
    
    <script>
        // Toggle dropdown menu
        const userProfile = document.getElementById('userProfile');
        const dropdownMenu = document.getElementById('dropdownMenu');
        
        if (userProfile && dropdownMenu) {
            userProfile.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
                // Hide message dot when dropdown is opened
                const messageDot = document.getElementById('messageDot');
                if (messageDot && dropdownMenu.classList.contains('show')) {
                    messageDot.style.display = 'none';
                }
            });
        }

        // Toggle mobile menu
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mainNav = document.getElementById('mainNav');

        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenuBtn.classList.toggle('active');
                mainNav.classList.toggle('active');
            });
        }
        
        // Toggle notification modal
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationModal = document.getElementById('notificationModal');
        
        if (notificationBtn && notificationModal) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationModal.classList.toggle('show');
                dropdownMenu.classList.remove('show');
                
                // Mark notifications as read when opening
                if (notificationModal.classList.contains('show')) {
                    markNotificationsAsRead();
                }
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (dropdownMenu.classList.contains('show') && !userProfile.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
            
            if (notificationModal.classList.contains('show') && !notificationBtn.contains(e.target) && !notificationModal.contains(e.target)) {
                notificationModal.classList.remove('show');
            }

            if (mainNav.classList.contains('active') && !mainNav.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                mainNav.classList.remove('active');
                mobileMenuBtn.classList.remove('active');
            }
        });
        
        // Mark all notifications as read
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                markNotificationsAsRead();
            });
        } 
        
        // Clear notifications
        const clearNotificationsBtn = document.getElementById('clearNotifications');
        if (clearNotificationsBtn) {
            clearNotificationsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearNotifications();
            });
        }
        
        // Notification item click handler
        const notificationItems = document.querySelectorAll('.notification-item');
        notificationItems.forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                const link = this.querySelector('a').href;
                if (!this.classList.contains('read')) {
                    markNotificationAsRead(notificationId, false); // Mark as read but don't prevent redirect
                }
                window.location.href = link;
            });
        });
        
        // Function to mark ALL notifications as read
        function markNotificationsAsRead() {
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ markAll: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                    });
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Function to mark single notification as read
        function markNotificationAsRead(notificationId, shouldPreventRedirect = true) {
            if (shouldPreventRedirect) event.preventDefault();
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notificationId: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        
                        // Update badge count
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            if (currentCount > 1) {
                                badge.textContent = currentCount - 1;
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Function to clear all notifications
        function clearNotifications() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                fetch('clear_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        document.querySelector('.notification-list').innerHTML = `
                            <li class="no-notifications">
                                <i class="far fa-bell"></i>
                                <p>No notifications yet</p>
                            </li>
                        `;
                        document.querySelector('.notification-badge').style.display = 'none';
                        document.querySelector('.notification-footer').style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
        
        // Button hover effects
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Universal page loader
        window.addEventListener('load', function() {
            const loader = document.getElementById('universal-loader');
            if (loader) {
                loader.style.opacity = '0';
                // Wait for the transition to finish before setting display to none
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500); // Matches the CSS transition duration
            }
        });
    </script>
</body>
</html>
