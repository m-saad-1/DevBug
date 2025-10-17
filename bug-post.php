<?php
date_default_timezone_set('UTC');
// bug-post.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'config/database.php';

// Handle AJAX request for saving a bug FIRST
if (isset($_SESSION['user_id']) && isset($_POST['save_bug'])) {
    $bug_id = (int)$_POST['bug_id'];
    $save = $_POST['save'] === 'true';
    
    try {
        if ($save) {
            // Save bug
            $save_sql = "INSERT INTO user_bug_saves (user_id, bug_id) VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP";
            $save_stmt = $pdo->prepare($save_sql);
            $save_stmt->execute([$_SESSION['user_id'], $bug_id]);
        } else {
            // Remove saved bug
            $remove_sql = "DELETE FROM user_bug_saves WHERE user_id = ? AND bug_id = ?";
            $remove_stmt = $pdo->prepare($remove_sql);
            $remove_stmt->execute([$_SESSION['user_id'], $bug_id]);
        }
        
        // Send a clean JSON response and exit
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        // Send a JSON error response and exit
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// For non-AJAX requests, include the header and proceed with rendering the page
include(__DIR__ . '/Components/header.php');

// Include utility functions
require_once 'includes/utils.php';

// Get page number from URL, default to 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get filter parameters with proper initialization
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$technology = isset($_GET['technology']) ? $_GET['technology'] : '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.description LIKE ? OR b.tags LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status) && $status !== 'all') {
    $where_conditions[] = "b.status = ?";
    $params[] = $status;
}

if (!empty($priority) && $priority !== 'all') {
    $where_conditions[] = "b.priority = ?";
    $params[] = $priority;
}

if (!empty($technology) && $technology !== 'all') {
    $where_conditions[] = "b.tags LIKE ?";
    $params[] = "%$technology%";
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM bugs b $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_bugs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_bugs / $per_page);

// Get bugs with user info - FIXED: Removed u.username which doesn't exist
$bugs_sql = "SELECT b.*, u.name as user_name, u.profile_picture, u.avatar_color, u.id as user_id, u.title as user_title, u.profile_picture,
                    (SELECT COUNT(*) FROM solutions s WHERE s.bug_id = b.id) as solution_count,
                    (SELECT COUNT(*) FROM comments c WHERE c.bug_id = b.id) as comment_count
             FROM bugs b 
             LEFT JOIN users u ON b.user_id = u.id 
             $where_sql
             ORDER BY b.created_at DESC 
             LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

try {
    $stmt = $pdo->prepare($bugs_sql);
    $stmt->execute($params);
    $bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching bugs: " . $e->getMessage();
    $bugs = [];
}

// Get popular tags for filter suggestions
$tags_sql = "SELECT DISTINCT tags FROM bugs WHERE tags IS NOT NULL AND tags != ''";
$tags_stmt = $pdo->query($tags_sql);
$all_tags = [];
while ($row = $tags_stmt->fetch(PDO::FETCH_ASSOC)) {
    $tag_list = explode(',', $row['tags']);
    foreach ($tag_list as $tag) {
        $tag = trim($tag);
        if (!empty($tag) && !in_array($tag, $all_tags)) {
            $all_tags[] = $tag;
        }
    }
}
sort($all_tags);

// Get saved bugs for current user
$saved_bugs = [];
if (isset($_SESSION['user_id'])) {
    try {
        $saved_sql = "SELECT bug_id FROM user_bug_saves WHERE user_id = ?";
        $saved_stmt = $pdo->prepare($saved_sql);
        $saved_stmt->execute([$_SESSION['user_id']]);
        $saved_bugs = $saved_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching saved bugs: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bug Posts - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
         /* Page Header */
             .page-header {
            padding: 80px 0 20px;
            text-align: center;
        }
        .page-header h1 {
            font-size: 2.8rem;
            margin-bottom: 16px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Notification Styles */
        .notification-toast {
            position: fixed;
            top: 100px; /* Position below the sticky header */
            right: 20px;
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 16px 24px;
            border-radius: 8px;
            border: 1px solid var(--border);
            border-left: 4px solid var(--success);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(120%);
            opacity: 0;
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.5s ease;
        }
        .notification-toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .notification-toast i {
            color: var(--success);
            font-size: 1.4rem;
        }

        /* Filters and Search */
        .filters-section {
            background: var(--bg-secondary);
            padding: 30px 0;
            margin-bottom: 40px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 14px 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 1rem;
            padding-left: 45px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .filter-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-width: 150px;
        }

        /* Bug Posts */
        .bugs-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 60px;
        }

        .bug-card {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
            display: flex;
        }

        .bug-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .bug-status {
            width: 6px;
            flex-shrink: 0;
        }

        .status-open {
            background: var(--success);
        }

        .status-in-progress {
            background: var(--warning);
        }

        .status-solved {
            background: var(--accent-primary);
        }

        .status-closed {
            background: var(--accent-secondary);
        }

        .bug-content {
            flex: 1;
            padding: 25px;
        }

        .bug-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .bug-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .bug-title a {
            color: var(--text-primary);
            text-decoration: none;
        }

        .bug-title a:hover {
            color: var(--accent-primary);
            text-decoration: none;
        }

        .bug-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .severity {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .severity-low {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .severity-medium {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .severity-high {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .severity-critical {
            background: rgba(239, 68, 68, 0.3);
            color: var(--danger);
            font-weight: 700;
        }

        .bug-description {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .bug-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-decoration: none;
        }

        .tag:hover {
            color: var(--accent-primary);
            text-decoration: none;
        }

        .tag.js {
            background: rgba(247, 223, 30, 0.15);
            color: #f7df1e;
            border-color: rgba(247, 223, 30, 0.3);
        }

        .tag.php {
            background: rgba(119, 123, 179, 0.15);
            color: #777bb3;
            border-color: rgba(119, 123, 179, 0.3);
        }

        .tag.python {
            background: rgba(53, 114, 165, 0.15);
            color: #3572a5;
            border-color: rgba(53, 114, 165, 0.3);
        }

        .tag.react {
            background: rgba(97, 218, 251, 0.15);
            color: #61dafb;
            border-color: rgba(97, 218, 251, 0.3);
        }

        .tag.node {
            background: rgba(131, 205, 41, 0.15);
            color: #83cd29;
            border-color: rgba(131, 205, 41, 0.3);
        }

        .tag.java {
            background: rgba(237, 139, 0, 0.15);
            color: #ed8b00;
            border-color: rgba(237, 139, 0, 0.3);
        }

        .bug-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .user-name:hover {
            color: var(--accent-primary);
        }

        .post-time {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .bug-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 38px; /* Set a fixed height */
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            text-decoration: none;
        }

        .action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
            z-index: 1;
        }

        .action-btn.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        /* Code Snippet Preview */
        .code-preview {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            overflow: hidden; 
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            border: 1px solid var(--border);
            max-height: 150px;
            overflow-y: hidden;
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }

        .code-preview pre,
        .code-preview pre code.hljs {
            background: transparent !important;
            width: 100%;
            overflow-x: auto;
        }

        .code-preview:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            pointer-events: none;
        }

        .view-full-code {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: var(--accent-primary);
            font-size: 0.9rem;
            text-decoration: none;
        }

        .view-full-code:hover {
            text-decoration: underline;
        }

        /* Image Gallery */
        .bug-images {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }

        .bug-image {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: var(--transition);
        }

        .bug-image:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* File Attachments */
        .file-attachments {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }

        .file-attachment {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .file-attachment:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
        }

        .file-icon {
            font-size: 1.2rem;
        }

        .file-name {
            font-size: 0.9rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 40px 0;
        }

        .pagination-btn {
            padding: 10px 16px;
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .pagination-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
        }

        .pagination-btn.active {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        /* No bugs message */
        .no-bugs {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .no-bugs i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--accent-primary);
        }

        .no-bugs h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        /* Error message */
        .error-message {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            text-align: center;
        }

        /* Image Modal Styles from post-details.php */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            max-width: 95%;
            max-height: 95%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .modal-image {
            max-width: 100%;
            max-height: calc(100vh - 100px);
            border-radius: 8px;
            object-fit: contain;
        }

        .modal-controls {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            align-items: center;
        }

        .close-modal {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .close-modal:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .image-counter {
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .bug-image {
            cursor: pointer;
        }

        /* ========== RESPONSIVE DESIGN ========== */
        
        /* 1024px Breakpoint */
        @media (max-width: 1024px) {
            .page-header {
                padding: 60px 0 15px;
            }
            
            .page-header h1 {
                font-size: 2.4rem;
            }
            
            .page-header p {
                font-size: 1.1rem;
            }
            
            .filters-section {
                padding: 25px 0;
                margin-bottom: 30px;
            }
            
            .search-box input {
                padding: 12px 18px;
                font-size: 0.95rem;
            }
            
            .filter-select {
                padding: 11px 14px;
                font-size: 0.95rem;
                min-width: 140px;
            }
            
            .bug-content {
                padding: 22px;
            }
            
            .bug-title {
                font-size: 1.3rem;
            }
            
            .bug-description {
                font-size: 0.95rem;
            }
            
            .code-preview {
                padding: 14px;
                font-size: 0.85rem;
            }
            
            .action-btn {
                padding: 7px 11px;
                font-size: 0.85rem;
            }
            
            .user-avatar {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }
            
            .tag {
                padding: 5px 12px;
                font-size: 0.8rem;
            }
            
            .bug-image {
                width: 140px;
                height: 140px;
            }
            
            .file-attachment {
                padding: 9px;
                font-size: 0.85rem;
            }
        }

        /* 768px Breakpoint */
        @media (max-width: 768px) {
            .page-header {
                padding: 50px 0 12px;
            }
            
            .page-header h1 {
                font-size: 2.1rem;
            }
            
            .page-header p {
                font-size: 1rem;
                max-width: 500px;
            }
            
            .filters-section {
                padding: 20px 0;
                margin-bottom: 25px;
            }
            
            .filters-container {
                gap: 15px;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .search-box input {
                padding: 11px 16px;
                font-size: 0.9rem;
            }
            
            .filter-options {
                width: 100%;
                gap: 12px;
            }
            
            .filter-select {
                padding: 10px 12px;
                font-size: 0.9rem;
                min-width: 130px;
                flex: 1;
            }
            
            .bug-content {
                padding: 18px;
            }
            
            .bug-title {
                font-size: 1.2rem;
            }
            
            .bug-header {
                flex-direction: column;
                gap: 12px;
            }
            
            .bug-meta {
                gap: 12px;
            }
            
            .meta-item {
                font-size: 0.85rem;
            }
            
            .bug-description {
                font-size: 0.9rem;
                margin-bottom: 18px;
            }
            
            .code-preview {
                padding: 12px;
                font-size: 0.8rem;
                max-height: 130px;
            }
            
            .bug-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .bug-actions {
                width: 100%;
            }
            
            .action-btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }
            
            .tag {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
            
            .bug-image {
                width: 120px;
                height: 120px;
            }
            
            .file-attachment {
                padding: 8px;
                font-size: 0.8rem;
            }
            
            .pagination-btn {
                padding: 9px 14px;
                font-size: 0.9rem;
            }
        }

        /* 600px Breakpoint */
        @media (max-width: 600px) {
            .page-header {
                padding: 40px 0 10px;
            }
            
            .page-header h1 {
                font-size: 1.9rem;
            }
            
            .page-header p {
                font-size: 0.95rem;
                max-width: 400px;
            }
            
            .filters-section {
                padding: 18px 0;
                margin-bottom: 20px;
            }
            
           
            .filter-select {
                min-width: auto;
                width: 100%;
            }
            
            
            
            .bug-content {
                padding: 16px;
            }
            
            .bug-title {
                font-size: 1.1rem;
            }
            
            .bug-meta {
                gap: 10px;
            }
            
            .meta-item {
                font-size: 0.8rem;
            }
            
            .severity {
                font-size: 0.75rem;
                padding: 3px 8px;
            }
            
            .bug-description {
                font-size: 0.85rem;
                margin-bottom: 16px;
            }
            
            .code-preview {
                padding: 10px;
                font-size: 0.75rem;
                max-height: 120px;
                margin: 12px 0;
            }
            
            .view-full-code {
                font-size: 0.8rem;
            }
            
            .bug-tags {
                gap: 8px;
                margin-bottom: 16px;
            }
            
            .tag {
                padding: 3px 8px;
                font-size: 0.7rem;
            }
            
            .bug-footer {
                gap: 10px;
            }
            
            .user-info {
                gap: 10px;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }
            
            .user-name {
                font-size: 0.85rem;
            }
            
            .user-title {
                font-size: 0.75rem;
            }
            
          
            
            .action-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
                gap: 4px;
            }
            
            .bug-image {
                width: 100px;
                height: 100px;
            }
            
            .file-attachment {
                padding: 6px;
                font-size: 0.75rem;
                gap: 6px;
            }
            
            .file-icon {
                font-size: 1rem;
            }
            
            .pagination {
                gap: 8px;
                margin: 30px 0;
            }
            
            .pagination-btn {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
        }

        /* 500px Breakpoint */
        @media (max-width: 500px) {
            .page-header {
                padding: 30px 0 8px;
            }
            
            .page-header h1 {
                font-size: 1.7rem;
            }
            
            .page-header p {
                font-size: 0.9rem;
                max-width: 350px;
            }
            
            .filters-section {
                padding: 15px 0;
                margin-bottom: 15px;
            }
            
            .filter-options {
                grid-template-columns: 3fr;
                gap: 8px;
            }
            
            .btn {
                grid-column: span 1;
            }
            
            .search-box input {
                padding: 10px 14px;
                font-size: 0.85rem;
                padding-left: 40px;
            }
            
            .search-box i {
                left: 12px;
                font-size: 0.9rem;
            }
            
            .filter-select {
                padding: 9px 10px;
                font-size: 0.85rem;
            }
            
            .bug-card {
                flex-direction: column;
            }
            
            .bug-status {
                width: 100%;
                height: 5px;
            }
            
            
            .bug-content {
                padding: 14px;
            }
            
            .bug-title {
                font-size: 1rem;
                margin-bottom: 8px;
            }
            
            .bug-meta {
                gap: 8px;
                margin-bottom: 12px;
            }
            
            .meta-item {
                font-size: 0.75rem;
            }
            
            .bug-description {
                font-size: 0.8rem;
                margin-bottom: 14px;
                line-height: 1.5;
            }
            
            .code-preview {
                padding: 8px;
                font-size: 0.7rem;
                max-height: 100px;
                margin: 10px 0;
            }
            
            .view-full-code {
                font-size: 0.75rem;
            }
            
            .bug-tags {
                gap: 6px;
                margin-bottom: 14px;
            }
            
            .tag {
                padding: 2px 6px;
                font-size: 0.65rem;
            }
            
            .bug-footer {
                gap: 8px;
            }
            
            .user-info {
                gap: 8px;
            }
            
            .user-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }
            
            .user-details {
                display: none;
            }
            
            .bug-actions {
                width: 100%;
            }
            
            .action-btn {
                padding: 5px 8px;
                font-size: 0.75rem;
                flex: 1;
                justify-content: center;
            }
            
            .bug-image {
                width: 80px;
                height: 80px;
            }
            
            .file-attachments {
                gap: 6px;
            }
            
            .file-attachment {
                padding: 5px;
                font-size: 0.7rem;
                gap: 4px;
            }
            
            .file-icon {
                font-size: 0.9rem;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 6px;
                margin: 25px 0;
            }
            
            .pagination-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .no-bugs {
                padding: 40px 15px;
            }
            
            .no-bugs i {
                font-size: 2.5rem;
                margin-bottom: 15px;
            }
            
            .no-bugs h3 {
                font-size: 1.3rem;
            }
            
            .no-bugs p {
                font-size: 0.9rem;
            }
        }

        /* Additional mobile optimizations */
        @media (max-width: 400px) {
            .bug-image {
                width: 70px;
                height: 70px;
            }
            
            .action-btn span {
                display: none;
            }
            
            .action-btn {
                padding: 8px;
            }
            
            .pagination-btn {
                padding: 5px 8px;
                font-size: 0.75rem;
            }
        }

        /* Ensure code snippets don't shift or hide */
        .code-preview-container {
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .code-preview pre {
            min-width: min-content;
            margin: 0;
        }

        /* Fix for action buttons on very small screens */
        @media (max-width: 360px) {
            .bug-actions {
                flex-wrap: nowrap;
            }
            
            .action-btn {
                min-width: auto;
            }
            
            .action-btn i {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Bug Reports</h1>
            <p>Browse through the latest bugs reported by our developer community</p>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="filters-section">
        <div class="container">
            <form method="GET" action="bug-post.php">
                <div class="filters-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search bugs by title, description, or tags..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-options">
                        <select class="filter-select" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in-progress" <?php echo $status === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="solved" <?php echo $status === 'solved' ? 'selected' : ''; ?>>Solved</option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                        <select class="filter-select" name="priority">
                            <option value="all" <?php echo $priority === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $priority === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                        <select class="filter-select" name="technology">
                            <option value="all" <?php echo $technology === 'all' ? 'selected' : ''; ?>>All Technologies</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $technology === $tag ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tag); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="bug-post.php" class="btn" style="background: var(--bg-secondary); color: var(--text-primary);">Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Bug Posts -->
    <main class="container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bugs-container">
            <?php if (!empty($bugs)): ?>
                <?php foreach ($bugs as $bug): 
                    // Check if this bug is saved by the current user
                    $is_saved = in_array($bug['id'], $saved_bugs);
                    
                    // Get bug images
                    $bug_images = [];
                    try {
                        $image_stmt = $pdo->prepare("SELECT image_path FROM bug_images WHERE bug_id = ?");
                        $image_stmt->execute([$bug['id']]);
                        $bug_images = $image_stmt->fetchAll(PDO::FETCH_COLUMN);
                    } catch (PDOException $e) {
                        error_log("Error fetching bug images: " . $e->getMessage());
                    }
                    
                    // Get bug files
                    $bug_files = [];
                    try {
                        $file_stmt = $pdo->prepare("SELECT file_path, original_name FROM bug_files WHERE bug_id = ?");
                        $file_stmt->execute([$bug['id']]);
                        $bug_files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Error fetching bug files: " . $e->getMessage());
                    }
                ?>
                    <div class="bug-card">
                        <div class="bug-status status-<?php echo str_replace(' ', '-', strtolower($bug['status'])); ?>"></div>
                        <div class="bug-content">
                            <div class="bug-header">
                                <div>
                                    <h2 class="bug-title">
                                        <a href="post-details.php?id=<?php echo $bug['id']; ?>">
                                            <?php echo htmlspecialchars($bug['title']); ?>
                                        </a>
                                    </h2>
                                    <div class="bug-meta">
                                        <?php if (isset($bug['priority'])): ?>
                                        <span class="severity severity-<?php echo strtolower($bug['priority']); ?>">
                                            <?php echo ucfirst($bug['priority']); ?> Priority
                                        </span>
                                        <?php endif; ?>
                                        <span class="meta-item"><i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($bug['created_at'])); ?></span>
                                        <span class="meta-item"><i class="far fa-eye"></i> <?php echo $bug['views'] ?? 0; ?> views</span>
                                    </div>
                                </div>
                            </div>
                            <p class="bug-description">
                                <?php 
                                $description = htmlspecialchars($bug['description']);
                                if (strlen($description) > 200) {
                                    echo substr($description, 0, 200) . '...';
                                } else {
                                    echo $description;
                                }
                                ?>
                            </p>
                            
                            <?php if (!empty($bug['code_snippet'])): ?>
                            <div class="code-preview-container">
                                <div class="code-preview">
                                    <pre><code class="language-<?php echo detectLanguage($bug['tags']); ?>"><?php echo htmlspecialchars(substr($bug['code_snippet'], 0, 500)); ?></code></pre>
                                </div>
                            </div>
                            <a href="post-details.php?id=<?php echo $bug['id']; ?>#code" class="view-full-code">View full code snippet</a>
                            <?php endif; ?>
                            
                            <?php if (!empty($bug_files)):
                            ?><h4 style="margin-top: 20px;">Attachments</h4>
                            <div class="file-attachments">
                                <?php foreach ($bug_files as $file): ?>
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="file-attachment">
                                        <i class="fas fa-file file-icon"></i>
                                        <span class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($bug_images)):
                            ?>
                            <div class="bug-images">
                                <?php foreach ($bug_images as $image_path): ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Bug screenshot" class="bug-image">
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($bug['tags'])): ?>
                            <div class="bug-tags">
                                <?php 
                                $tags = explode(',', $bug['tags']);
                                foreach ($tags as $tag):
                                    $tag = trim($tag);
                                    if (!empty($tag)):
                                ?>
                                    <a href="bug-post.php?search=<?php echo urlencode($tag); ?>" class="tag <?php echo strtolower($tag); ?>"><?php echo htmlspecialchars($tag); ?></a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            <?php endif; ?>
                            <div class="bug-footer">
                                <div class="user-info">
                                    <a href="profile.php?id=<?php echo $bug['user_id']; ?>" class="user-avatar" style="background-color: <?php echo $bug['avatar_color'] ?? '#6366f1'; ?>">
                                        <?php if (!empty($bug['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($bug['profile_picture']); ?>" alt="<?php echo htmlspecialchars($bug['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($bug['user_name'], 0, 2)); ?>
                                        <?php endif; ?>
                                    </a>
                                    <div class="user-details">
                                        <a href="profile.php?id=<?php echo $bug['user_id']; ?>" class="user-name"><?php echo htmlspecialchars($bug['user_name']); ?></a>
                                        <span class="user-title"><?php echo htmlspecialchars($bug['user_title'] ?? 'Developer'); ?></span>
                                    </div>
                                </div>
                                <div class="bug-actions">
                                    <a href="post-details.php?id=<?php echo $bug['id']; ?>#comments" class="action-btn">
                                        <i class="far fa-comment"></i> <span><?php echo $bug['comment_count']; ?></span>
                                    </a>
                                    <a href="post-details.php?id=<?php echo $bug['id']; ?>#solutions" class="action-btn">
                                        <i class="far fa-lightbulb"></i> <span><?php echo $bug['solution_count']; ?></span>
                                    </a>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="action-btn save-btn <?php echo $is_saved ? 'active' : ''; ?>" 
                                            data-bug-id="<?php echo $bug['id']; ?>">
                                        <i class="<?php echo $is_saved ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                                        <span><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
                                    </button>
                                    <?php else: ?>
                                    <a href="auth.php" class="action-btn">
                                        <i class="far fa-bookmark"></i> <span>Save</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-bugs">
                    <i class="fas fa-bug"></i>
                    <h3>No bugs found</h3>
                    <p><?php echo empty($search) && empty($status) && empty($priority) && empty($technology) 
                        ? 'There are no bugs reported yet. Be the first to report a bug!' 
                        : 'There are no bugs matching your search criteria. Try adjusting your filters or search term.'; ?>
                    </p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php?tab=report-tab" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Report a Bug
                        </a>
                    <?php else: ?>
                        <a href="auth.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-sign-in-alt"></i> Sign In to Report a Bug
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="bug-post.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($priority) ? '&priority=' . $priority : ''; ?><?php echo !empty($technology) ? '&technology=' . urlencode($technology) : ''; ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                $start_page = max(1, $end_page - 4);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="bug-post.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($priority) ? '&priority=' . $priority : ''; ?><?php echo !empty($technology) ? '&technology=' . urlencode($technology) : ''; ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="bug-post.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($priority) ? '&priority=' . $priority : ''; ?><?php echo !empty($technology) ? '&technology=' . urlencode($technology) : ''; ?>" class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Enhanced Image Modal -->
    <div id="imageModal" class="image-modal">
        <div class="modal-content">
            <img id="modalImage" src="" alt="" class="modal-image">
            <div class="modal-controls">
                <span class="image-counter" id="imageCounter"></span>
                <button id="closeModal" class="close-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Notification Toast for New Bug -->
    <?php if (isset($_GET['new_bug']) && $_GET['new_bug'] == '1'): ?>
        <div id="success-notification" class="notification-toast">
            <i class="fas fa-check-circle"></i>
            <span>Bug reported successfully!</span>
        </div>
    <?php endif; ?>


    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize syntax highlighting
            hljs.highlightAll();
            
            // Image modal functionality
            let currentImageIndex = 0;
            let currentImages = [];

            function openImageModal(imageSrc, index, images) {
                const modal = document.getElementById('imageModal');
                const modalImage = document.getElementById('modalImage');
                const imageCounter = document.getElementById('imageCounter');
                
                currentImageIndex = index;
                currentImages = images;
                modalImage.src = imageSrc;
                imageCounter.textContent = `Image ${currentImageIndex + 1} of ${currentImages.length}`;
                modal.style.display = 'flex';
                
                document.body.style.overflow = 'hidden';
            }

            function closeImageModal() {
                const modal = document.getElementById('imageModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            document.getElementById('closeModal').addEventListener('click', closeImageModal);

            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImageModal();
                }
            });

            // Set up image click handlers for bug images
            document.querySelectorAll('.bug-image').forEach((img) => {
                img.addEventListener('click', function() {
                    const bugCard = this.closest('.bug-card');
                    const imagesInCard = Array.from(bugCard.querySelectorAll('.bug-image'));
                    const imageSources = imagesInCard.map(i => i.src);
                    const clickedIndex = imagesInCard.indexOf(this);
                    
                    openImageModal(this.src, clickedIndex, imageSources);
                });
            });

            // Keyboard navigation for image modal
            document.addEventListener('keydown', function(e) {
                const modal = document.getElementById('imageModal');
                if (modal.style.display === 'flex') {
                    if (e.key === 'Escape') {
                        closeImageModal();
                    } else if (e.key === 'ArrowLeft') {
                        currentImageIndex = (currentImageIndex - 1 + currentImages.length) % currentImages.length;
                        openImageModal(currentImages[currentImageIndex], currentImageIndex, currentImages);
                    } else if (e.key === 'ArrowRight') {
                        currentImageIndex = (currentImageIndex + 1) % currentImages.length;
                        openImageModal(currentImages[currentImageIndex], currentImageIndex, currentImages);
                    }
                }
                
                // Close code modal with escape key
                const codeModal = document.getElementById('codeModal');
                if (codeModal && codeModal.style.display === 'flex' && e.key === 'Escape') {
                    closeCodeModal();
                }
            });


            // Save bug functionality
            const saveButtons = document.querySelectorAll('.save-btn');
            saveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const bugId = this.getAttribute('data-bug-id');
                    const isCurrentlySaved = this.classList.contains('active');
                    
                    // Optimistic UI update
                    this.classList.toggle('active');
                    const icon = this.querySelector('i');
                    const text = this.querySelector('span');
                    
                    if (this.classList.contains('active')) {
                        icon.className = 'fas fa-bookmark';
                        if (text) text.textContent = 'Saved';
                    } else {
                        icon.className = 'far fa-bookmark';
                        if (text) text.textContent = 'Save';
                    }
                    
                    // Send AJAX request
                    fetch('bug-post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `save_bug=true&bug_id=${bugId}&save=${!isCurrentlySaved}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            // Revert UI if failed
                            this.classList.toggle('active');
                            const icon = this.querySelector('i');
                            const text = this.querySelector('span');
                            
                            if (this.classList.contains('active')) {
                                icon.className = 'fas fa-bookmark';
                                if (text) text.textContent = 'Saved';
                            } else {
                                icon.className = 'far fa-bookmark';
                                if (text) text.textContent = 'Save';
                            }
                            
                            alert('Failed to update saved status: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revert UI
                        this.classList.toggle('active');
                        const icon = this.querySelector('i');
                        const text = this.querySelector('span');
                        
                        if (this.classList.contains('active')) {
                            icon.className = 'fas fa-bookmark';
                            if (text) text.textContent = 'Saved';
                        } else {
                            icon.className = 'far fa-bookmark';
                            if (text) text.textContent = 'Save';
                        }
                        
                        alert('Network error. Please try again.');
                    });
                });
            });

            // Button hover effects
            const buttons = document.querySelectorAll('.btn, .action-btn, .pagination-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Filter form submission enhancement
            const filterForm = document.querySelector('.filters-section form');
            if (filterForm) {
                const filterSelects = filterForm.querySelectorAll('select');
                filterSelects.forEach(select => {
                    select.addEventListener('change', function() {
                        filterForm.submit();
                    });
                });
            }

            // Handle the success notification toast
            const notification = document.getElementById('success-notification');
            if (notification) {
                // Show the notification
                setTimeout(() => {
                    notification.classList.add('show');
                }, 100);

                // Hide it after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    // Optional: remove from DOM after transition
                    setTimeout(() => notification.remove(), 600);
                }, 5000);
            }
        });
    </script>
</body>
</html>