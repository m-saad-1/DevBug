<?php
// bug_submit.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Database connection
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bug_title'])) {
    $title = trim($_POST['bug_title']);
    $description = trim($_POST['bug_description']);
    $code_snippet = trim($_POST['bug_code'] ?? '');
    $tags = trim($_POST['bug_tags'] ?? '');
    $priority = trim($_POST['bug_priority'] ?? 'medium');
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($tags)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: dashboard.php?tab=report-tab");
        exit();
    }
    
    try {
        // Insert bug into database
        $stmt = $pdo->prepare("
            INSERT INTO bugs (user_id, title, description, code_snippet, tags, priority, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $title, $description, $code_snippet, $tags, $priority]);
        
        $bug_id = $pdo->lastInsertId();
        
        // Handle file uploads if any
        $image_paths = [];
        $file_paths = [];
        
        // Create uploads directory if it doesn't exist
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        if (!file_exists('uploads/images')) {
            mkdir('uploads/images', 0777, true);
        }
        if (!file_exists('uploads/files')) {
            mkdir('uploads/files', 0777, true);
        }
        
        // Process image uploads
        if (!empty($_FILES['bug_images']['name'][0])) {
            foreach ($_FILES['bug_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['bug_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = bin2hex(random_bytes(8)) . '_' . basename($_FILES['bug_images']['name'][$key]);
                    $target_path = 'uploads/images/' . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $image_paths[] = $target_path;
                    }
                }
            }
        }
        
        // Process file uploads
        if (!empty($_FILES['bug_files']['name'][0])) {
            foreach ($_FILES['bug_files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['bug_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = bin2hex(random_bytes(8)) . '_' . basename($_FILES['bug_files']['name'][$key]);
                    $target_path = 'uploads/files/' . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $file_paths[] = [
                            'path' => $target_path,
                            'original_name' => $_FILES['bug_files']['name'][$key]
                        ];
                    }
                }
            }
        }
        
        // Insert images into database
        if (!empty($image_paths)) {
            $image_sql = "INSERT INTO bug_images (bug_id, image_path) VALUES (?, ?)";
            $image_stmt = $pdo->prepare($image_sql);
            
            foreach ($image_paths as $image_path) {
                $image_stmt->execute([$bug_id, $image_path]);
            }
        }
        
        // Insert files into database
        if (!empty($file_paths)) {
            $file_sql = "INSERT INTO bug_files (bug_id, file_path, original_name) VALUES (?, ?, ?)";
            $file_stmt = $pdo->prepare($file_sql);
            
            foreach ($file_paths as $file) {
                $file_stmt->execute([$bug_id, $file['path'], $file['original_name']]);
            }
        }
        
        // Update user reputation
        $rep_stmt = $pdo->prepare("UPDATE users SET reputation = reputation + 5 WHERE id = ?");
        $rep_stmt->execute([$_SESSION['user_id']]);
        
        // Update session reputation
        $_SESSION['reputation'] += 5;
        
        // Set success message
        $_SESSION['success'] = "Bug reported successfully!";
        
        // Redirect to the bug post page with success
        header("Location: bug-post.php?id=" . $bug_id);
        exit();
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to submit bug: " . $e->getMessage();
        header("Location: dashboard.php?tab=report-tab");
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header("Location: dashboard.php");
    exit();
}
?>