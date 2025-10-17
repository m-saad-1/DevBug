<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Store form data in session in case of error
$_SESSION['bug_form'] = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $code_snippet = trim($_POST['code_snippet'] ?? '');
    $code_language = trim($_POST['code_language'] ?? '');
    $tags = trim($_POST['tags']);
    $priority = trim($_POST['priority']);
    $environment = trim($_POST['environment'] ?? '');
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($tags) || empty($priority)) {
        header("Location: dashboard.php?tab=report-tab&error=Please fill in all required fields");
        exit();
    }
    
    // Validate title length
    if (strlen($title) < 10 || strlen($title) > 200) {
        header("Location: dashboard.php?tab=report-tab&error=Title must be between 10 and 200 characters");
        exit();
    }
    
    // Validate description length
    if (strlen($description) < 20) {
        header("Location: dashboard.php?tab=report-tab&error=Description must be at least 20 characters");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert bug into database
        $stmt = $pdo->prepare("
            INSERT INTO bugs (user_id, title, description, code_snippet, tags, severity, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'open')
        ");
        
        $stmt->execute([
            $user_id, $title, $description, $code_snippet, $tags, $priority
        ]);
        
        $bug_id = $pdo->lastInsertId();
        
        // Handle file uploads
        if (!empty($_FILES['attachments']['name'][0])) {
            $upload_dir = 'uploads/bugs/' . $bug_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_count = count($_FILES['attachments']['name']);
            $uploaded_files = [];
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = basename($_FILES['attachments']['name'][$i]);
                    $file_size = $_FILES['attachments']['size'][$i];
                    $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Validate file size (5MB max)
                    if ($file_size > 5242880) {
                        throw new Exception("File $file_name is too large. Maximum size is 5MB.");
                    }
                    
                    // Validate file type
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'txt', 'log', 'zip'];
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("File type $file_type is not allowed.");
                    }
                    
                    // Generate unique filename
                    $unique_name = uniqid() . '_' . $file_name;
                    $file_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert file record into database
                        $file_stmt = $pdo->prepare("
                            INSERT INTO bug_attachments (bug_id, file_name, file_path, file_type, file_size) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $file_stmt->execute([$bug_id, $file_name, $file_path, $file_type, $file_size]);
                    }
                }
            }
        }
        
        // Process tags - create any new tags and link them to the bug
        $tag_array = array_map('trim', explode(',', $tags));
        $tag_array = array_filter($tag_array);
        $tag_array = array_unique($tag_array);
        
        foreach ($tag_array as $tag_name) {
            if (!empty($tag_name)) {
                // Check if tag exists
                $tag_stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $tag_stmt->execute([$tag_name]);
                $tag = $tag_stmt->fetch();
                
                if ($tag) {
                    $tag_id = $tag['id'];
                } else {
                    // Create new tag
                    $tag_stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                    $slug = strtolower(str_replace(' ', '-', $tag_name));
                    $tag_stmt->execute([$tag_name, $slug]);
                    $tag_id = $pdo->lastInsertId();
                }
                
                // Link tag to bug
                $link_stmt = $pdo->prepare("INSERT INTO bug_tags (bug_id, tag_id) VALUES (?, ?)");
                $link_stmt->execute([$bug_id, $tag_id]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear saved form data
        unset($_SESSION['bug_form']);
        
        // Update user reputation for reporting a bug
        $rep_stmt = $pdo->prepare("UPDATE users SET reputation = reputation + 5 WHERE id = ?");
        $rep_stmt->execute([$user_id]);
        $_SESSION['reputation'] += 5;
        
        // Redirect to success page
        header("Location: dashboard.php?tab=report-tab&bug_success=1&bug_id=" . $bug_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        header("Location: dashboard.php?tab=report-tab&error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}