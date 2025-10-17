<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('UTC');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// --- AJAX Handlers ---
// This block must be at the top of the file before any HTML output.
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Database connection is needed for AJAX handlers
    require_once 'config/database.php';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Handle solution voting
    if (isset($_POST['vote_solution'])) {
        header('Content-Type: application/json');
        $solution_id = (int)$_POST['solution_id'];
        $vote_type = $_POST['vote_type'] ?? 'up'; // Default to 'up' for old logic

        try {
            // First, check if the user is trying to vote on their own solution
            $solution_author_stmt = $pdo->prepare("SELECT user_id FROM solutions WHERE id = ?");
            $solution_author_stmt->execute([$solution_id]);
            $solution_author_id = $solution_author_stmt->fetchColumn();

            if ($user_id == $solution_author_id) {
                echo json_encode(['success' => false, 'error' => 'You cannot vote for your own solution.']);
                exit();
            }

            // Check for an existing vote
            $check_sql = "SELECT id, vote_type FROM solution_votes WHERE user_id = ? AND solution_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$user_id, $solution_id]);
            $existing_vote = $check_stmt->fetch();

            // Remove any existing vote
            if ($existing_vote) { // If a vote exists, remove it.
                $pdo->prepare("DELETE FROM solution_votes WHERE id = ?")->execute([$existing_vote['id']]);
            }
            
            // If the new vote is 'up' and there wasn't an existing 'up' vote, add it and update reputation.
            // This handles both toggling on a vote and switching from downvote to upvote.
            if ($vote_type === 'up' && (!$existing_vote || $existing_vote['vote_type'] !== 'up')) {
                $insert_sql = "INSERT INTO solution_votes (user_id, solution_id, vote_type) VALUES (?, ?, 'up')";
                $pdo->prepare($insert_sql)->execute([$user_id, $solution_id]); 
            } elseif ($vote_type === 'down' && (!$existing_vote || $existing_vote['vote_type'] !== 'down')) {
                $insert_sql = "INSERT INTO solution_votes (user_id, solution_id, vote_type) VALUES (?, ?, 'down')";
                $pdo->prepare($insert_sql)->execute([$user_id, $solution_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Vote submission failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
        }
        exit();
    }

    // Handle solution saving
    if (isset($_POST['save_solution'])) {
        header('Content-Type: application/json');
        $solution_id = (int)$_POST['solution_id'];
        $save = filter_var($_POST['save'], FILTER_VALIDATE_BOOLEAN);

        try {
            if ($save) {
                $save_sql = "INSERT INTO solution_saves (user_id, solution_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP";
                $pdo->prepare($save_sql)->execute([$user_id, $solution_id]);
            } else {
                $remove_sql = "DELETE FROM solution_saves WHERE user_id = ? AND solution_id = ?";
                $pdo->prepare($remove_sql)->execute([$user_id, $solution_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit();
    }
}

// For non-AJAX page loads, include the header and required files.
// The header itself also requires the database, but including it here ensures
// $pdo is available for the main page logic, regardless of header changes.
include(__DIR__ . '/Components/header.php');
require_once 'config/database.php';
require_once 'includes/utils.php'; // For timeAgo function

// Pagination settings
$solutions_per_page = 8;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $solutions_per_page;

// Handle search and filtering
$search = $_GET['search'] ?? '';
$technology = $_GET['technology'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

// Build query with filters - ONLY APPROVED SOLUTIONS
$query = "
    SELECT s.*, 
           u.name as user_name, 
           u.avatar_color, 
           u.profile_picture,
           u.title as user_title,
           b.title as bug_title,
           b.id as bug_id,
           b.tags as bug_tags,
           (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as votes,
           s.views as views_count,
           (SELECT COUNT(*) FROM solution_saves WHERE solution_id = s.id) as saves_count
    FROM solutions s 
    JOIN users u ON s.user_id = u.id 
    JOIN bugs b ON s.bug_id = b.id
    WHERE s.is_approved = 1
";

$count_query = "
    SELECT COUNT(*) as total
    FROM solutions s 
    JOIN users u ON s.user_id = u.id 
    JOIN bugs b ON s.bug_id = b.id
    WHERE s.is_approved = 1
";

$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (s.content LIKE :search OR b.title LIKE :search)";
    $count_query .= " AND (s.content LIKE :search OR b.title LIKE :search)";
    $params[':search'] = $count_params[':search'] = "%$search%";
}

if (!empty($technology) && $technology !== 'all') {
    $query .= " AND b.tags LIKE :technology";
    $count_query .= " AND b.tags LIKE :technology";
    $params[':technology'] = $count_params[':technology'] = "%$technology%";
}

// Add sorting
switch ($sort) {
    case 'popular':
        $query .= " ORDER BY (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') DESC, s.views DESC";
        break;
    case 'votes':
        $query .= " ORDER BY (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') DESC";
        break;
    case 'views':
        $query .= " ORDER BY s.views DESC";
        break;
    default:
        $query .= " ORDER BY s.updated_at DESC";
        break;
}

// Add pagination
$query .= " LIMIT :limit OFFSET :offset";

try {
    // Get total count
    $count_stmt = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_solutions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_solutions / $solutions_per_page);
    
    // Get paginated solutions
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $solutions_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching solutions: " . $e->getMessage();
    $solutions = [];
    $total_solutions = 0;
    $total_pages = 1;
}

// Get solution attachments and record views
foreach ($solutions as &$solution) {
    try {
        // Get solution images
        $image_stmt = $pdo->prepare("SELECT image_path FROM solution_images WHERE solution_id = ?");
        $image_stmt->execute([$solution['id']]);
        $solution['images'] = $image_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get solution files
        $file_stmt = $pdo->prepare("SELECT file_path, original_name FROM solution_files WHERE solution_id = ?");
        $file_stmt->execute([$solution['id']]);
        $solution['files'] = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching solution attachments: " . $e->getMessage());
    }
}
unset($solution); // Unset the reference to avoid issues in subsequent loops

// Get saved solutions for current user to display correct button state
$saved_solutions_ids = [];
if (isset($_SESSION['user_id'])) {
    try {
        $saved_sql = "SELECT solution_id FROM solution_saves WHERE user_id = ?";
        $saved_stmt = $pdo->prepare($saved_sql);
        $saved_stmt->execute([$_SESSION['user_id']]);
        $saved_solutions_ids = $saved_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching saved solutions: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solutions - DevBug</title>
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
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .filters-container .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .filters-container .search-box input {
            width: 100%;
            padding: 14px 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 1rem;
            padding-left: 45px;
        }

        .filters-container .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .filters-container .filter-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            align-items: center;
            flex-grow: 2;
            justify-content: flex-start;
        }

        .filter-select {
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-width: 140px;
            flex: 1;
        }

        .filters-container .filter-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .filters-container .filter-buttons .btn {
            white-space: nowrap;
            min-width: auto;
            padding: 12px 20px;
        }

        /* Solutions List */
        .solutions-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 60px;
        }

        .solution-card {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .solution-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .solution-header {
            padding: 25px 25px 15px;
            border-bottom: 1px solid var(--border);
        }

        .solution-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--text-primary);
            text-align: left;
        }

        .solution-title a {
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .solution-title a:hover {
            color: var(--accent-primary);
        }

        .solution-meta {
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

        .solution-for {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-decoration: none;
        }

        .solution-for:hover {
            background: rgba(99, 102, 241, 0.25);
        }

        .solution-body {
            padding: 25px;
            text-align: left;
        }

        .solution-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .solution-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .solution-user-details {
            flex: 1;
        }

        .solution-user-name {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            display: block;
            margin-bottom: 4px;
        }

        .solution-user-name:hover {
            color: var(--accent-primary);
        }

        .description-heading {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 5px;
        }

        .solution-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .code-preview {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            overflow-x: auto;
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
        .code-preview::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, and Opera */
        }
        .code-preview {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
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

        .code-snippet {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 16px;
            margin: 15px 0;
            overflow-x: auto;
            border: 1px solid var(--border);
            text-align: left;
            position: relative;
        }

        .code-snippet pre {
            margin: 0;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
            max-height: 200px;
            overflow: hidden;
        }
        .code-snippet pre::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, and Opera */
        }
        .code-snippet {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* for Firefox */
        }
        .code-snippet pre {
            -ms-overflow-style: none;  /* for IE and Edge */
            scrollbar-width: none;  /* for Firefox */
        }

        .code-snippet.expanded pre {
            max-height: none;
            overflow: auto;
        }

        .view-code-toggle {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(99, 102, 241, 0.8);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
        }

        .view-code-toggle:hover {
            background: var(--accent-primary);
        }

        .view-full-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 10px;
        }

        .view-full-link:hover {
            text-decoration: underline;
        }

        .solution-attachments {
            margin: 15px 0;
        }

        .solution-images {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .solution-image {
            width: 100px;
            height: 100px;
            border-radius: 6px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .solution-image:hover {
            transform: scale(1.05);
        }

        .solution-files {
            display: flex;
            flex-direction: row;
            gap: 8px;
            flex-wrap: wrap;
        }

        .solution-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--bg-secondary);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border: 1px solid var(--border);
            width: auto;
        }

        .solution-file:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
        }

        .solution-footer {
            padding: 20px 25px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid var(--border);
        }

        .solution-actions {
            display: flex;
            gap: 10px; /* Add a small gap between buttons */
            align-items: center;
        }

        .solution-action-btn {
            display: flex;
            align-items: center;
            justify-content: center; /* Center content within the button */
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px; /* Give all buttons a radius */
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            text-decoration: none;
            flex: 1; /* Allow buttons to grow and fill space */
        }

        .solution-action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
            z-index: 1;
        }

        .solution-action-btn.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            border-color: var(--accent-primary); 
        }

        .vote-btn.upvote.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border-color: var(--success);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 40px 0;
            flex-wrap: wrap;
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
            display: inline-block;
        }

        .pagination-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
        }

        .pagination-btn.active {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: white;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.disabled:hover {
            background: var(--bg-card);
            border-color: var(--border);
        }

        /* No solutions message */
        .no-solutions {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .no-solutions i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--accent-primary);
        }

        .no-solutions h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        /* Image Modal */
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

        /* Code Modal */
        .code-modal {
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

        .code-modal-content {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 25px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            border: 1px solid var(--border);
            position: relative;
        }

        .code-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .code-modal-title {
            font-size: 1.3rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        .close-code-modal {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .close-code-modal:hover {
            background: #dc2626;
        }

        .code-modal-body {
            background: #1a1a28;
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
        }

        .code-modal-body pre {
            margin: 0;
            font-family: 'Fira Code', monospace;
            font-size: 0.95rem;
            line-height: 1.5;
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
            
            .filters-container {
                gap: 12px;
            }
            
            .search-box {
                min-width: 220px;
            }
            
            .search-box input {
                padding: 12px 18px;
                font-size: 0.95rem;
            }
            
            .filter-options {
                gap: 10px;
            }
            
            .filter-select {
                padding: 11px 12px;
                font-size: 0.95rem;
                min-width: 130px;
            }
            
            .filter-buttons .btn {
                padding: 11px 18px;
                font-size: 0.95rem;
            }
            
            .solution-header {
                padding: 22px 22px 12px;
            }
            
            .solution-body {
                padding: 22px;
            }
            
            .solution-title {
                font-size: 1rem;
            }
            
            .description-heading {
                font-size: 1rem;
            }
            
            .solution-description {
                font-size: 0.95rem;
            }
            
            .code-preview {
                padding: 14px;
                font-size: 0.85rem;
            }
            
            .solution-user-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.85rem;
            }
            
            .solution-user-name {
                font-size: 0.9rem;
            }
            
            .solution-action-btn {
                padding: 7px 11px;
                font-size: 0.85rem;
            }
            
            .solution-image {
                width: 90px;
                height: 90px;
            }
            
            .solution-file {
                padding: 7px 10px;
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
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
                order: 1;
            }
            
            .filters-container .filter-options {
                order: 2;
                flex-wrap: wrap;
                justify-content: space-between;
                width: 100%;
            }
            
            .filters-container .search-box input {
                padding: 11px 16px;
                font-size: 0.9rem;
                padding-left: 45px;
            }
            
            .filter-select {
                padding: 10px 12px;
                font-size: 0.9rem;
                min-width: 120px;
                flex: 1;
            }

            .filters-container .filter-buttons {
                flex-shrink: 0;
            }
            
            .filters-container .filter-buttons .btn {
                padding: 12px 16px; /* Match height of selects */
                font-size: 0.9rem;
            }
            
            .solution-header {
                padding: 18px 18px 10px;
            }
            
            .solution-body {
                padding: 18px;
            }
            
            .solution-title {
                font-size: 0.95rem;
            }
            
            .solution-meta {
                gap: 12px;
            }
            
            .meta-item {
                font-size: 0.85rem;
            }
            
            .description-heading {
                font-size: 0.95rem;
            }
            
            .solution-description {
                font-size: 0.9rem;
                margin-bottom: 12px;
            }
            
            .code-preview {
                padding: 12px;
                font-size: 0.8rem;
                max-height: 130px;
                margin: 12px 0;
            }
            
            .solution-footer {
                padding: 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .solution-actions {
                width: 100%;
                flex-wrap: nowrap; /* Prevent wrapping on this specific container */
            }
            
            .solution-action-btn {
                padding: 8px 16px;
                font-size: 0.85rem;
                flex: 1;
                justify-content: center;
            }
            
            .solution-user-avatar {
                width: 34px;
                height: 34px;
                font-size: 0.8rem;
            }
            
            .solution-user-name {
                font-size: 0.85rem;
            }
            
            .solution-image {
                width: 80px;
                height: 80px;
            }
            
            .solution-file {
                padding: 6px 9px;
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
            
            .filters-container {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .filters-container .filter-options {
                flex-basis: 100%;
                order: 2;
                flex-wrap: nowrap; /* Ensure items stay on one line */
                gap: 10px;
            }
            
            .filters-container .filter-buttons {
                flex-basis: auto; /* Allow button to shrink */
                flex-shrink: 0; /* Prevent button from shrinking too much */
            }
            .filters-container .filter-buttons .btn {
                flex: 1;
                margin: 0;
                text-align: center;
            }
            
            .solution-header {
                padding: 16px 16px 8px;
            }
            
            .solution-body {
                padding: 16px;
            }
            
            .solution-title {
                font-size: 0.9rem;
            }
            
            .solution-meta {
                gap: 10px;
            }
            
            .meta-item {
                font-size: 0.8rem;
            }
            
            .solution-for {
                padding: 4px 10px;
                font-size: 0.8rem;
            }
            
            .description-heading {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            
            .solution-description {
                font-size: 0.85rem;
                margin-bottom: 10px;
                line-height: 1.5;
            }
            
            .code-preview {
                padding: 10px;
                font-size: 0.75rem;
                max-height: 120px;
                margin: 10px 0;
            }
            
            .view-full-code {
                font-size: 0.8rem;
            }
            
            .solution-user-info {
                gap: 10px;
                margin-bottom: 12px;
            }
            
            .solution-user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
            
            .solution-user-name {
                font-size: 0.8rem;
            }
            
            .solution-footer {
                padding: 16px;
                gap: 10px;
            }
            
            .solution-action-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
                gap: 4px;
            }
            
            .solution-image {
                width: 70px;
                height: 70px;
            }
            
            .solution-file {
                padding: 5px 8px;
                font-size: 0.75rem;
                gap: 6px;
            }
            
            .view-full-link {
                font-size: 0.8rem;
            }
            
            .pagination {
                gap: 6px;
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
            
            .filters-container .filters-section {
                padding: 15px 0;
                margin-bottom: 15px;
            }
            
            .filters-container .filter-options {
                flex-direction: column;
                gap: 8px;
            }
            
            .filters-container .filter-select {
                min-width: 100%;
                flex: 1;
                width: 100%;
            }
            
            .filter-buttons {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            
            .filters-container .filter-buttons .btn {
                width: 100%;
                margin: 0;
            }
            
            .filters-container .search-box input {
                padding: 10px 14px;
                font-size: 0.85rem;
                padding-left: 40px;
            }
            
            .search-box i {
                left: 12px;
                font-size: 0.9rem;
            }
            
            .solution-header {
                padding: 14px 14px 6px;
            }
            
            .solution-body {
                padding: 14px;
            }
            
            .solution-title {
                font-size: 0.85rem;
                margin-bottom: 8px;
            }
            
            .solution-meta {
                gap: 8px;
                margin-bottom: 10px;
            }
            
            .meta-item {
                font-size: 0.75rem;
            }
            
            .solution-for {
                padding: 3px 8px;
                font-size: 0.75rem;
            }
            
            .description-heading {
                font-size: 0.85rem;
                margin-bottom: 6px;
            }
            
            .solution-description {
                font-size: 0.8rem;
                margin-bottom: 8px;
                line-height: 1.4;
            }
            
            .code-preview {
                padding: 8px;
                font-size: 0.7rem;
                max-height: 100px;
                margin: 8px 0;
            }
            
            .view-full-code {
                font-size: 0.75rem;
            }
            
            .solution-user-info {
                gap: 8px;
                margin-bottom: 10px;
            }
            
            .solution-user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.7rem;
            }
            
            .solution-user-details {
                display: none;
            }
            
            .solution-footer {
                padding: 14px;
                gap: 8px;
            }
            
            .solution-actions {
                width: 100%;
            }
            
            .solution-action-btn {
                padding: 5px 8px;
                font-size: 0.75rem;
                flex: 1;
                justify-content: center;
            }
            
            .solution-image {
                width: 60px;
                height: 60px;
            }
            
            .solution-files {
                gap: 6px;
            }
            
            .solution-file {
                padding: 4px 6px;
                font-size: 0.7rem;
                gap: 4px;
            }
            
            .view-full-link {
                font-size: 0.75rem;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 4px;
                margin: 25px 0;
            }
            
            .pagination-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            .no-solutions {
                padding: 40px 15px;
            }
            
            .no-solutions i {
                font-size: 2.5rem;
                margin-bottom: 15px;
            }
            
            .no-solutions h3 {
                font-size: 1.3rem;
            }
            
            .no-solutions p {
                font-size: 0.9rem;
            }
        }

        /* Additional mobile optimizations */
        @media (max-width: 400px) {
            .solution-image {
                width: 50px;
                height: 50px;
            }
            
            .solution-action-btn span {
                display: none;
            }
            
            .solution-action-btn {
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
            .solution-actions {
                flex-wrap: nowrap;
            }
            
            .solution-action-btn {
                min-width: auto;
            }
            
            .solution-action-btn i {
                margin: 0;
            }
        }
    </style>
</head>
<body>


    <section class="page-header">
        <div class="container">
            <h1>Solutions Library</h1>
            <p>Browse through verified solutions to common programming problems</p>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="filters-section">
        <div class="container">
            <form method="GET" action="solutions.php">
                <div class="filters-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search solutions by technology, problem, or keyword..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-options"> <!-- This div now wraps the selects and the button -->
                        <div class="filter-select-group" style="display: flex; flex: 1; gap: 15px; min-width: 0;">
                            <select class="filter-select" name="technology">
                                <option value="all" <?php echo $technology === 'all' ? 'selected' : ''; ?>>All Technologies</option>
                                <option value="javascript" <?php echo $technology === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                                <option value="python" <?php echo $technology === 'python' ? 'selected' : ''; ?>>Python</option>
                                <option value="php" <?php echo $technology === 'php' ? 'selected' : ''; ?>>PHP</option>
                                <option value="java" <?php echo $technology === 'java' ? 'selected' : ''; ?>>Java</option>
                                <option value="react" <?php echo $technology === 'react' ? 'selected' : ''; ?>>React</option>
                                <option value="node" <?php echo $technology === 'node' ? 'selected' : ''; ?>>Node.js</option>
                            </select>
                            <select class="filter-select" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="votes" <?php echo $sort === 'votes' ? 'selected' : ''; ?>>Most Votes</option>
                                <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Views</option>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Solutions List -->
    <main class="container">
        <div class="solutions-container">
            <?php if (!empty($solutions)): ?>
                <?php foreach ($solutions as $solution): ?>
                    <div class="solution-card">
                        <div class="solution-header">
                            <h2 class="solution-title">
                                Solution for: <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>" class="bug-title-link">
                                    <?php echo htmlspecialchars($solution['bug_title']); ?>
                                </a>
                            </h2>
                            <div class="solution-meta">
                                <span class="meta-item"><i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($solution['created_at'])); ?></span>
                                <span class="meta-item"><i class="far fa-eye"></i> <?php echo $solution['views_count'] ?? 0; ?> views</span>
                                <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="solution-for">
                                    View Full Solution
                                </a>
                            </div>
                        </div>
                        <div class="solution-body">
                            <!-- User Info and Stats -->
                            <div class="solution-user-info">
                                <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="solution-user-avatar" style="background: <?php echo $solution['avatar_color']; ?>; overflow: hidden;">
                                    <?php if (!empty($solution['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($solution['profile_picture']); ?>" alt="<?php echo htmlspecialchars($solution['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($solution['user_name'], 0, 2)); ?>
                                    <?php endif; ?>
                                </a>
                                <div class="solution-user-details">
                                    <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="solution-user-name"><?php echo htmlspecialchars($solution['user_name']); ?></a>
                                    <div class="solution-meta">
                                        <span class="meta-item"><i class="fas fa-thumbs-up"></i> <?php echo $solution['votes']; ?> votes</span>
                                        <span class="meta-item"><i class="fas fa-bookmark"></i> <?php echo $solution['saves_count'] ?? 0; ?> saves</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Solution Description -->
                            <div class="description-heading">Solution Description</div>
                            <div class="solution-description">
                                <?php 
                                $solution_content = $solution['content'] ?? '';
                                echo nl2br(htmlspecialchars(substr($solution_content, 0, 300))); 
                                ?>
                                <?php if (strlen($solution_content) > 300): ?>
                                    ... <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="view-full-link">Read more</a>
                                <?php endif; ?>
                            </div>

                            <!-- Code Snippet Preview -->
                            <?php if (!empty($solution['code_snippet'])): ?>
                                <div class="code-preview-container">
                                    <div class="code-preview">
                                        <pre><code class="language-<?php echo detectLanguage($solution['bug_tags']); ?>"><?php echo htmlspecialchars(substr($solution['code_snippet'], 0, 500)); ?></code></pre>
                                    </div>
                                </div>
                                <a href="solution-details.php?id=<?php echo $solution['id']; ?>#code" class="view-full-code">View full code snippet</a>
                            <?php endif; ?>

                            <!-- Attachments Preview -->
                            <?php if (!empty($solution['images']) || !empty($solution['files'])): ?>
                            <div class="solution-attachments">
                                <?php if (!empty($solution['images'])): ?>
                                <div class="solution-images">
                                    <?php foreach (array_slice($solution['images'], 0, 3) as $index => $image_path): ?>
                                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Solution screenshot" class="solution-image" 
                                             onclick="openImageModal('<?php echo htmlspecialchars($image_path); ?>', <?php echo $index; ?>)">
                                    <?php endforeach; ?>
                                    <?php if (count($solution['images']) > 3): ?>
                                        <div style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; background: var(--bg-secondary); border-radius: 6px; border: 1px solid var(--border); color: var(--text-muted);">
                                            +<?php echo count($solution['images']) - 3; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($solution['files'])): ?>
                                <div class="solution-files">
                                    <?php foreach (array_slice($solution['files'], 0, 2) as $file): ?>
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="solution-file">
                                            <i class="fas fa-file"></i>
                                            <span><?php echo htmlspecialchars($file['original_name']); ?></span>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if (count($solution['files']) > 2): ?>
                                        <div style="padding: 8px 12px; color: var(--text-muted); font-size: 0.9rem;">
                                            +<?php echo count($solution['files']) - 2; ?> more files
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="solution-footer">
                            <div class="solution-actions">
                                <?php
                                $is_saved = in_array($solution['id'], $saved_solutions_ids);
                                $has_voted = false;
                                if (isset($_SESSION['user_id'])) {
                                    try {
                                        $vote_check_sql = "SELECT id FROM solution_votes WHERE user_id = ? AND solution_id = ? AND vote_type = 'up'";
                                        $vote_check_stmt = $pdo->prepare($vote_check_sql);
                                        $vote_check_stmt->execute([$_SESSION['user_id'], $solution['id']]);
                                        $has_voted = $vote_check_stmt->fetch() !== false;
                                    } catch (PDOException $e) {
                                        error_log("Error checking user vote: " . $e->getMessage());
                                    }
                                }
                                ?>
                                <button class="solution-action-btn vote-btn <?php echo $has_voted ? 'active' : ''; ?>" 
                                        data-solution-id="<?php echo $solution['id']; ?>">
                                    <i class="fas fa-thumbs-up"></i> <span class="vote-count"><?php echo $solution['votes']; ?></span> <span>Votes</span>
                                </button>
                                <button class="solution-action-btn save-solution-btn <?php echo $is_saved ? 'active' : ''; ?>" data-solution-id="<?php echo $solution['id']; ?>">
                                    <i class="<?php echo $is_saved ? 'fas' : 'far'; ?> fa-bookmark"></i> <span class="save-text"><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
                                </button>
                                <button class="solution-action-btn share-solution-btn" data-solution-id="<?php echo $solution['id']; ?>">
                                    <i class="fas fa-share"></i> <span>Share</span>
                                </button>
                                <button class="solution-action-btn" onclick="window.location.href='solution-details.php?id=<?php echo $solution['id']; ?>'">
                                    <i class="far fa-comment"></i> <span>Comment</span>
                                </button>
                            </div>
                            <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="view-full-link">Full Solution Details →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-solutions">
                    <i class="fas fa-code"></i>
                    <h3>No solutions found</h3>
                    <p>There are no solutions matching your search criteria. Try adjusting your filters or search term.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="pagination-btn">
                    ← Previous
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">← Previous</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="pagination-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php elseif ($i == $current_page - 3 || $i == $current_page + 3): ?>
                    <span class="pagination-btn disabled">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="pagination-btn">
                    Next →
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">Next →</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <div class="modal-content">
            <img id="modalImage" src="" alt="" class="modal-image">
            <div class="modal-controls">
                <span class="image-counter" id="imageCounter"></span>
                <button id="closeModal" class="close-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Code Modal -->
    <div id="codeModal" class="code-modal">
        <div class="code-modal-content">
            <div class="code-modal-header">
                <h3 class="code-modal-title">Complete Code Solution</h3>
                <button id="closeCodeModal" class="close-code-modal">Close</button>
            </div>
            <div class="code-modal-body">
                <pre><code id="modalCode" class="language-"></code></pre>
            </div>
        </div>
    </div>

    <?php include 'footer.html'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize syntax highlighting
            hljs.highlightAll();

            // Image modal functionality
            let currentImageIndex = 0;
            let currentImages = [];

            function openImageModal(imageSrc, index = 0) {
                const modal = document.getElementById('imageModal');
                const modalImage = document.getElementById('modalImage');
                const imageCounter = document.getElementById('imageCounter');
                
                currentImageIndex = index;
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

            // Close modal when clicking outside the image
            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImageModal();
                }
            });

            // Code modal functionality
            window.openCodeModal = function(button) {
                const modal = document.getElementById('codeModal');
                const modalCode = document.getElementById('modalCode');
                const code = JSON.parse(button.getAttribute('data-code'));
                const language = button.getAttribute('data-lang');
                
                modalCode.textContent = code;
                modalCode.className = 'language-' + language;
                modal.style.display = 'flex';
                
                // Re-highlight the code
                hljs.highlightElement(modalCode);
                
                document.body.style.overflow = 'hidden';
            }

            function closeCodeModal() {
                const modal = document.getElementById('codeModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            document.getElementById('closeCodeModal').addEventListener('click', closeCodeModal);

            // Close code modal when clicking outside
            document.getElementById('codeModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCodeModal();
                }
            });

            // Set up image click handlers
            document.querySelectorAll('.solution-image').forEach((img, index) => {
                img.addEventListener('click', function() {
                    const solutionCard = this.closest('.solution-card');
                    const images = Array.from(solutionCard.querySelectorAll('.solution-image')).map(img => img.src);
                    currentImages = images;
                    openImageModal(this.src, index);
                });
            });

            // Keyboard navigation for image modal
            document.addEventListener('keydown', function(e) {
                const modal = document.getElementById('imageModal');
                if (modal.style.display === 'flex') {
                    if (e.key === 'Escape') {
                        closeImageModal();
                    } else if (e.key === 'ArrowLeft' && currentImageIndex > 0) {
                        currentImageIndex--;
                        openImageModal(currentImages[currentImageIndex], currentImageIndex);
                    } else if (e.key === 'ArrowRight' && currentImageIndex < currentImages.length - 1) {
                        currentImageIndex++;
                        openImageModal(currentImages[currentImageIndex], currentImageIndex);
                    }
                }
                
                // Close code modal with escape key
                const codeModal = document.getElementById('codeModal');
                if (codeModal.style.display === 'flex' && e.key === 'Escape') {
                    closeCodeModal();
                }
            });

            // Solution voting with AJAX
            const voteButtons = document.querySelectorAll('.vote-btn');
            voteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const isCurrentlyActive = this.classList.contains('active');
                    const voteCountElement = this.querySelector('.vote-count');
                    let currentCount = parseInt(voteCountElement.textContent) || 0;
                    
                    // Optimistic UI update
                    this.classList.toggle('active');
                    if (this.classList.contains('active')) {
                        currentCount++;
                    } else {
                        currentCount--;
                    }
                    voteCountElement.textContent = currentCount;
                    
                    // Send AJAX request
                    fetch('solutions.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `vote_solution=true&solution_id=${solutionId}&vote_type=up`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // UI is already updated optimistically. Server confirmed.
                        } else {
                            // Revert UI on failure
                            this.classList.toggle('active');
                            if (this.classList.contains('active')) {
                                currentCount++;
                            } else {
                                currentCount--;
                            }
                            voteCountElement.textContent = currentCount;
                            alert('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revert UI on error
                        this.classList.toggle('active');
                        if (this.classList.contains('active')) {
                            currentCount++;
                        } else {
                            currentCount--;
                        }
                        voteCountElement.textContent = currentCount;
                        alert('Network error. Please try again.');
                    });
                });
            });

            // Solution saving with AJAX
            const saveSolutionButtons = document.querySelectorAll('.save-solution-btn');
            saveSolutionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const isCurrentlySaved = this.classList.contains('active');
                    
                    // Optimistic UI update
                    this.classList.toggle('active');
                    const icon = this.querySelector('i');
                    const text = this.querySelector('.save-text');
                    
                    if (this.classList.contains('active')) {
                        icon.className = 'fas fa-bookmark';
                        if (text) text.textContent = 'Saved';
                    } else {
                        icon.className = 'far fa-bookmark';
                        if (text) text.textContent = 'Save';
                    }
                    
                    // Send AJAX request to save/unsave
                    fetch('solutions.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `save_solution=true&solution_id=${solutionId}&save=${!isCurrentlySaved}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            // Revert UI on failure
                            this.classList.toggle('active');
                            if (this.classList.contains('active')) {
                                icon.className = 'fas fa-bookmark';
                                if (text) text.textContent = 'Saved';
                            } else {
                                icon.className = 'far fa-bookmark';
                                if (text) text.textContent = 'Save';
                            }
                            alert('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revert UI on error
                        this.classList.toggle('active');
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

            // Solution sharing
            const shareSolutionButtons = document.querySelectorAll('.share-solution-btn');
            shareSolutionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const url = `solution-details.php?id=${solutionId}`;
                    
                    if (navigator.share) {
                        navigator.share({
                            title: 'Solution on DevBug',
                            text: 'Check out this solution on DevBug',
                            url: url
                        });
                    } else {
                        navigator.clipboard.writeText(url).then(() => {
                            alert('Solution link copied to clipboard!');
                        }).catch(() => {
                            // Fallback for older browsers
                            const tempInput = document.createElement('input');
                            tempInput.value = url;
                            document.body.appendChild(tempInput);
                            tempInput.select();
                            document.execCommand('copy');
                            document.body.removeChild(tempInput);
                            alert('Solution link copied to clipboard!');
                        });
                    }
                });
            });

            // Button hover effects
            const buttons = document.querySelectorAll('.btn, .solution-action-btn, .pagination-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>