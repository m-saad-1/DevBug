<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $title = trim($_POST['title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $github = trim($_POST['github'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $avatar_color = trim($_POST['avatar_color'] ?? '#6366f1');
    $skills = trim($_POST['skills'] ?? '');
    
    // Email preferences
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $email_solutions = isset($_POST['email_solutions']) ? 1 : 0;
    $email_comments = isset($_POST['email_comments']) ? 1 : 0;
    $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;

    try {
        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: dashboard.php?tab=profile-tab&error=Username or email already exists");
            exit();
        }
        
        // Update user profile
        $stmt = $pdo->prepare("
            UPDATE users SET 
                name = ?, username = ?, email = ?, title = ?, bio = ?, 
                location = ?, company = ?, website = ?, github = ?, 
                twitter = ?, linkedin = ?, avatar_color = ?, skills = ?,
                email_notifications = ?, email_solutions = ?, 
                email_comments = ?, email_newsletter = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $username, $email, $title, $bio, 
            $location, $company, $website, $github, 
            $twitter, $linkedin, $avatar_color, $skills,
            $email_notifications, $email_solutions, 
            $email_comments, $email_newsletter,
            $user_id
        ]);
        
        // Update session variables
        $_SESSION['user_name'] = $name;
        $_SESSION['username'] = $username;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_title'] = $title;
        $_SESSION['bio'] = $bio;
        $_SESSION['location'] = $location;
        $_SESSION['company'] = $company;
        $_SESSION['website'] = $website;
        $_SESSION['github'] = $github;
        $_SESSION['twitter'] = $twitter;
        $_SESSION['linkedin'] = $linkedin;
        $_SESSION['avatar_color'] = $avatar_color;
        $_SESSION['skills'] = $skills;
        $_SESSION['email_notifications'] = $email_notifications;
        $_SESSION['email_solutions'] = $email_solutions;
        $_SESSION['email_comments'] = $email_comments;
        $_SESSION['email_newsletter'] = $email_newsletter;
        
        header("Location: dashboard.php?tab=profile-tab&profile_updated=1");
        exit();
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: dashboard.php?tab=profile-tab&error=Database error occurred");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}