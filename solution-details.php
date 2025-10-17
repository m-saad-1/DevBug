<?php
date_default_timezone_set('UTC');

// Start session and check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle logout immediately at the top of the script
if (isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = array();

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    // Destroy the session
    session_destroy();

    // Redirect to the login page
    header("Location: auth.php");
    exit();
}

// Database connection
require_once 'config/database.php';

// Include utility functions
require_once 'includes/utils.php';

// Check if solution ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: solutions.php');
    exit();
}

$solution_id = (int)$_GET['id'];

// Increment view count before fetching, to ensure the count is fresh.
if (!isset($_SESSION['viewed_solutions'][$solution_id])) {
    // Check if the current user is the owner of the solution to prevent self-views.
    $owner_id_stmt = $pdo->prepare("SELECT user_id FROM solutions WHERE id = ?");
    $owner_id_stmt->execute([$solution_id]);
    $owner_id = $owner_id_stmt->fetchColumn();

    // Only increment if the user is not the owner.
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $owner_id) {
        $update_view_sql = "UPDATE solutions SET views = COALESCE(views, 0) + 1 WHERE id = ?";
        $pdo->prepare($update_view_sql)->execute([$solution_id]);
        
        // Mark as viewed in this session to prevent spamming views.
        $_SESSION['viewed_solutions'][$solution_id] = true;
    }
}

$solution = null;
$item_not_found = false;

// Get solution details
try {
    $solution_sql = "
        SELECT s.*, 
               u.name as user_name, 
               u.avatar_color, 
               u.id as user_id,
               u.profile_picture,
               u.title as user_title,
               b.title as bug_title,
               b.id as bug_id,
               b.tags as bug_tags,
               (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes,
               s.views as views_count, -- Use the direct column for views
               (SELECT COUNT(*) FROM solution_saves WHERE solution_id = s.id) as saves_count
        FROM solutions s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN bugs b ON s.bug_id = b.id
        WHERE s.id = ?
    ";
    $solution_stmt = $pdo->prepare($solution_sql);
    $solution_stmt->execute([$solution_id]);
    $solution = $solution_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$solution) {
        $item_not_found = true;
    }
    
} catch (PDOException $e) {
    $error = "Error fetching solution details: " . $e->getMessage();
}

// --- AJAX Handlers ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // Handle Comment/Reply Submission (AJAX)
    if (isset($_POST['submit_comment_ajax'])) {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'You must be logged in to comment.']);
            exit();
        }

        $solution_id = (int)$_GET['id'];
        $user_id = $_SESSION['user_id'];
        $content = trim($_POST['comment_text']); // Fixed: using comment_text consistently
        $parent_id = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;

        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Comment cannot be empty.']);
            exit();
        }

        try {
            if ($parent_id) {
                // Insert reply
                $sql = "INSERT INTO solution_comment_replies (solution_comment_id, user_id, content) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$parent_id, $user_id, $content]);
                $new_id = $pdo->lastInsertId();

                 // --- Notification Logic for Replies ---
                 // Notify comment owner about the new reply
                 $comment_info_stmt = $pdo->prepare("SELECT sc.user_id, b.title as bug_title FROM solution_comments sc JOIN solutions s ON sc.solution_id = s.id JOIN bugs b ON s.bug_id = b.id WHERE sc.id = ?");
                 $comment_info_stmt->execute([$parent_id]);
                 $comment_info = $comment_info_stmt->fetch();
 
                 if ($comment_info && $user_id != $comment_info['user_id']) {
                     $user_name = $_SESSION['user_name'];
                     $message = htmlspecialchars($user_name) . " replied to your comment on a solution for: \"" . htmlspecialchars(substr($comment_info['bug_title'], 0, 25)) . "...\"";
                     $link = "solution-details.php?id=$solution_id#comment-$parent_id";
 
                     $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                     $pdo->prepare($notif_sql)->execute([$comment_info['user_id'], $user_id, 'solution_reply', $message, $link]);
                 }
                 // --- End Notification Logic ---
                
                // Fetch the new reply
                $fetch_sql = "SELECT r.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture FROM solution_comment_replies r JOIN users u ON r.user_id = u.id WHERE r.id = ?";
                $stmt = $pdo->prepare($fetch_sql);
                $stmt->execute([$new_id]);
                $reply = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reply) {
                    echo json_encode(['success' => false, 'error' => 'Could not fetch the new reply.']);
                    exit();
                }

                ob_start();
                // Use a unique variable to pass the new reply data to the card
                // to avoid scope conflicts with the existing $reply variable.
                $new_reply_for_card = $reply;
                include __DIR__ . '/solution_reply_card.php';
                $html = ob_get_clean();
                echo json_encode(['success' => true, 'html' => $html, 'isReply' => true, 'parentId' => $parent_id]);

            } else {
                // Insert main comment
                $sql = "INSERT INTO solution_comments (solution_id, user_id, content) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$solution_id, $user_id, $content]);
                $new_id = $pdo->lastInsertId();

                // --- Notification Logic ---
                // Notify solution owner about the new comment
                $solution_info_stmt = $pdo->prepare("SELECT s.user_id, b.title FROM solutions s JOIN bugs b ON s.bug_id = b.id WHERE s.id = ?");
                $solution_info_stmt->execute([$solution_id]);
                $solution_info = $solution_info_stmt->fetch();

                if ($solution_info && $user_id != $solution_info['user_id']) {
                    $user_name = $_SESSION['user_name'];
                    $message = htmlspecialchars($user_name) . " commented on your solution for: \"" . htmlspecialchars(substr($solution_info['title'], 0, 25)) . "...\"";
                    $link = "solution-details.php?id=$solution_id#comment-$new_id";
                    
                    $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                    $pdo->prepare($notif_sql)->execute([$solution_info['user_id'], $user_id, 'solution_comment', $message, $link]);
                }
                // --- End Notification Logic ---

                // Fetch the new comment and render it
                $fetch_sql = "SELECT sc.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture FROM solution_comments sc JOIN users u ON sc.user_id = u.id WHERE sc.id = ?";
                $stmt = $pdo->prepare($fetch_sql);
                $stmt->execute([$new_id]);
                $comment = $stmt->fetch(PDO::FETCH_ASSOC);
                $comment['replies'] = []; // New comments have no replies

                ob_start();
                // The comment_card component expects a $comment variable.
                // Use the standardized comment card
                include __DIR__ . '/solution_comment_card.php';
                $html = ob_get_clean();
                echo json_encode(['success' => true, 'html' => $html, 'isReply' => false]);
            }
        } catch (PDOException $e) {
            // Log the detailed error for debugging, but show a generic message to the user.
            error_log("Comment submission failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
        }
        exit();
    }

    // Handle solution voting
    if (isset($_POST['vote_solution'])) {
        header('Content-Type: application/json');
        $solution_id = (int)$_POST['solution_id'];
        $user_id = $_SESSION['user_id'];
        $vote_type = $_POST['vote_type'];

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
            if ($existing_vote) {
                $pdo->prepare("DELETE FROM solution_votes WHERE id = ?")->execute([$existing_vote['id']]);
            }
            
            // If the user is casting a new vote (not just removing an old one)
            if ($vote_type !== 'remove') {
                $insert_sql = "INSERT INTO solution_votes (user_id, solution_id, vote_type) VALUES (?, ?, ?)";
                $pdo->prepare($insert_sql)->execute([$user_id, $solution_id, $vote_type]);
            }

            // Get the new total upvote count
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM solution_votes WHERE solution_id = ? AND vote_type = 'up'");
            $count_stmt->execute([$solution_id]);
            $new_vote_count = $count_stmt->fetchColumn();

            echo json_encode(['success' => true, 'newVoteCount' => $new_vote_count]);
        } catch (PDOException $e) {
            error_log("Vote submission failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
        }
        exit();
    }

    // Handle solution saving
    if (isset($_POST['save_solution'])) {
        $solution_id = (int)$_POST['solution_id'];
        $save = filter_var($_POST['save'], FILTER_VALIDATE_BOOLEAN);
        try {
            if ($save) {
                $save_sql = "INSERT INTO solution_saves (user_id, solution_id) VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP";
                $pdo->prepare($save_sql)->execute([$_SESSION['user_id'], $solution_id]);
            } else {
                $remove_sql = "DELETE FROM solution_saves WHERE user_id = ? AND solution_id = ?";
                $pdo->prepare($remove_sql)->execute([$_SESSION['user_id'], $solution_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }

    // Handle Comment/Reply Editing
    if (isset($_POST['edit_comment']) || isset($_POST['edit_reply'])) {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $content = trim($_POST['content']);

        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Content cannot be empty.']);
            exit();
        }

        try {
            if (isset($_POST['edit_comment'])) {
                $comment_id = (int)$_POST['comment_id'];
                $stmt = $pdo->prepare("UPDATE solution_comments SET content = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $comment_id, $user_id]);
            } elseif (isset($_POST['edit_reply'])) {
                $reply_id = (int)$_POST['reply_id'];
                $stmt = $pdo->prepare("UPDATE solution_comment_replies SET content = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $reply_id, $user_id]);
            }

            if ($stmt->rowCount() > 0) {
                // Convert Markdown to HTML for the response
                require_once 'includes/Parsedown.php';
                $Parsedown = new Parsedown();
                $html_content = $Parsedown->text($content);
                echo json_encode(['success' => true, 'content' => $html_content]);
            } else {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this or it does not exist.']);
            }

        } catch (PDOException $e) {
            error_log("Solution Comment/Reply edit failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error during update.']);
        }
        exit();
    }

    // Handle Comment/Reply Deletion
    if (isset($_POST['delete_comment']) || isset($_POST['delete_reply'])) {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];

        try {
            if (isset($_POST['delete_comment'])) {
                $comment_id = (int)$_POST['comment_id'];
                // Also delete replies to this comment
                $pdo->prepare("DELETE FROM solution_comment_replies WHERE solution_comment_id = ?")->execute([$comment_id]);
                // Delete the comment itself, ensuring the user is the owner
                $stmt = $pdo->prepare("DELETE FROM solution_comments WHERE id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $user_id]);
            } elseif (isset($_POST['delete_reply'])) {
                $reply_id = (int)$_POST['reply_id'];
                // Delete the reply, ensuring the user is the owner
                $stmt = $pdo->prepare("DELETE FROM solution_comment_replies WHERE id = ? AND user_id = ?");
                $stmt->execute([$reply_id, $user_id]);
            }

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this or it does not exist.']);
                exit();
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Solution Comment/Reply deletion failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error during deletion.']);
        }
        exit();
    }
}

// Get solution images
$solution_images = [];
try {
    $image_stmt = $pdo->prepare("SELECT image_path FROM solution_images WHERE solution_id = ?");
    $image_stmt->execute([$solution_id]);
    $solution_images = $image_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching solution images: " . $e->getMessage());
}

// Get solution files
$solution_files = [];
try {
    $file_stmt = $pdo->prepare("SELECT file_path, original_name FROM solution_files WHERE solution_id = ?");
    $file_stmt->execute([$solution_id]);
    $solution_files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching solution files: " . $e->getMessage());
}

// Get comments for this solution
$comments = [];
try {
    $comment_sql = "
        SELECT c.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture
        FROM solution_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.solution_id = ?
        ORDER BY c.created_at DESC
    ";
    $comment_stmt = $pdo->prepare($comment_sql);
    $comment_stmt->execute([$solution_id]);
    $comments = $comment_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total comments count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM solution_comments WHERE solution_id = ?");
    $count_stmt->execute([$solution_id]);
    $total_comments = $count_stmt->fetchColumn();

    // Get replies for each comment
    foreach ($comments as &$comment) {
        $reply_sql = "SELECT r.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture
                      FROM solution_comment_replies r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.solution_comment_id = ?
                      ORDER BY r.created_at DESC";
        $reply_stmt = $pdo->prepare($reply_sql);
        $reply_stmt->execute([$comment['id']]);
        $comment['replies'] = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($comment); // Unset the reference to avoid scope issues with AJAX handlers.
} catch (PDOException $e) {
    error_log("Error fetching comments: " . $e->getMessage());
}

// Check if current user has saved this solution
$is_saved = false;
if (isset($_SESSION['user_id'])) {
    try {
        $saved_sql = "SELECT solution_id FROM solution_saves WHERE user_id = ? AND solution_id = ?";
        $saved_stmt = $pdo->prepare($saved_sql);
        $saved_stmt->execute([$_SESSION['user_id'], $solution_id]);
        $is_saved = $saved_stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error checking saved solution: " . $e->getMessage());
    }
}

// Check if current user has voted on this solution
$user_vote = null;
if (isset($_SESSION['user_id'])) {
    try {
        $vote_sql = "SELECT vote_type FROM solution_votes WHERE user_id = ? AND solution_id = ?";
        $vote_stmt = $pdo->prepare($vote_sql);
        $vote_stmt->execute([$_SESSION['user_id'], $solution_id]);
        $vote_result = $vote_stmt->fetch(PDO::FETCH_ASSOC);
        $user_vote = $vote_result ? $vote_result['vote_type'] : null;
    } catch (PDOException $e) {
        error_log("Error checking user vote: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solution: <?php echo htmlspecialchars($solution['bug_title']); ?> - DevBugSolver</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
        /* Base Responsive Scaling */
        :root {
            --scale-factor: 1;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1000px) {
            :root {
                --scale-factor: 0.95;
            }
        }

        @media (max-width: 768px) {
            :root {
                --scale-factor: 0.9;
            }
        }

        @media (max-width: 600px) {
            :root {
                --scale-factor: 0.85;
            }
        }

        @media (max-width: 400px) {
            :root {
                --scale-factor: 0.8;
            }
        }

        /* Apply scaling to all elements */
        * {
            box-sizing: border-box;
        }

        html {
            font-size: calc(1rem * var(--scale-factor));
        }

        /* Page Header */
        .page-header {
            padding: 80px 0 20px;
            text-align: left;
        }

        .page-header h1 {
            font-size: 2.2rem;
            margin-bottom: calc(16px * var(--scale-factor));
            font-weight: 700;
        }

        .page-meta {
            display: flex;
            gap: calc(20px * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: calc(6px * var(--scale-factor));
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-content {
            margin-bottom: 60px;
        }

        /* Solution Details */
        .solution-details {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(30px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            margin-bottom: calc(30px * var(--scale-factor));
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: calc(20px * var(--scale-factor));
            padding-bottom: calc(10px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: calc(15px * var(--scale-factor));
            margin-bottom: calc(25px * var(--scale-factor));
            padding-bottom: calc(20px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
        }

        .user-avatar {
            width: calc(60px * var(--scale-factor));
            height: calc(60px * var(--scale-factor));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: block;
            margin-bottom: calc(5px * var(--scale-factor));
        }

        .user-name:hover {
            color: var(--accent-primary);
        }

        .user-title {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .solution-description {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 1.05rem;
            margin-bottom: calc(30px * var(--scale-factor));
        }

        /* Code Snippet */
        .code-snippet {
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(25px * var(--scale-factor));
            margin: calc(25px * var(--scale-factor)) 0;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.95rem;
            line-height: 1.5;
            border: 1px solid var(--border);
            position: relative; /* Needed for positioning the button */
        }
        .code-snippet::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, and Opera */
        }
        .code-snippet {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }


        .code-snippet pre {
            margin: 0;
            background: transparent !important; /* Removed background color */
            max-height: calc(300px * var(--scale-factor)); /* Limit the height of the code preview */
            overflow: hidden; /* Hide the rest of the code */
        }

        .code-snippet code {
            background: transparent !important;
            padding: 0 !important;
        }


        .view-code-toggle {
            position: absolute;
            bottom: calc(10px * var(--scale-factor));
            right: calc(10px * var(--scale-factor));
            background: rgba(99, 102, 241, 0.8);
            color: white;
            border: none;
            padding: calc(8px * var(--scale-factor)) calc(16px * var(--scale-factor));
            border-radius: calc(6px * var(--scale-factor));
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
            z-index: 10;
        }
        .view-code-toggle:hover {
            background: var(--accent-primary);
        }

        /* Attachments */
        .solution-images {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
            flex-wrap: wrap;
        }

        .solution-image {
            width: calc(200px * var(--scale-factor));
            height: calc(200px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            object-fit: cover;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .solution-image:hover {
            transform: scale(1.02);
            box-shadow: 0 calc(3px * var(--scale-factor)) calc(10px * var(--scale-factor)) rgba(0, 0, 0, 0.2);
        }

        .solution-files {
            display: flex;
            flex-direction: row; /* Changed from column to row */
            gap: calc(10px * var(--scale-factor));
            flex-wrap: wrap; /* Allow items to wrap to the next line */
            margin: calc(20px * var(--scale-factor)) 0;
        }

        .solution-file {
            display: flex;
            align-items: center;
            gap: calc(12px * var(--scale-factor));
            padding: calc(12px * var(--scale-factor)) calc(16px * var(--scale-factor));
            background: var(--bg-secondary);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border: 1px solid var(--border);
            width: fit-content; /* Make the button only as wide as its content */
        }

        .solution-file:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .file-icon {
            font-size: 1.3rem;
        }

        .file-name {
            flex: 1;
            font-size: 0.95rem;
        }

        /* Action Buttons */
        .solution-actions {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            margin: calc(25px * var(--scale-factor)) 0;
            flex-wrap: wrap;
        }

        .btn {
            padding: calc(12px * var(--scale-factor)) calc(24px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            text-decoration: none;
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

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--accent-primary);
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(20px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            text-decoration: none;
        }

        .action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .action-btn.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .vote-btn.upvote.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border-color: var(--success);
        }

        /* Comments Section - Matching post_details.php style */
        .comments-section {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(30px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            margin-bottom: calc(30px * var(--scale-factor));
        }

        .comment-form {
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(20px * var(--scale-factor));
            margin-top: calc(20px * var(--scale-factor));
            margin-bottom: calc(30px * var(--scale-factor)); /* Add space below the form */
            border: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: calc(15px * var(--scale-factor));
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: calc(12px * var(--scale-factor)) calc(15px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            resize: vertical;
        }

        textarea.form-control {
            min-height: calc(120px * var(--scale-factor));
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            padding: calc(10px * var(--scale-factor)) calc(15px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            margin-bottom: calc(15px * var(--scale-factor));
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: calc(10px * var(--scale-factor)) calc(15px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            margin-bottom: calc(15px * var(--scale-factor));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Comment Styles matching post_details.php */
        .comment {
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(20px * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        .comment .user-info {
            display: flex;
            align-items: center;
            gap: calc(12px * var(--scale-factor));
            margin-bottom: calc(15px * var(--scale-factor));
            padding-bottom: 0;
            border-bottom: none;
        }

        .comment .user-avatar {
            width: calc(50px * var(--scale-factor));
            height: calc(50px * var(--scale-factor));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .comment .user-avatar:hover {
            transform: scale(1.05);
        }

        .comment .user-details {
            display: flex;
            flex-direction: column;
        }

        .comment .user-name {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            margin-bottom: 0;
        }

        .comment .user-name:hover {
            color: var(--accent-primary);
        }

        .comment .post-time {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .comment-text {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: calc(15px * var(--scale-factor));
            font-size: 1.05rem;
        }

        /* Reply Form Styles */
        .reply-form {
            margin-top: calc(15px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            background: rgba(255, 255, 255, 0.03);
            border-radius: calc(8px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        /* Hide reply form by default */
        .reply-form {
            display: none;
        }

        .replies-container {
            margin-top: calc(15px * var(--scale-factor));
            padding-left: calc(20px * var(--scale-factor));
            border-left: 2px solid var(--border);
        }

        .view-replies-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            transition: var(--transition);
        }
        .view-replies-btn:hover {
            color: var(--accent-primary);
        }

        .reply-btn {
            background: none;
            border: none;
            color: var(--accent-primary);
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: calc(5px * var(--scale-factor));
            padding: calc(5px * var(--scale-factor)) calc(10px * var(--scale-factor));
            border-radius: calc(4px * var(--scale-factor));
            transition: var(--transition);
        }

        .reply-btn:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .comment-reply {
            margin-left: calc(40px * var(--scale-factor));
            margin-top: calc(15px * var(--scale-factor));
            background: rgba(255,255,255,0.03);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        /* Code Modal from post-details */
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
            padding: calc(20px * var(--scale-factor));
        }
        .code-modal-content {
            background: var(--bg-secondary);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(25px * var(--scale-factor));
            width: 100%;
            max-height: 90%;
            overflow: auto;
            border: 1px solid var(--border);
            position: relative;
        }
        .close-code-modal {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: calc(6px * var(--scale-factor));
            padding: calc(8px * var(--scale-factor)) calc(16px * var(--scale-factor));
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        .close-code-modal:hover {
            background: #dc2626; /* A darker shade of the danger color */
            transform: translateY(-2px);
        }
        .code-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: calc(20px * var(--scale-factor));
            padding-bottom: calc(15px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
        }
        .code-modal-body {
            background: transparent;
            border-radius: calc(8px * var(--scale-factor));
            overflow-x: auto;
        }
        .code-modal-body pre {
            margin: 0;
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
            padding: calc(20px * var(--scale-factor));
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
            max-height: calc(100vh - (100px * var(--scale-factor)));
            border-radius: calc(8px * var(--scale-factor));
            object-fit: contain;
        }

        .modal-controls {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            margin-top: calc(20px * var(--scale-factor));
            align-items: center;
        }

        .close-modal {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(20px * var(--scale-factor));
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

        /* Related Solutions */
        .related-solutions {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(25px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .related-solution {
            display: block;
            padding: calc(15px * var(--scale-factor));
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border: 1px solid var(--border);
            margin-bottom: calc(10px * var(--scale-factor));
        }

        .related-solution:hover {
            border-color: var(--accent-primary);
            transform: translateX(5px);
        }

        .related-solution:last-child {
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .page-meta {
                gap: calc(10px * var(--scale-factor));
                justify-content: flex-start; /* Ensure it stays left-aligned */
            }
            
            .solution-actions {
                justify-content: center;
            }
            
            .page-header h1 {
                font-size: calc(1.8rem * var(--scale-factor));
            }

            .solution-image {
                width: calc(150px * var(--scale-factor));
                height: calc(150px * var(--scale-factor));
            }

            .comment-reply {
                margin-left: calc(20px * var(--scale-factor));
            }
        }

        @media (max-width: 480px) {
            .solution-details, .comments-section, .related-solutions {
                padding: calc(20px * var(--scale-factor));
            }
            
            .solution-image {
                width: 100%;
                height: auto;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .modal-content {
                max-width: 100%;
                padding: calc(10px * var(--scale-factor));
            }
            
            .modal-image {
                max-height: calc(100vh - (80px * var(--scale-factor)));
            }

            .comment-reply {
                margin-left: calc(10px * var(--scale-factor));
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php if ($item_not_found): ?>
        <div class="container" style="text-align: center; padding: 100px 20px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">
            <div class="no-solutions" style="padding: 40px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border);">
                <i class="fas fa-ghost" style="font-size: 3rem; margin-bottom: 20px; color: var(--accent-primary);"></i>
                <h1 style="font-size: 2rem; color: var(--text-primary); margin-bottom: 10px;">Solution Not Found</h1>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 20px;">The solution you are looking for may have been deleted or is no longer available.</p>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <a href="solutions.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Solutions
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Solution for: <?php echo htmlspecialchars($solution['bug_title']); ?></h1>
            <div class="page-meta">
                <span class="meta-item"><i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($solution['created_at'])); ?></span>
                <span class="meta-item"><i class="far fa-eye"></i> <?php echo $solution['views_count']; ?> views</span>
                <span class="meta-item"><i class="fas fa-thumbs-up"></i> <?php echo $solution['upvotes']; ?> votes</span>
                <span class="meta-item"><i class="far fa-bookmark"></i> <?php echo $solution['saves_count']; ?> saves</span>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="main-content">
            <!-- Solution Details -->
            <section class="solution-details">
                <!-- User Info -->
                <div class="user-info">
                    <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="user-avatar" style="background: <?php echo $solution['avatar_color']; ?>; overflow: hidden;">
                        <?php if (!empty($solution['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($solution['profile_picture']); ?>" alt="<?php echo htmlspecialchars($solution['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($solution['user_name'], 0, 2)); ?>
                        <?php endif; ?>
                    </a>
                    <div class="user-details">
                        <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="user-name"><?php echo htmlspecialchars($solution['user_name']); ?></a>
                        <div class="user-title"><?php echo htmlspecialchars($solution['user_title']); ?></div>
                    </div>
                </div>

                <!-- Solution Description -->
                <h2 class="section-title">Solution Description</h2>
                <div class="solution-description">
                    <?php echo nl2br(htmlspecialchars($solution['content'])); ?>
                </div>

                <!-- Code Snippet -->
                <?php if (!empty($solution['code_snippet'])): ?>
                <h2 class="section-title" id="code">Code Solution</h2>
                <div class="code-snippet">
                    <pre><code class="language-<?php echo detectLanguage($solution['bug_tags']); ?>"><?php echo htmlspecialchars($solution['code_snippet']); ?></code></pre>
                     <?php if (strlen($solution['code_snippet'] ?? '') > 1000): ?>
                    <button class="view-code-toggle" 
                            data-code="<?php echo htmlspecialchars(json_encode($solution['code_snippet']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-lang="<?php echo detectLanguage($solution['bug_tags'] ?? ''); ?>"
                            onclick="openCodeModal(this)">
                        <i class="fas fa-expand"></i> View Full Code
                    </button>
                <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Images -->
                <?php if (!empty($solution_images)): ?>
                <h2 class="section-title">Solution Screenshots</h2>
                <div class="solution-images">
                    <?php foreach ($solution_images as $index => $image_path): ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Solution screenshot" class="solution-image" 
                             onclick="openImageModal('<?php echo htmlspecialchars($image_path); ?>', <?php echo $index; ?>)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Files -->
                <?php if (!empty($solution_files)): ?>
                <h2 class="section-title">Attached Files</h2>
                <div class="solution-files">
                    <?php foreach ($solution_files as $file): ?>
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="solution-file">
                            <i class="fas fa-file file-icon"></i>
                            <span class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></span>
                            <i class="fas fa-download"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="solution-actions">
                    <button class="action-btn vote-btn upvote <?php echo $user_vote === 'up' ? 'active' : ''; ?>" 
                            data-solution-id="<?php echo $solution_id; ?>">
                        <i class="fas fa-thumbs-up"></i> Vote <span class="vote-count"><?php echo $solution['upvotes']; ?></span>
                    </button>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="action-btn save-solution-btn <?php echo $is_saved ? 'active' : ''; ?>" 
                            data-solution-id="<?php echo $solution_id; ?>">
                        <i class="<?php echo $is_saved ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                        <span><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
                    </button>
                    <?php else: ?>
                    <a href="auth.php" class="action-btn">
                        <i class="far fa-bookmark"></i> Save
                    </a>
                    <?php endif; ?>
                    <button class="action-btn share-solution-btn" data-solution-id="<?php echo $solution_id; ?>">
                        <i class="fas fa-share"></i> Share
                    </button>
                    <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-bug"></i> View Original Bug
                    </a>
                </div>
            </section>

            <!-- Comments Section - Matching post_details.php -->
            <section class="comments-section" id="comments">
                <h2 class="section-title" id="comment-count-header">Comments (<?php echo $total_comments; ?>)</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="comment-form">
                        <form class="comment-submit-form" method="POST" action="solution-details.php?id=<?php echo $solution_id; ?>#comments">
                            <div class="form-group">
                                <label for="main_comment_text" class="form-label">Add a Comment</label>
                                <textarea id="main_comment_text" name="comment_text" class="form-control" placeholder="Share your thoughts or ask for clarification..." required></textarea>
                            </div>
                            <button type="submit" name="submit_comment" class="btn btn-primary">Post Comment</button>
                            <input type="hidden" name="submit_comment_ajax" value="1">
                        </form>
                    </div>
                <?php else: ?>
                    <div class="comment-form">
                        <p>Please <a href="auth.php">sign in</a> to post a comment.</p>
                    </div>
                <?php endif; ?>
                
                <div class="comments-list" id="commentsList">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <?php include __DIR__ . '/solution_comment_card.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p id="no-comments-message" style="text-align: center; color: var(--text-muted); padding: 20px;">No comments yet. Be the first to comment!</p>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <?php endif; ?>

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

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        // Global functions for modals to be accessible by onclick attributes
        let currentImageIndex = 0;
        let solutionImages = <?php echo json_encode($solution_images); ?>;

        function openImageModal(imageSrc, index = 0) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const imageCounter = document.getElementById('imageCounter');
            
            currentImageIndex = index;
            modalImage.src = imageSrc;
            imageCounter.textContent = `Image ${currentImageIndex + 1} of ${solutionImages.length}`;
            modal.style.display = 'flex';
            
            document.body.style.overflow = 'hidden';
        }

        function openCodeModal(button) {
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
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize syntax highlighting
            hljs.highlightAll();
            
            // AJAX Comment/Reply Submission
            document.getElementById('comments').addEventListener('submit', function(e) {
                if (e.target && e.target.classList.contains('comment-submit-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const formData = new FormData(form);
                    const submitButton = form.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;

                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                    fetch(`solution-details.php?id=<?php echo $solution_id; ?>`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData,
                    }).then(response => response.json()).then(data => {
                        if (data.success) {
                            if (data.isReply) {
                                // It's a reply, find the correct container.
                                // The correct container is always the one with '-replies-container'
                                let replyContainer = document.getElementById(`comment-${data.parentId}-replies-container`);
                                replyContainer.insertAdjacentHTML('afterbegin', data.html);
                                replyContainer.style.display = 'block'; // Make the container visible
                                toggleReplyForm(data.parentId); // Hide the form after successful reply
                                
                                // Ensure the replies list (for existing replies) is visible if it exists
                                const repliesList = document.getElementById(`replies-list-${data.parentId}`);
                                if (repliesList) { // If there are existing replies, make sure their container is also visible.
                                    repliesList.style.display = 'block';
                                }

                                // Update reply count text
                                updateReplyCount(data.parentId, 1); // Increment by 1
                            } else {
                                // It's a main comment, prepend to the list
                                const commentsList = document.getElementById('commentsList');
                                const noCommentsMessage = document.getElementById('no-comments-message');
                                if (noCommentsMessage) {
                                    noCommentsMessage.remove();
                                }
                                commentsList.insertAdjacentHTML('afterbegin', data.html);

                                // Update total comment count
                                const commentCountHeader = document.getElementById('comment-count-header');
                                if (commentCountHeader) {
                                    const currentCount = parseInt(commentCountHeader.textContent.match(/\d+/)[0]);
                                    commentCountHeader.textContent = `Comments (${currentCount + 1})`;
                                }
                            }
                            form.querySelector('textarea').value = ''; // Clear textarea
                        } else {
                            alert('Error: ' + (data.error || 'Could not post comment.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('A network error occurred. Please try again.');
                    })
                    .finally(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                    });
                }
            });

            // Handle comment/reply actions (edit and delete)
            document.getElementById('commentsList').addEventListener('click', function(e) {
                let target = e.target.closest('.delete-comment-btn, .delete-reply-btn, .edit-comment-btn, .edit-reply-btn');
                if (!target) return;

                const isDelete = target.matches('.delete-comment-btn, .delete-reply-btn');
                const isEdit = target.matches('.edit-comment-btn, .edit-reply-btn');

                if (isDelete) {
                    handleDelete(target);
                } else if (isEdit) {
                    // The handleEdit function from post-details.php is more robust.
                    // We'll use that one.
                    handleEdit(target);
                }
            });

            function handleEdit(target) {
                const isComment = target.classList.contains('edit-comment-btn');
                const id = isComment ? target.dataset.commentId : target.dataset.replyId;
                const type = isComment ? 'comment' : 'reply';
                const commentElement = document.getElementById(`${type}-${id}`);
                const textElement = commentElement.querySelector('.comment-text');
                const originalContentHTML = textElement.innerHTML;
                const originalContent = originalContentHTML.replace(/<br\s*\/?>/gi, "\n");

                // Prevent multiple edit forms
                if (commentElement.querySelector('.edit-form-container')) {
                    return;
                }

                const editFormHTML = `
                    <div class="edit-form-container" style="margin-top: 10px;">
                        <textarea class="form-control" style="min-height: 100px;">${originalContent}</textarea>
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <button class="btn btn-primary btn-sm save-edit-btn">Save</button>
                            <button class="btn btn-secondary btn-sm cancel-edit-btn">Cancel</button>
                        </div>
                    </div>
                `;

                textElement.style.display = 'none';
                textElement.insertAdjacentHTML('afterend', editFormHTML);

                const editContainer = commentElement.querySelector('.edit-form-container');
                const textarea = editContainer.querySelector('textarea');
                const saveBtn = editContainer.querySelector('.save-edit-btn');
                const cancelBtn = editContainer.querySelector('.cancel-edit-btn');

                function closeEdit() {
                    editContainer.remove();
                    textElement.style.display = 'block';
                }

                cancelBtn.addEventListener('click', closeEdit);

                saveBtn.addEventListener('click', function() {
                    const newContent = textarea.value;
                    if (newContent.trim() === '') {
                        alert('Content cannot be empty.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('content', newContent);
                    if (isComment) {
                        formData.append('edit_comment', '1');
                        formData.append('comment_id', id);
                    } else {
                        formData.append('edit_reply', '1');
                        formData.append('reply_id', id);
                    }

                    fetch(`solution-details.php?id=<?php echo $solution_id; ?>`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            textElement.innerHTML = data.content;
                            closeEdit();
                        } else {
                            alert('Error: ' + (data.error || 'Could not save changes.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('A network error occurred.');
                    });
                });
            }

            function handleDelete(target) {
                const isComment = target.classList.contains('delete-comment-btn');
                const id = isComment ? target.dataset.commentId : target.dataset.replyId;
                const type = isComment ? 'comment' : 'reply';

                if (!confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
                    return;
                }

                const formData = new FormData();
                if (isComment) {
                    formData.append('delete_comment', '1');
                    formData.append('comment_id', id);
                } else {
                    formData.append('delete_reply', '1');
                    formData.append('reply_id', id);
                }

                fetch(`solution-details.php?id=<?php echo $solution_id; ?>`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const elementToRemove = document.getElementById(`${type}-${id}`);
                        if (elementToRemove) {
                            elementToRemove.style.transition = 'opacity 0.5s';
                            elementToRemove.style.opacity = '0';
                            setTimeout(() => elementToRemove.remove(), 500);

                            if (isComment) {
                                // It's a main comment, update the total count
                                const commentCountHeader = document.getElementById('comment-count-header');
                                if (commentCountHeader) {
                                    const currentCount = parseInt(commentCountHeader.textContent.match(/\d+/)[0] || '0');
                                    commentCountHeader.textContent = `Comments (${Math.max(0, currentCount - 1)})`;
                                }
                            } else {
                                // It's a reply, update the reply count for the parent comment
                                const parentComment = target.closest('.comment');
                                if (parentComment) {
                                    const parentId = parentComment.id.split('-')[1];
                                    updateReplyCount(parentId, -1); // Decrement by 1
                                }
                            }
                        } else if (isComment) { // If it's a comment, and it was the last one, show no-comments message
                            const commentsList = document.getElementById('commentsList');
                            if (commentsList.children.length === 0) {
                                commentsList.insertAdjacentHTML('beforeend', `
                                    <p id="no-comments-message" style="text-align: center; color: var(--text-muted); padding: 20px;">No comments yet. Be the first to comment!</p>
                                `);
                            }
                        } else { // If it's a reply, and it was the last one, remove the view replies button
                            const parentComment = target.closest('.comment');
                            if (parentComment) {
                                const parentId = parentComment.id.split('-')[1];
                                const repliesInnerContainer = document.getElementById(`comment-${parentId}-replies-inner`);
                                if (repliesInnerContainer && repliesInnerContainer.children.length === 0) {
                                    document.querySelector(`button[onclick="toggleReplies(${parentId})"]`)?.remove();
                                }
                            }
                        }
                    } else {
                        alert('Error: ' + (data.error || `Could not delete ${type}.`));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('A network error occurred.');
                });
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

            // Keyboard navigation for image modal
            document.addEventListener('keydown', function(e) {
                const modal = document.getElementById('imageModal');
                if (modal.style.display === 'flex') {
                    if (e.key === 'Escape') {
                        closeImageModal();
                    } else if (e.key === 'ArrowLeft' && currentImageIndex > 0) {
                        currentImageIndex--;
                        openImageModal(solutionImages[currentImageIndex], currentImageIndex);
                    } else if (e.key === 'ArrowRight' && currentImageIndex < solutionImages.length - 1) {
                        currentImageIndex++;
                        openImageModal(solutionImages[currentImageIndex], currentImageIndex);
                    }
                }
                // Close code modal with escape key
                const codeModal = document.getElementById('codeModal');
                if (codeModal.style.display === 'flex' && e.key === 'Escape') {
                    closeCodeModal(); 
                }
            });

            // Solution voting without page refresh
            const voteButtons = document.querySelectorAll('.vote-btn');
            voteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const isCurrentlyActive = this.classList.contains('active');
                    
                    // Determine new vote type
                    const voteType = isCurrentlyActive ? 'remove' : 'up';
                    
                    // Optimistic UI update
                    this.classList.toggle('active');
                    const countElement = this.querySelector('.vote-count');
                    let currentCount = parseInt(countElement.textContent) || 0;
                    
                    if (voteType === 'up') {
                        currentCount++;
                    } else {
                        currentCount--;
                    }
                    countElement.textContent = currentCount;
                    
                    // Send AJAX request
                    fetch('solution-details.php?id=<?php echo $solution_id; ?>', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `vote_solution=true&solution_id=${solutionId}&vote_type=${voteType}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update count with server-verified value
                            countElement.textContent = data.newVoteCount;
                        } else {
                            // Revert UI on failure
                            this.classList.toggle('active');
                            countElement.textContent = currentCount; // Revert to optimistic count before re-toggling
                            alert('Failed to update vote: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revert UI on error
                        this.classList.toggle('active'); // double toggle reverts
                        countElement.textContent = currentCount;
                        alert('Network error. Please try again.');
                    });
                });
            });

            // Solution saving
            const saveButton = document.querySelector('.save-solution-btn');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const isCurrentlySaved = this.classList.contains('active');
                    
                    // Send AJAX request
                    fetch('solution-details.php?id=<?php echo $solution_id; ?>', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `save_solution=true&solution_id=${solutionId}&save=${!isCurrentlySaved}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Toggle UI state
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
                        } else {
                            alert('Failed to update saved status: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error. Please try again.');
                    });
                });
            }

            // Solution sharing
            const shareButton = document.querySelector('.share-solution-btn');
            if (shareButton) {
                shareButton.addEventListener('click', function() {
                    const solutionId = this.getAttribute('data-solution-id');
                    const url = window.location.href;
                    
                    if (navigator.share) {
                        navigator.share({
                            title: 'Solution on DevBugSolver',
                            text: 'Check out this solution on DevBugSolver',
                            url: url
                        });
                    } else {
                        navigator.clipboard.writeText(url).then(() => {
                            alert('Solution link copied to clipboard!');
                        });
                    }
                });
            }

        });

        // Reply form toggle
        function toggleReplyForm(commentId) {
            const form = document.getElementById(`reply-form-${commentId}`);
            if (form) {
                // Check computed style to correctly toggle visibility, even if set by CSS
                const isHidden = window.getComputedStyle(form).display === 'none';
                form.style.display = isHidden ? 'block' : 'none';
            }
        }

        // Toggle replies visibility
        window.toggleReplies = function(commentId) { // This is the wrapper
            const repliesList = document.getElementById(`replies-list-${commentId}`); 
            if (!repliesList) return; // Should not happen if button exists
            const toggleBtn = repliesList.previousElementSibling;
            const isVisible = repliesList.style.display === 'block';
            repliesList.style.display = isVisible ? 'none' : 'block';
            toggleBtn.innerHTML = isVisible ? `<i class="fas fa-chevron-down"></i> View ${repliesList.querySelectorAll('.comment-reply').length} replies` : `<i class="fas fa-chevron-up"></i> Hide replies`;
        }

        // Helper function to update reply counts
        function updateReplyCount(commentId, change) {
            const countSpan = document.getElementById(`reply-count-${commentId}`);
            if (countSpan) {
                const match = countSpan.textContent.match(/\d+/);
                let currentCount = match ? parseInt(match[0]) : 0;
                const newCount = Math.max(0, currentCount + change);

                if (newCount > 0) {
                    const replyText = newCount === 1 ? 'reply' : 'replies';
                    countSpan.textContent = `View ${newCount} ${replyText}`;
                } else {
                    // If count drops to 0, remove the button
                    const viewRepliesBtn = document.querySelector(`button[onclick="toggleReplies(${commentId})"]`);
                    if (viewRepliesBtn) {
                        viewRepliesBtn.remove();
                    }
                }
            }
        }
    </script>
</body>
</html>