<?php
// Set the default timezone to UTC
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'config/database.php';

// Include utility functions
require_once 'includes/utils.php';

// --- AJAX Handlers ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // Handle Comment/Reply Submission (AJAX)
    if (isset($_POST['submit_comment_ajax'])) {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'You must be logged in to comment.']);
            exit();
        }

        $bug_id = (int)$_GET['id'];
        $user_id = $_SESSION['user_id'];
        $content = trim($_POST['comment_text']);
        $parent_id = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;

        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Comment cannot be empty.']);
            exit();
        }

        try {
            if ($parent_id) {
                // Insert reply
                $sql = "INSERT INTO comment_replies (comment_id, user_id, content) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$parent_id, $user_id, $content]);
                $new_id = $pdo->lastInsertId();
                
                // --- Notification Logic for Replies ---
                // Notify comment owner about the new reply
                $comment_owner_stmt = $pdo->prepare("SELECT c.user_id, b.title FROM comments c JOIN bugs b ON c.bug_id = b.id WHERE c.id = ?");
                $comment_owner_stmt->execute([$parent_id]);
                $comment_info = $comment_owner_stmt->fetch();

                if ($comment_info && $user_id != $comment_info['user_id']) {
                    $user_name = $_SESSION['user_name'];
                    $message = htmlspecialchars($user_name) . " replied to your comment on: \"" . htmlspecialchars(substr($comment_info['title'], 0, 25)) . "...\"";
                    $link = "post-details.php?id=$bug_id#comment-$parent_id";

                    $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                    $pdo->prepare($notif_sql)->execute([$comment_info['user_id'], $user_id, 'comment_reply', $message, $link]);
                }
                // --- End Notification Logic ---

                // Fetch the new reply with ALL fields including created_at
                $fetch_sql = "SELECT r.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture FROM comment_replies r JOIN users u ON r.user_id = u.id WHERE r.id = ?";
                $stmt = $pdo->prepare($fetch_sql);
                $stmt->execute([$new_id]);
                $reply = $stmt->fetch(PDO::FETCH_ASSOC);

                // Use a unique variable to pass the new reply data to the card
                // to avoid scope conflicts with the existing $reply variable.
                $new_reply_for_card = $reply;
                ob_start();
                include __DIR__ . '/Components/reply_card.php';
                $html = ob_get_clean();
                echo json_encode(['success' => true, 'html' => $html, 'isReply' => true, 'parentId' => $parent_id]);

            } else {
                // Insert main comment
                $sql = "INSERT INTO comments (bug_id, user_id, comment_text) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$bug_id, $user_id, $content]);
                $new_id = $pdo->lastInsertId();

                // --- Notification Logic ---
                // Notify bug owner about the new comment
                $bug_info_stmt = $pdo->prepare("SELECT user_id, title FROM bugs WHERE id = ?");
                $bug_info_stmt->execute([$bug_id]);
                $bug_info = $bug_info_stmt->fetch();

                if ($bug_info && $user_id != $bug_info['user_id']) {
                    $user_name = $_SESSION['user_name'];
                    $message = htmlspecialchars($user_name) . " commented on your bug: \"" . htmlspecialchars(substr($bug_info['title'], 0, 30)) . "...\"";
                    $link = "post-details.php?id=$bug_id#comment-$new_id";
                    
                    $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                    $pdo->prepare($notif_sql)->execute([$bug_info['user_id'], $user_id, 'new_comment', $message, $link]);
                }
                // --- End Notification Logic ---

                // Fetch the new comment with ALL fields including created_at
                $fetch_sql = "SELECT c.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?";
                $stmt = $pdo->prepare($fetch_sql);
                $stmt->execute([$new_id]);
                $comment = $stmt->fetch(PDO::FETCH_ASSOC);
                $comment['replies'] = []; // New comments have no replies
                // The comment_card component expects a $comment variable.

                ob_start();
                include __DIR__ . '/Components/comment_card.php';
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

    // Handle Bug Saving
    if (isset($_POST['save_bug'])) {
        $bug_id = (int)$_POST['bug_id'];
        $save = $_POST['save'] === 'true';
        
        try {
            if ($save) {
                $save_sql = "INSERT INTO user_bug_saves (user_id, bug_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP";
                $pdo->prepare($save_sql)->execute([$_SESSION['user_id'], $bug_id]);
            } else {
                $remove_sql = "DELETE FROM user_bug_saves WHERE user_id = ? AND bug_id = ?";
                $pdo->prepare($remove_sql)->execute([$_SESSION['user_id'], $bug_id]);
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
                $stmt = $pdo->prepare("UPDATE comments SET comment_text = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $comment_id, $user_id]);
            } elseif (isset($_POST['edit_reply'])) {
                $reply_id = (int)$_POST['reply_id'];
                $stmt = $pdo->prepare("UPDATE comment_replies SET content = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$content, $reply_id, $user_id]);
            }

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'content' => nl2br(htmlspecialchars($content))]);
            } else {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this or it does not exist.']);
            }

        } catch (PDOException $e) {
            error_log("Comment/Reply edit failed: " . $e->getMessage());
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
                // Also delete replies to this comment to avoid orphaned records
                $pdo->prepare("DELETE FROM comment_replies WHERE comment_id = ?")->execute([$comment_id]);
                // Delete the comment itself, ensuring the user is the owner
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $user_id]);
            } elseif (isset($_POST['delete_reply'])) {
                $reply_id = (int)$_POST['reply_id'];
                // Delete the reply, ensuring the user is the owner
                $stmt = $pdo->prepare("DELETE FROM comment_replies WHERE id = ? AND user_id = ?");
                $stmt->execute([$reply_id, $user_id]);
            }
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this or it does not exist.']);
                exit();
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Comment/Reply deletion failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error during deletion.']);
        }
        exit();
    }
}

// Check if bug ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: bug-post.php');
    exit();
}

$bug_id = (int)$_GET['id'];

$bug = null;
$item_not_found = false;
// Get bug details
try {
    $bug_sql = "SELECT b.*, u.name as user_name, u.avatar_color, u.id as user_id, u.title as user_title, u.profile_picture,
                        (SELECT COUNT(*) FROM solutions s WHERE s.bug_id = b.id AND s.is_approved = 1) as solution_count,
                        (SELECT COUNT(*) FROM comments c WHERE c.bug_id = b.id) as comment_count
                 FROM bugs b 
                 LEFT JOIN users u ON b.user_id = u.id 
                 WHERE b.id = ?";
    $bug_stmt = $pdo->prepare($bug_sql);
    $bug_stmt->execute([$bug_id]);
    $bug = $bug_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bug) {
        $item_not_found = true;
    } else {
    // Increment view count only if user hasn't viewed this bug in current session
    if (!isset($_SESSION['viewed_bugs'])) {
        $_SESSION['viewed_bugs'] = [];
    }
    
    if (!in_array($bug_id, $_SESSION['viewed_bugs'])) {
        $view_sql = "UPDATE bugs SET views = COALESCE(views, 0) + 1 WHERE id = ?";
        $view_stmt = $pdo->prepare($view_sql);
        $view_stmt->execute([$bug_id]);
        $_SESSION['viewed_bugs'][] = $bug_id;
        
        // Update the bug object with new view count
        $bug['views'] = ($bug['views'] ?? 0) + 1;
    }
    }
    
} catch (PDOException $e) {
    $error = "Error fetching bug details: " . $e->getMessage();
}

// Get bug images
$bug_images = [];
try {
    $image_stmt = $pdo->prepare("SELECT image_path FROM bug_images WHERE bug_id = ?");
    $image_stmt->execute([$bug_id]);
    $bug_images = $image_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching bug images: " . $e->getMessage());
}

// Get bug files
$bug_files = [];
try {
    $file_stmt = $pdo->prepare("SELECT file_path, original_name FROM bug_files WHERE bug_id = ?");
    $file_stmt->execute([$bug_id]);
    $bug_files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching bug files: " . $e->getMessage());
}

// Handle solution submission with file uploads
if (isset($_POST['submit_solution']) && isset($_SESSION['user_id'])) {
    $solution_text = trim($_POST['solution_text']);
    $solution_code = trim($_POST['solution_code'] ?? '');
    
    if (!empty($solution_text)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert solution
            $insert_solution_sql = "INSERT INTO solutions (bug_id, user_id, content, code_snippet) VALUES (?, ?, ?, ?)";
            $insert_solution_stmt = $pdo->prepare($insert_solution_sql);
            $insert_solution_stmt->execute([$bug_id, $_SESSION['user_id'], $solution_text, $solution_code]);
            $solution_id = $pdo->lastInsertId();
            
            // Handle image uploads
            if (!empty($_FILES['solution_images']['name'][0])) {
                $upload_dir = __DIR__ . '/uploads/solutions/images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['solution_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['solution_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($_FILES['solution_images']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $image_sql = "INSERT INTO solution_images (solution_id, image_path) VALUES (?, ?)";
                            $image_stmt = $pdo->prepare($image_sql);
                            $image_stmt->execute([$solution_id, 'uploads/solutions/images/' . $file_name]);
                        }
                    }
                }
            }
            
            // Handle file uploads
            if (!empty($_FILES['solution_files']['name'][0])) {
                $upload_dir = __DIR__ . '/uploads/solutions/files/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['solution_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['solution_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($_FILES['solution_files']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        $original_name = $_FILES['solution_files']['name'][$key];
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $file_sql = "INSERT INTO solution_files (solution_id, file_path, original_name) VALUES (?, ?, ?)";
                            $file_stmt = $pdo->prepare($file_sql);
                            $file_stmt->execute([$solution_id, 'uploads/solutions/files/' . $file_name, $original_name]);
                        }
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // --- Notification Logic for New Solution ---
            // Notify bug owner that a new solution has been proposed
            if ($bug['user_id'] != $_SESSION['user_id']) { // Don't notify if user solves their own bug
                $user_name = $_SESSION['user_name'];
                $bug_title = $bug['title'];
                $message = htmlspecialchars($user_name) . " proposed a solution for your bug: \"" . htmlspecialchars(substr($bug_title, 0, 30)) . "...\"";
                $link = "dashboard.php?tab=pending-solutions-tab";

                $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($notif_sql)->execute([$bug['user_id'], $_SESSION['user_id'], 'new_solution', $message, $link]);
            }
            // --- End Notification Logic ---


            // Redirect with a success flag instead of using session
            header("Location: post-details.php?id=$bug_id&solution_submitted=1#solutions");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $solution_error = "Error submitting solution: " . $e->getMessage();
        }
    } else {
        $solution_error = "Solution description cannot be empty";
    }
}

// Handle solution approval (only bug owner can approve)
if (isset($_POST['approve_solution']) && isset($_SESSION['user_id'])) {
    $solution_id = (int)$_POST['solution_id'];
    
    // Check if current user is the bug owner
    if ($_SESSION['user_id'] == $bug['user_id']) {
        try {
            $approve_sql = "UPDATE solutions SET is_approved = 1, updated_at = NOW() WHERE id = ?";
            $approve_stmt = $pdo->prepare($approve_sql);
            $approve_stmt->execute([$solution_id]);
            
            // Update bug status to solved
            $update_bug_sql = "UPDATE bugs SET status = 'solved' WHERE id = ?";
            $update_bug_stmt = $pdo->prepare($update_bug_sql);
            $update_bug_stmt->execute([$bug_id]);

            // --- Award Reputation to Solution Author ---
            $solution_author_stmt = $pdo->prepare("SELECT user_id FROM solutions WHERE id = ?");
            $solution_author_stmt->execute([$solution_id]);
            $solution_author_id = $solution_author_stmt->fetchColumn();
            $pdo->prepare("UPDATE users SET reputation = reputation + 15 WHERE id = ?")->execute([$solution_author_id]);

            // --- Notification Logic for Approved Solution ---
            // Notify the solution author that their solution was approved
            $solution_info_stmt = $pdo->prepare("SELECT s.user_id, b.title FROM solutions s JOIN bugs b ON s.bug_id = b.id WHERE s.id = ?");
            $solution_info_stmt->execute([$solution_id]);
            $solution_info = $solution_info_stmt->fetch();

            // Only notify if the approver is not the solution author
            if ($solution_info && $_SESSION['user_id'] != $solution_info['user_id']) {
                $bug_owner_name = $bug['user_name'];
                $message = "Your solution for \"" . htmlspecialchars(substr($solution_info['title'], 0, 30)) . "...\" was approved!";
                $link = "solution-details.php?id=$solution_id";

                $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($notif_sql)->execute([$solution_info['user_id'], $_SESSION['user_id'], 'solution_approved', $message, $link]);
            }
            // --- End Notification Logic ---

            $_SESSION['success'] = "Solution approved successfully!";
            header("Location: post-details.php?id=$bug_id#solutions");
            exit();
        } catch (PDOException $e) {
            $solution_error = "Error approving solution: " . $e->getMessage();
        }
    } else {
        $solution_error = "Only the bug reporter can approve solutions";
    }
}

// Get all comments for this bug
$comments = [];

try {
    $comment_sql = "SELECT c.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture
                    FROM comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.bug_id = ?
                    ORDER BY c.created_at DESC";
    $comment_stmt = $pdo->prepare($comment_sql);
    $comment_stmt->bindValue(1, $bug_id, PDO::PARAM_INT);
    $comment_stmt->execute();
    $comments = $comment_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total comments count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE bug_id = ?");
    $count_stmt->execute([$bug_id]);
    $total_comments = $count_stmt->fetchColumn();
    
    // Get replies for each comment
    foreach ($comments as &$comment) {
        $reply_sql = "SELECT r.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture
                      FROM comment_replies r
                      LEFT JOIN users u ON r.user_id = u.id
                      WHERE r.comment_id = ?
                      ORDER BY r.created_at DESC";
        $reply_stmt = $pdo->prepare($reply_sql);
        $reply_stmt->execute([$comment['id']]);
        $comment['replies'] = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($comment); // Unset the reference to avoid scope issues with AJAX handlers.
} catch (PDOException $e) {
    error_log("Error fetching comments: " . $e->getMessage());
}

// Get solutions for this bug - ONLY APPROVED SOLUTIONS
$solutions = [];

try {
    // Get total solutions count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM solutions WHERE bug_id = ? AND is_approved = 1");
    $count_stmt->execute([$bug_id]);
    $total_solutions = $count_stmt->fetchColumn();
    
    // Get APPROVED solutions with votes and saves count
    $solution_sql = "SELECT s.*, u.name as user_name, u.avatar_color, u.id as user_id, u.profile_picture,
                            (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes,
                            (SELECT COUNT(*) FROM solution_saves WHERE solution_id = s.id) as saves_count,
                            s.views as views_count
                     FROM solutions s
                     LEFT JOIN users u ON s.user_id = u.id
                     WHERE s.bug_id = ? AND s.is_approved = 1
                     ORDER BY s.created_at DESC";
    
    $solution_stmt = $pdo->prepare($solution_sql);
    $solution_stmt->bindValue(1, $bug_id, PDO::PARAM_INT);
    $solution_stmt->execute();
    $solutions = $solution_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching solutions: " . $e->getMessage());
}

// Get solution images and files
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
unset($solution);

// Check if current user has saved this bug
$is_saved = false;
if (isset($_SESSION['user_id'])) {
    try {
        $saved_sql = "SELECT bug_id FROM user_bug_saves WHERE user_id = ? AND bug_id = ?";
        $saved_stmt = $pdo->prepare($saved_sql);
        $saved_stmt->execute([$_SESSION['user_id'], $bug_id]);
        $is_saved = $saved_stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error checking saved bug: " . $e->getMessage());
    }
}

// Handle edit bug redirect
if (isset($_GET['edit']) && $_GET['edit'] == 'true' && isset($_SESSION['user_id'])) {
    // Check if current user is the bug owner
    if ($_SESSION['user_id'] == $bug['user_id']) {
        // Store bug data in session for pre-filling the form
        $_SESSION['edit_bug_data'] = [
            'id' => $bug_id,
            'title' => $bug['title'],
            'description' => $bug['description'],
            'code_snippet' => $bug['code_snippet'],
            'tags' => $bug['tags'],
            'priority' => $bug['priority']
        ];
        
        // Redirect to dashboard with edit mode
        header("Location: dashboard.php?tab=report-tab&edit=" . $bug_id);
        exit();
    } else {
        $error = "You can only edit your own bugs";
    }
}

// Get related bugs for the bottom section
$related_bugs = [];
if (!empty($bug['tags'])) {
    $tags = explode(',', $bug['tags']);
    $tag_conditions = [];
    $tag_params = [];
    
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            $tag_conditions[] = "b.tags LIKE ?";
            $tag_params[] = "%$tag%";
        }
    }
    
    if (!empty($tag_conditions)) {
        $tag_sql = "SELECT b.id, b.title, b.status, b.priority, b.created_at 
                    FROM bugs b 
                    WHERE b.id != ? AND (" . implode(' OR ', $tag_conditions) . ")
                    ORDER BY b.created_at DESC 
                    LIMIT 5";
        
        try {
            $related_stmt = $pdo->prepare($tag_sql);
            $related_stmt->execute(array_merge([$bug_id], $tag_params));
            $related_bugs = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching related bugs: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($bug['title']); ?> - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
        /* Base Responsive Scaling */
        :root {
            --scale-factor: 1;
            --font-size-base: 1rem;
            --padding-base: 1rem;
            --margin-base: 1rem;
            --border-radius-base: 0.5rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1000px) {
            :root {
                --scale-factor: 0.95;
                --font-size-base: 0.95rem;
            }
        }

        @media (max-width: 768px) {
            :root {
                --scale-factor: 0.9;
                --font-size-base: 0.9rem;
                --padding-base: 0.875rem;
                --margin-base: 0.875rem;
            }
        }

        @media (max-width: 600px) {
            :root {
                --scale-factor: 0.85;
                --font-size-base: 0.85rem;
                --padding-base: 0.75rem;
                --margin-base: 0.75rem;
                --border-radius-base: 0.375rem;
            }
        }

        @media (max-width: 400px) {
            :root {
                --scale-factor: 0.8;
                --font-size-base: 0.8rem;
                --padding-base: 0.625rem;
                --margin-base: 0.625rem;
                --border-radius-base: 0.25rem;
            }
        }

        /* Apply scaling to all elements */
        * {
            box-sizing: border-box;
        }

        html {
            font-size: var(--font-size-base);
        }

        /* Page Header */
        .page-header {
            padding: calc(80px * var(--scale-factor)) 0 calc(20px * var(--scale-factor));
            text-align: left;
        }
        .page-header h1 {
            font-size: calc(2.5rem * var(--scale-factor));
            margin-bottom: calc(16px * var(--scale-factor));
            font-weight: 700;
            line-height: 1.2;
        }

        .page-meta {
            display: flex;
            gap: calc(20px * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .page-meta-group {
            display: flex;
            flex-wrap: wrap;
            gap: calc(15px * var(--scale-factor));
            justify-content: flex-start;
            /* width: 100%; */ /* Removing this allows groups to sit side-by-side */
        }

        .page-meta-group:not(:last-child) {
            margin-bottom: calc(15px * var(--scale-factor));
        }


        .meta-item {
            display: flex;
            align-items: center;
            gap: calc(6px * var(--scale-factor));
            color: var(--text-muted);
            font-size: calc(0.9rem * var(--scale-factor));
        }

        .severity {
            padding: calc(4px * var(--scale-factor)) calc(10px * var(--scale-factor));
            border-radius: calc(20px * var(--scale-factor));
            font-size: calc(0.8rem * var(--scale-factor));
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

        .status-badge {
            padding: calc(6px * var(--scale-factor)) calc(12px * var(--scale-factor));
            border-radius: calc(20px * var(--scale-factor));
            font-size: calc(0.8rem * var(--scale-factor));
            font-weight: 600;
        }

        .status-open {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .status-in-progress {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .status-solved {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
        }

        .status-closed {
            background: rgba(139, 92, 246, 0.15);
            color: var(--accent-secondary);
        }

        /* Main Content - Single Column */
        .main-content {
            margin-bottom: calc(60px * var(--scale-factor));
        }

        /* Bug Details */
        .bug-details {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(30px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            margin-bottom: calc(30px * var(--scale-factor));
        }

        .bug-description {
            color: var(--text-secondary);
            margin-bottom: calc(30px * var(--scale-factor));
            line-height: 1.7;
            font-size: calc(1.05rem * var(--scale-factor));
        }

        .section-title {
            font-size: calc(1.5rem * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            padding-bottom: calc(10px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }

        /* Code Snippet */
        .code-snippet {
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(20px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: calc(0.95rem * var(--scale-factor));
            line-height: 1.5;
            border: 1px solid var(--border);
            position: relative;
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
            background: transparent !important;
            max-height: calc(300px * var(--scale-factor));
            overflow: hidden;
        }

        .code-snippet.expanded pre {
            max-height: none;
            overflow: auto;
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
            font-size: calc(0.9rem * var(--scale-factor));
            transition: var(--transition);
            z-index: 10;
        }

        .view-code-toggle:hover {
            background: var(--accent-primary);
            transform: translateY(calc(-2px * var(--scale-factor)));
        }

        /* Image Gallery */
        .bug-images {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
            flex-wrap: wrap;
        }

        .bug-image {
            width: calc(200px * var(--scale-factor));
            height: calc(200px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            object-fit: cover;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .bug-image:hover {
            transform: scale(1.02);
            box-shadow: 0 calc(3px * var(--scale-factor)) calc(10px * var(--scale-factor)) rgba(0, 0, 0, 0.2);
        }

        /* File Attachments */
        .file-attachments {
            display: flex;
            flex-direction: row;
            gap: calc(10px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
            flex-wrap: wrap;
        }

        .file-attachment {
            display: flex;
            align-items: center;
            gap: calc(10px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(15px * var(--scale-factor));
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border: 1px solid var(--border);
            width: fit-content; /* Make button only as wide as its content */
        }

        .file-attachment:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .file-icon {
            font-size: calc(1.2rem * var(--scale-factor));
        }

        .file-name {
            flex: 1;
            font-size: calc(0.9rem * var(--scale-factor));
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tags */
        .bug-tags {
            display: flex;
            gap: calc(10px * var(--scale-factor));
            flex-wrap: wrap;
            margin: calc(20px * var(--scale-factor)) 0;
        }

        .tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: calc(6px * var(--scale-factor)) calc(14px * var(--scale-factor));
            border-radius: calc(20px * var(--scale-factor));
            font-size: calc(0.85rem * var(--scale-factor));
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: calc(12px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
        }

        .user-avatar {
            width: calc(50px * var(--scale-factor));
            height: calc(50px * var(--scale-factor));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: calc(1rem * var(--scale-factor));
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
            font-size: calc(1rem * var(--scale-factor));
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .user-name:hover {
            color: var(--accent-primary);
        }

        .post-time {
            color: var(--text-muted);
            font-size: calc(0.85rem * var(--scale-factor));
        }

        .bug-meta-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Actions */
        .bug-actions {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            margin: calc(20px * var(--scale-factor)) 0;
            flex-wrap: wrap;
        }

        .btn {
            padding: calc(12px * var(--scale-factor)) calc(20px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            font-size: calc(1rem * var(--scale-factor));
            display: inline-flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: 0 calc(4px * var(--scale-factor)) calc(14px * var(--scale-factor)) rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(calc(-2px * var(--scale-factor)));
            box-shadow: 0 calc(6px * var(--scale-factor)) calc(20px * var(--scale-factor)) rgba(99, 102, 241, 0.6);
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
            gap: calc(6px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(16px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            font-size: calc(0.9rem * var(--scale-factor));
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

        /* Comments and Solutions */
        .comments-section, .solutions-section {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(30px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            margin-bottom: calc(30px * var(--scale-factor));
        }

        .comment, .solution {
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(20px * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        .solution-actions {
            display: flex;
            gap: calc(10px * var(--scale-factor));
        }

        .approve-badge {
            background: var(--success);
        }

        .solution-code {
            background: transparent;
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            margin: calc(15px * var(--scale-factor)) 0;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: calc(0.9rem * var(--scale-factor));
            line-height: 1.5;
            border: 1px solid var(--border);
            position: relative;
        }

        .solution-code pre {
            margin: 0;
            max-height: calc(250px * var(--scale-factor));
            overflow: hidden;
        }

        .solution-code pre, .solution-code pre code {
            background: transparent !important;
        }


        .solution-code.expanded pre {
            max-height: none;
            overflow: auto;
        }

        /* Forms */
        .comment-form, .solution-form {
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
            margin-bottom: calc(8px * var(--scale-factor));
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
            font-size: calc(1rem * var(--scale-factor));
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

        /* Refined Solution Card Styles */
        .solution-card {
            background: var(--bg-secondary);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(25px * var(--scale-factor));
            margin-bottom: calc(25px * var(--scale-factor));
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
        }

        .solution-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(calc(-2px * var(--scale-factor)));
            box-shadow: 0 calc(8px * var(--scale-factor)) calc(25px * var(--scale-factor)) rgba(0, 0, 0, 0.4);
        }

        .solution-user-info {
            display: flex;
            align-items: center;
            gap: calc(15px * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
        }

        .solution-user-avatar {
            width: calc(50px * var(--scale-factor));
            height: calc(50px * var(--scale-factor));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: calc(1rem * var(--scale-factor));
            flex-shrink: 0;
        }

        .solution-user-details {
            flex: 1;
        }

        .solution-user-name {
            color: var(--text-primary);
            font-weight: 600;
            font-size: calc(1.1rem * var(--scale-factor));
            text-decoration: none;
            display: block;
            margin-bottom: calc(5px * var(--scale-factor));
        }

        .solution-user-name:hover {
            color: var(--accent-primary);
        }

        .solution-meta {
            display: flex;
            gap: calc(15px * var(--scale-factor));
            color: var(--text-muted);
            font-size: calc(0.9rem * var(--scale-factor));
            flex-wrap: wrap;
        }
        .solution-stat {
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            color: var(--text-muted);
            font-size: calc(0.9rem * var(--scale-factor));
        }

        .solution-stat i {
            font-size: calc(1.1rem * var(--scale-factor));
        }

        .solution-stat .stat-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .solution-description {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: calc(20px * var(--scale-factor));
            font-size: calc(1.05rem * var(--scale-factor));
        }

        .solution-attachments {
            margin: calc(20px * var(--scale-factor)) 0;
        }

        .solution-images {
            display: flex;
            gap: calc(12px * var(--scale-factor));
            margin-bottom: calc(15px * var(--scale-factor));
            flex-wrap: wrap;
        }

        .solution-image {
            width: calc(140px * var(--scale-factor));
            height: calc(140px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .solution-image:hover {
            transform: scale(1.05);
            border-color: var(--accent-primary);
        }

        .solution-files {
            display: flex;
            flex-direction: column;
            gap: calc(10px * var(--scale-factor));
        }

        .solution-file {
            display: flex;
            align-items: center;
            gap: calc(12px * var(--scale-factor));
            padding: calc(12px * var(--scale-factor)) calc(16px * var(--scale-factor));
            background: var(--bg-card);
            border-radius: calc(8px * var(--scale-factor));
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .solution-file:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateX(calc(5px * var(--scale-factor)));
        }

        @media (max-width: 768px) {
            .solution-file {
                width: fit-content;
            }
        }

        .solution-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: calc(20px * var(--scale-factor));
            padding-top: calc(20px * var(--scale-factor));
            border-top: 1px solid var(--border);
        }

        .solution-actions-full {
            display: flex;
            gap: calc(12px * var(--scale-factor));
            flex-wrap: wrap;
        }

        .solution-action-btn {
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(18px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            font-size: calc(0.9rem * var(--scale-factor));
            text-decoration: none;
            font-weight: 500;
        }

        .solution-action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateY(calc(-2px * var(--scale-factor)));
        }

        .view-full-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: calc(1rem * var(--scale-factor));
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            transition: var(--transition);
        }

        .view-full-link:hover {
            color: var(--accent-secondary);
            transform: translateX(calc(5px * var(--scale-factor)));
        }

        /* Bottom Sections */
        .bottom-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(calc(300px * var(--scale-factor)), 1fr));
            gap: calc(25px * var(--scale-factor));
            margin-top: calc(40px * var(--scale-factor));
        }

        .bottom-card {
            background: var(--bg-card);
            border-radius: calc(12px * var(--scale-factor));
            padding: calc(25px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: calc(15px * var(--scale-factor));
        }

        .stat-item {
            text-align: center;
            padding: calc(15px * var(--scale-factor));
            background: var(--bg-secondary);
            border-radius: calc(8px * var(--scale-factor));
        }

        .stat-number {
            font-size: calc(1.8rem * var(--scale-factor));
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: calc(5px * var(--scale-factor));
        }

        .stat-label {
            font-size: calc(0.9rem * var(--scale-factor));
            color: var(--text-muted);
        }

        /* Enhanced Image Modal */
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
            max-height: calc(100vh - 100px);
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
            transform: translateY(calc(-2px * var(--scale-factor)));
        }

        .image-counter {
            color: var(--text-primary);
            font-size: calc(0.9rem * var(--scale-factor));
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
            background: #dc2626;
        }

        .code-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: calc(20px * var(--scale-factor));
            padding-bottom: calc(15px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
        }

        .code-modal-title {
            font-size: calc(1.3rem * var(--scale-factor));
            color: var(--text-primary);
            font-weight: 600;
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
            background: #dc2626;
        }

        .code-modal-body {
            background: transparent;
            border-radius: calc(8px * var(--scale-factor));
            overflow-x: auto;
        }

        .code-modal-body pre {
            margin: 0;
            font-family: 'Fira Code', monospace;
            font-size: calc(0.95rem * var(--scale-factor));
            line-height: 1.5;
        }
        
        /* File Upload Styles from dashboard.php */
        .file-upload-container {
            margin-bottom: calc(20px * var(--scale-factor));
        }

        .file-upload-label {
            display: block;
            margin-bottom: calc(8px * var(--scale-factor));
            color: var(--text-secondary);
            font-weight: 500;
        }

        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(20px * var(--scale-factor));
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload-box:hover {
            border-color: var(--accent-primary);
        }

        .file-upload-box i {
            font-size: calc(2rem * var(--scale-factor));
            color: var(--text-muted);
            margin-bottom: calc(10px * var(--scale-factor));
        }

        .file-upload-box p {
            color: var(--text-muted);
            margin-bottom: calc(10px * var(--scale-factor));
        }

        .file-upload-box input[type="file"] {
            display: none;
        }

        .file-preview {
            margin-top: calc(15px * var(--scale-factor));
            display: flex;
            flex-wrap: wrap;
            gap: calc(10px * var(--scale-factor));
        }

        .file-preview-item {
            background: var(--bg-secondary);
            border-radius: calc(6px * var(--scale-factor));
            padding: calc(8px * var(--scale-factor)) calc(12px * var(--scale-factor));
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            font-size: calc(0.9rem * var(--scale-factor));
        }

        .remove-file {
            color: var(--danger);
            cursor: pointer;
        }

        /* File Upload Styles */
        .file-upload-group {
            margin-bottom: calc(15px * var(--scale-factor));
        }

        .file-upload-label {
            display: block;
            margin-bottom: calc(8px * var(--scale-factor));
            color: var(--text-primary);
            font-weight: 500;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            width: 100%;
            padding: calc(12px * var(--scale-factor)) calc(15px * var(--scale-factor));
            border-radius: calc(8px * var(--scale-factor));
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: calc(1rem * var(--scale-factor));
        }

        .file-input::file-selector-button {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: calc(8px * var(--scale-factor)) calc(16px * var(--scale-factor));
            border-radius: calc(6px * var(--scale-factor));
            cursor: pointer;
            font-weight: 500;
            margin-right: calc(10px * var(--scale-factor));
            transition: var(--transition);
        }

        .file-input::file-selector-button:hover {
            background: var(--accent-secondary);
        }

        .file-hint {
            font-size: calc(0.85rem * var(--scale-factor));
            color: var(--text-muted);
            margin-top: calc(5px * var(--scale-factor));
        }

        /* Load More Button */
        .load-more-container {
            text-align: center;
            margin-top: calc(30px * var(--scale-factor));
            padding-top: calc(20px * var(--scale-factor));
            border-top: 1px solid var(--border);
        }

        .load-more-btn {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            border: none;
            padding: calc(14px * var(--scale-factor)) calc(28px * var(--scale-factor));
            border-radius: calc(10px * var(--scale-factor));
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: calc(10px * var(--scale-factor));
            font-size: calc(1rem * var(--scale-factor));
            box-shadow: 0 calc(4px * var(--scale-factor)) calc(14px * var(--scale-factor)) rgba(99, 102, 241, 0.4);
        }

        .load-more-btn:hover {
            transform: translateY(calc(-3px * var(--scale-factor)));
            box-shadow: 0 calc(8px * var(--scale-factor)) calc(25px * var(--scale-factor)) rgba(99, 102, 241, 0.6);
        }

        .load-more-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 calc(4px * var(--scale-factor)) calc(14px * var(--scale-factor)) rgba(99, 102, 241, 0.2);
        }

        .load-more-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .load-more-btn.loading .btn-text {
            display: none;
        }

        .load-more-btn.loading .loading-text {
            display: inline;
        }

        .loading-text {
            display: none;
        }

        /* Reply Form Styles */
        .reply-form {
            margin-top: calc(15px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            background: rgba(255, 255, 255, 0.03);
            border-radius: calc(8px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        .reply-btn {
            background: none;
            border: none;
            color: var(--accent-primary);
            cursor: pointer;
            font-size: calc(0.85rem * var(--scale-factor));
            display: flex;
            align-items: center;
            gap: calc(5px * var(--scale-factor));
            padding: calc(5px * var(--scale-factor)) calc(10px * var(--scale-factor));
            border-radius: calc(4px * var(--scale-factor));
            transition: var(--transition);
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
            font-size: calc(0.9rem * var(--scale-factor));
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: calc(8px * var(--scale-factor));
            transition: var(--transition);
        }
        .view-replies-btn:hover {
            color: var(--accent-primary);
        }

        .reply-btn:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .comment-reply {
            margin-top: calc(15px * var(--scale-factor));
            background: rgba(255,255,255,0.03);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            border: 1px solid var(--border);
        }

        .comment-reply .user-avatar {
            width: calc(40px * var(--scale-factor));
            height: calc(40px * var(--scale-factor));
        }

        /* Responsive Design - Layout Adjustments */
        @media (max-width: 1000px) {
            .page-header h1 {
                font-size: calc(2.2rem * var(--scale-factor));
            }
            
            .bug-details, .comments-section, .solutions-section {
                padding: calc(25px * var(--scale-factor));
            }
            
            .section-title {
                font-size: calc(1.4rem * var(--scale-factor));
            }
            
            .bug-image {
                width: calc(180px * var(--scale-factor));
                height: calc(180px * var(--scale-factor));
            }
            
            .solution-image {
                width: calc(130px * var(--scale-factor));
                height: calc(130px * var(--scale-factor));
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: calc(2rem * var(--scale-factor));
            }
            
            .page-meta {
                flex-direction: column;
                gap: calc(10px * var(--scale-factor));
            }
            
            .bug-image {
                width: calc(150px * var(--scale-factor));
                height: calc(150px * var(--scale-factor));
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .bug-actions {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 10px;
                justify-content: flex-start;
            }

            .action-btn {
                justify-content: center;
                text-align: center;
            }

            .file-attachments {
                flex-direction: column;
            }

            .file-attachment {
                width: 100%;
                min-width: auto;
            }

            .bottom-sections {
                grid-template-columns: 1fr;
            }

            .solution-header {
                flex-direction: column;
                gap: calc(10px * var(--scale-factor));
            }
            
            .solution-actions {
                width: 100%;
                justify-content: space-between;
            }

            .solution-footer {
                flex-direction: column;
                gap: calc(15px * var(--scale-factor));
                align-items: flex-start;
            }

            .solution-actions-full {
                width: 100%;
                justify-content: space-between;
            }

            .solution-stats {
                flex-direction: column;
                gap: calc(10px * var(--scale-factor));
            }

            .solution-image {
                width: calc(120px * var(--scale-factor));
                height: calc(120px * var(--scale-factor));
            }

            .code-modal-content {
                padding: calc(15px * var(--scale-factor));
            }

            .code-modal-body {
                padding: calc(15px * var(--scale-factor));
            }

           

            .user-details {
                align-items: center;
            }

            .solution-user-info {
                flex-direction: column;
                text-align: center;
                gap: calc(15px * var(--scale-factor));
            }

            .solution-user-details {
                align-items: center;
            }

            .bug-details {
                display: flex;
                flex-direction: column;
            }

            .bug-tags {
                order: 1;
            }

            .bug-meta-footer {
                order: 2;
            }

            .bug-meta-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: calc(1.8rem * var(--scale-factor));
            }
            
            .bug-details, .comments-section, .solutions-section, .bottom-card {
                padding: calc(20px * var(--scale-factor));
            }
            
            .section-title {
                font-size: calc(1.3rem * var(--scale-factor));
            }
            
            .bug-image {
                width: 100%;
                height: auto;
                max-width: calc(300px * var(--scale-factor));
                max-height: calc(300px * var(--scale-factor));
            }
            
            .solution-image {
                width: calc(100px * var(--scale-factor));
                height: calc(100px * var(--scale-factor));
            }
            
            .modal-content {
                max-width: 100%;
                padding: calc(10px * var(--scale-factor));
            }

            .modal-image {
                max-height: calc(100vh - 80px);
            }

            .solution-images {
                justify-content: center;
            }

            .solution-card {
                padding: calc(20px * var(--scale-factor));
            }
            
            .bug-actions {
                gap: calc(10px * var(--scale-factor));
            }
            
            .action-btn {
                padding: calc(8px * var(--scale-factor)) calc(12px * var(--scale-factor));
                font-size: calc(0.85rem * var(--scale-factor));
            }
            
            .btn {
                padding: calc(10px * var(--scale-factor)) calc(16px * var(--scale-factor));
                font-size: calc(0.9rem * var(--scale-factor));
            }
        }

        @media (max-width: 400px) {
            .page-header h1 {
                font-size: calc(1.6rem * var(--scale-factor));
                line-height: 1.3;
            }
            
            .bug-details, .comments-section, .solutions-section, .bottom-card {
                padding: calc(15px * var(--scale-factor));
            }
            
            .section-title {
                font-size: calc(1.2rem * var(--scale-factor));
                margin-bottom: calc(15px * var(--scale-factor));
            }
            
            .bug-image {
                max-width: 100%;
                max-height: calc(250px * var(--scale-factor));
            }
            
            .solution-image {
                width: calc(90px * var(--scale-factor));
                height: calc(90px * var(--scale-factor));
            }
            
            .modal-content {
                padding: calc(5px * var(--scale-factor));
            }

            .modal-image {
                max-height: calc(100vh - 60px);
            }

            .solution-card {
                padding: calc(15px * var(--scale-factor));
            }
            
            .bug-actions {
                gap: calc(8px * var(--scale-factor));
            }
            
            .action-btn {
                padding: calc(6px * var(--scale-factor)) calc(10px * var(--scale-factor));
                font-size: calc(0.8rem * var(--scale-factor));
            }
            
            .btn {
                padding: calc(8px * var(--scale-factor)) calc(14px * var(--scale-factor));
                font-size: calc(0.85rem * var(--scale-factor));
            }
            
            .user-avatar, .solution-user-avatar {
                width: calc(40px * var(--scale-factor));
                height: calc(40px * var(--scale-factor));
                font-size: calc(0.9rem * var(--scale-factor));
            }
            
            .tag {
                padding: calc(4px * var(--scale-factor)) calc(10px * var(--scale-factor));
                font-size: calc(0.75rem * var(--scale-factor));
            }
            
            .file-attachment {
                padding: calc(8px * var(--scale-factor)) calc(12px * var(--scale-factor));
                min-width: auto;
            }
        }

        /* Additional Mobile Optimizations */
        @media (max-width: 768px) {
            .container {
                padding-left: calc(15px * var(--scale-factor));
                padding-right: calc(15px * var(--scale-factor));
            }
            
            .main-content {
                margin-bottom: calc(40px * var(--scale-factor));
            }
            
            .comment-form, .solution-form {
                padding: calc(15px * var(--scale-factor));
            }
            
            .form-control {
                padding: calc(10px * var(--scale-factor)) calc(12px * var(--scale-factor));
            }
            
            textarea.form-control {
                min-height: calc(100px * var(--scale-factor));
            }
        }

        @media (max-width: 600px) {
            .container {
                padding-left: calc(10px * var(--scale-factor));
                padding-right: calc(10px * var(--scale-factor));
            }
            
            .page-header {
                padding: calc(60px * var(--scale-factor)) 0 calc(15px * var(--scale-factor));
            }
            
            .main-content {
                margin-bottom: calc(30px * var(--scale-factor));
            }
            
            .bug-details, .comments-section, .solutions-section {
                margin-bottom: calc(20px * var(--scale-factor));
            }
        }

        @media (max-width: 400px) {
            .container {
                padding-left: calc(8px * var(--scale-factor));
                padding-right: calc(8px * var(--scale-factor));
            }
            
            .page-header {
                padding: calc(50px * var(--scale-factor)) 0 calc(10px * var(--scale-factor));
            }
            
            .main-content {
                margin-bottom: calc(25px * var(--scale-factor));
            }
        }

        /* Ensure text remains readable at smallest sizes */
        @media (max-width: 400px) {
            .bug-description, .solution-description, .comment-text {
                font-size: calc(0.9rem * var(--scale-factor));
                line-height: 1.6;
            }
            
            .meta-item, .solution-stat, .stat-label {
                font-size: calc(0.8rem * var(--scale-factor));
            }
            
            .user-name, .solution-user-name {
                font-size: calc(0.95rem * var(--scale-factor));
            }
        }

        /* Touch-friendly improvements */
        @media (max-width: 768px) {
            .action-btn, .btn, .view-code-toggle, .close-modal, .close-code-modal {
                min-height: calc(44px * var(--scale-factor));
                min-width: calc(44px * var(--scale-factor));
            }
            
            .bug-image, .solution-image {
                cursor: pointer;
            }
            
            .file-attachment, .solution-file {
                min-height: calc(44px * var(--scale-factor));
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php if ($item_not_found): ?>
        <div class="container" style="text-align: center; padding: 100px 20px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">
            <div class="no-bugs" style="padding: 40px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border);">
                <i class="fas fa-ghost" style="font-size: 3rem; margin-bottom: 20px; color: var(--accent-primary);"></i>
                <h1 style="font-size: 2rem; color: var(--text-primary); margin-bottom: 10px;">Bug Not Found</h1>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto 20px;">The bug you are looking for may have been deleted or is no longer available.</p>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <a href="bug-post.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Bug Reports
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
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($bug['title']); ?></h1>
        <div class="page-meta">
            <div class="page-meta-group">
                <span class="severity severity-<?php echo strtolower($bug['priority']); ?>">

<style>
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.7);
        justify-content: center;
        align-items: center;
    }
    .modal-content-custom {
        background-color: var(--bg-card);
        margin: auto;
        padding: 30px;
        border: 1px solid var(--border);
        width: 90%;
        max-width: 500px;
        border-radius: 12px;
        text-align: center;
        animation: fadeIn 0.3s ease-out;
        position: relative;
    }
    .modal-icon {
        font-size: 3rem;
        color: var(--success);
        margin-bottom: 20px;
    }
    .modal-header-custom h2 {
        margin-top: 0;
        color: var(--text-primary);
        font-size: 1.8rem;
    }
    .modal-body-custom p {
        color: var(--text-secondary);
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 30px;
    }
    .modal-footer-custom {
        display: flex;
        justify-content: center;
    }
    .modal-footer-custom .btn {
        min-width: 120px;
    }
    #modalOkBtn {
        justify-content: center; /* Center the text inside the flex container */
        text-align: center;
    }
</style>



                    <?php echo ucfirst($bug['priority']); ?> Priority
                </span>
                <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($bug['status'])); ?>">
                    <?php echo ucfirst(str_replace('-', ' ', $bug['status'])); ?>
                </span>
            </div>
            <div class="page-meta-group">
                <span class="meta-item"><i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($bug['created_at'])); ?></span>
                <span class="meta-item"><i class="far fa-eye"></i> <?php echo $bug['views'] ?? 0; ?> views</span>
                <span class="meta-item"><i class="far fa-comment"></i> <?php echo $bug['comment_count']; ?> comments</span>
                <span class="meta-item"><i class="far fa-lightbulb"></i> <?php echo $bug['solution_count']; ?> solutions</span>
            </div>
        </div>
    </section>

    <!-- Main Content - Single Column -->
    <main class="container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="main-content">
            <!-- Bug Details -->
            <section class="bug-details" id="description">
                <h2 class="section-title">Bug Description</h2>
                <div class="bug-description">
                    <?php echo nl2br(htmlspecialchars($bug['description'])); ?>
                </div>

                <!-- Code Snippet -->
                <?php if (!empty($bug['code_snippet'])): ?>
                <div id="code">
                    <h2 class="section-title">Code Snippet</h2>
                    <div class="code-snippet" id="bug-code-snippet">
                        <pre><code class="language-<?php echo detectLanguage($bug['tags']); ?>"><?php echo htmlspecialchars($bug['code_snippet']); ?></code></pre>
                        <?php if (strlen($bug['code_snippet'] ?? '') > 1000): ?>
                        <button class="view-code-toggle" 
                                data-code="<?php echo htmlspecialchars(json_encode($bug['code_snippet']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-lang="<?php echo detectLanguage($bug['tags']); ?>"
                                onclick="openCodeModal(this)">
                            <i class="fas fa-expand"></i> View Full Code
                        </button>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Images -->
                <?php if (!empty($bug_images)): ?>
                <div id="images">
                    <h2 class="section-title">Screenshots</h2>
                    <div class="bug-images">
                        <?php foreach ($bug_images as $index => $image_path): ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Bug screenshot" class="bug-image" 
                                 onclick="openImageModal('<?php echo htmlspecialchars($image_path); ?>', <?php echo $index; ?>)">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Files -->
                <?php if (!empty($bug_files)): ?>
                <div id="files">
                    <h2 class="section-title">Attachments</h2>
                    <div class="file-attachments">
                        <?php foreach ($bug_files as $file): ?>
                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="file-attachment">
                                <i class="fas fa-file file-icon"></i>
                                <span class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                <i class="fas fa-download"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tags -->
                <?php if (!empty($bug['tags'])): ?>
                <div class="bug-tags">
                    <?php 
                    $tags = explode(',', $bug['tags']);
                    foreach ($tags as $tag):
                        $tag = trim($tag);
                        if (!empty($tag)):
                    ?>
                        <span class="tag <?php echo strtolower($tag); ?>"><?php echo htmlspecialchars($tag); ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>

                <!-- User Info -->
                <div class="bug-meta-footer">
                    <div class="user-info">
                        <a href="profile.php?id=<?php echo $bug['user_id']; ?>" class="user-avatar" style="background: <?php echo $bug['avatar_color'] ?? '#6366f1'; ?>; overflow: hidden;">
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

                    <!-- Actions -->
                    <div class="bug-actions">
                        <a href="#solutions" class="action-btn">
                            <i class="far fa-lightbulb"></i> Propose Solution
                        </a>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="action-btn save-btn <?php echo $is_saved ? 'active' : ''; ?>" 
                                data-bug-id="<?php echo $bug_id; ?>">
                            <i class="<?php echo $is_saved ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                            <span><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
                        </button>
                        <?php else: ?>
                        <a href="auth.php" class="action-btn">
                            <i class="far fa-bookmark"></i> Save
                        </a>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $bug['user_id']): ?>
                        <a href="post-details.php?id=<?php echo $bug_id; ?>&edit=true" class="action-btn">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php endif; ?>
                        
                        <a href="#comments" class="action-btn">
                            <i class="far fa-comment"></i> Comment
                        </a>
                    </div>
                </div>
            </section>

            <!-- Comments Section -->
            <section class="comments-section" id="comments">
                <h2 class="section-title">Comments (<?php echo $total_comments; ?>)</h2>
                
                <?php if (isset($comment_error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($comment_error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="comment-form">
                    <form class="comment-submit-form" method="POST" action="post-details.php?id=<?php echo $bug_id; ?>#comments">
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
                            <?php include __DIR__ . '/Components/comment_card.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p id="no-comments-message" style="text-align: center; color: var(--text-muted); padding: 20px;">No comments yet. Be the first to comment!</p>
                    <?php endif; ?>
                </div>

            </section>

            <!-- Solutions Section -->
            <section class="solutions-section" id="solutions">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 20px;">
                    <h2 class="section-title" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">Approved Solutions (<?php echo $total_solutions; ?>)</h2>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button id="toggleSolutionFormBtn" class="btn btn-primary">
                            <i class="far fa-lightbulb"></i> Propose Solution
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($solution_error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($solution_error); ?>
                    </div>
                <?php endif; ?>

                <div id="solutionFormContainer" style="display: none; margin-bottom: 30px;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="solution-form">
                        <form id="solution-form" method="POST" action="post-details.php?id=<?php echo $bug_id; ?>#solutions" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="solution_text" class="form-label">Propose a Solution *</label>
                                <textarea id="solution_text" name="solution_text" class="form-control" placeholder="Describe your solution to this bug in detail..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="solution_code" class="form-label">Code Solution (Optional)</label>
                                <textarea id="solution_code" name="solution_code" class="form-control code-font" placeholder="Provide any code that fixes the issue..." rows="8"></textarea>
                            </div>
                            
                            <!-- Solution Image Upload -->
                            <div class="file-upload-container">
                                <label class="file-upload-label">Upload Solution Images (Optional)</label>
                                <div class="file-upload-box" id="image-upload-box">
                                    <i class="fas fa-image"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="solution_images" name="solution_images[]" multiple accept="image/*">
                                </div>
                                <div id="image-preview" class="file-preview"></div>
                            </div>
                            
                            <!-- Solution File Upload -->
                            <div class="file-upload-container">
                                <label class="file-upload-label">Upload Solution Files (Optional)</label>
                                <div class="file-upload-box" id="file-upload-box">
                                    <i class="fas fa-file"></i>
                                    <p>Drag & drop files here or click to browse</p>
                                    <input type="file" id="solution_files" name="solution_files[]" multiple>
                                </div>
                                <div id="file-preview" class="file-preview"></div>
                            </div>
                            
                            <button type="submit" name="submit_solution" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Solution
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="solution-form">
                        <p>Please <a href="auth.php">sign in</a> to propose a solution.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="solutions-list" id="solutionsList">
                    <?php if (!empty($solutions)): ?>
                        <?php foreach ($solutions as $solution): ?>
                            <?php include __DIR__ . '/solution_card.php'; ?>
                        <?php endforeach; ?>
                        
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 20px;">No solutions proposed yet. Be the first to share a solution!</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Bottom Sections -->
            <div class="bottom-sections">
                <!-- Stats Card -->
                <div class="bottom-card">
                    <h3 style="margin-bottom: 20px;">Bug Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $bug['views'] ?? 0; ?></div>
                            <div class="stat-label">Views</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $bug['comment_count']; ?></div>
                            <div class="stat-label">Comments</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $bug['solution_count']; ?></div>
                            <div class="stat-label">Solutions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $bug['like_count'] ?? 0; ?></div>
                            <div class="stat-label">Likes</div>
                        </div>
                    </div>
                </div>

                <!-- Related Bugs -->
                <div class="bottom-card">
                    <h3 style="margin-bottom: 15px;">Related Bugs</h3>
                    <?php if (!empty($related_bugs)): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($related_bugs as $related_bug): ?>
                            <a href="post-details.php?id=<?php echo $related_bug['id']; ?>" 
                               style="display: block; padding: 10px; background: var(--bg-secondary); border-radius: 6px; text-decoration: none; color: var(--text-primary); transition: var(--transition);">
                                <div style="font-weight: 500; margin-bottom: 5px;"><?php echo htmlspecialchars($related_bug['title']); ?></div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted);">
                                    <span class="status-<?php echo str_replace(' ', '-', strtolower($related_bug['status'])); ?>"><?php echo ucfirst($related_bug['status']); ?></span>
                                    <span><?php echo timeAgo($related_bug['created_at']); ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-muted);">No related bugs found.</p>
                    <?php endif; ?>
                </div>

                <!-- Actions Card -->
                <div class="bottom-card">
                    <h3 style="margin-bottom: 15px;">Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="bug-post.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bugs
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['user_id'] == $bug['user_id']): ?>
                                <a href="post-details.php?id=<?php echo $bug_id; ?>&edit=true" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i> Edit Bug
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-secondary share-btn" data-url="<?php echo "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
                                <i class="fas fa-share"></i> Share Bug
                            </button>
                        <?php else: ?>
                            <a href="auth.php" class="btn btn-secondary">
                                <i class="fas fa-sign-in-alt"></i> Sign In to Interact
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php endif; ?>

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

    <!-- Solution Submitted Modal -->
    <div id="solutionSubmittedModal" class="modal-overlay">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <div class="modal-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Solution Submitted!</h2>
            </div>
            <div class="modal-body-custom">
                <p>Your solution has been submitted for approval. Thank you for your contribution!</p>
            </div>
            <div class="modal-footer-custom">
                <button id="modalOkBtn" class="btn btn-primary">OK</button>
            </div>
        </div>
    </div>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
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

        // Code modal functionality
        window.openCodeModal = function(button) {
            const modal = document.getElementById('codeModal');
            const modalCode = document.getElementById('modalCode');
            const code = JSON.parse(button.getAttribute('data-code'));
            const language = button.getAttribute('data-lang');
            
            modalCode.textContent = code;
            modalCode.className = ''; // Clear existing classes
            modalCode.className = 'language-' + language;
            modal.style.display = 'flex';
            
            // Re-highlight the code
            hljs.highlightElement(modalCode);
            
            document.body.style.overflow = 'hidden';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Use window.onload to ensure all content including scripts are loaded
            window.onload = function() {
                // Initialize syntax highlighting
                hljs.highlightAll();

                // Solution Submitted Modal Logic
                const solutionModal = document.getElementById('solutionSubmittedModal');
            const modalOkBtn = document.getElementById('modalOkBtn');
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('solution_submitted')) {
                solutionModal.style.display = 'flex';
            }

            function closeModal() {
                solutionModal.style.display = 'none';
                // Optional: remove the query parameter from the URL without reloading
                const newUrl = window.location.pathname + '?id=<?php echo $bug_id; ?>' + window.location.hash;
                window.history.replaceState({}, document.title, newUrl);
            }

            modalOkBtn.addEventListener('click', closeModal);
            solutionModal.addEventListener('click', (e) => { if (e.target === solutionModal) closeModal(); });
            };

            function closeImageModal() {
                const modal = document.getElementById('imageModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            document.getElementById('closeModal').addEventListener('click', closeImageModal);

            function closeCodeModal() {
                const modal = document.getElementById('codeModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            document.getElementById('closeCodeModal').addEventListener('click', closeCodeModal);

            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImageModal();
                }
            });

            // Close code modal when clicking outside
            document.getElementById('codeModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCodeModal();
                }
            });

            // Set up image click handlers for bug images
            document.querySelectorAll('.bug-image').forEach((img, index) => {
                img.addEventListener('click', function() {
                    const images = Array.from(document.querySelectorAll('.bug-image')).map(img => img.src);
                    currentImages = images;
                    openImageModal(this.src, index);
                });
            });

            // Set up image click handlers for solution images
            function openSolutionImageModal(imageSrc, index, images) {
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

            // Bug saving functionality
            const saveBtn = document.querySelector('.save-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
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
                    
                    // Send AJAX request to save/unsave
                    fetch('post-details.php?id=<?php echo $bug_id; ?>', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `save_bug=true&bug_id=${bugId}&save=${!isCurrentlySaved}`
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
            }

            // Share functionality
            const shareBtn = document.querySelector('.share-btn');
            if (shareBtn) {
                shareBtn.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    
                    if (navigator.share) {
                        navigator.share({
                            title: 'Bug Report - DevBug',
                            text: 'Check out this bug report on DevBug',
                            url: url
                        });
                    } else {
                        navigator.clipboard.writeText(url).then(() => {
                            alert('Bug link copied to clipboard!');
                        }).catch(() => {
                            // Fallback for older browsers
                            const tempInput = document.createElement('input');
                            tempInput.value = url;
                            document.body.appendChild(tempInput);
                            tempInput.select();
                            document.execCommand('copy');
                            document.body.removeChild(tempInput);
                            alert('Bug link copied to clipboard!');
                        });
                    }
                });
            }

            // File upload functionality for solution form
            const imageUploadBox = document.getElementById('image-upload-box');
            const fileUploadBox = document.getElementById('file-upload-box');
            const imageInput = document.getElementById('solution_images');
            const fileInput = document.getElementById('solution_files');
            const imagePreview = document.getElementById('image-preview');
            const filePreview = document.getElementById('file-preview');

            if (imageUploadBox) {
                // Image upload
                imageUploadBox.addEventListener('click', () => imageInput.click());
                imageUploadBox.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    imageUploadBox.style.borderColor = 'var(--accent-primary)';
                });
                imageUploadBox.addEventListener('dragleave', () => {
                    imageUploadBox.style.borderColor = 'var(--border)';
                });
                imageUploadBox.addEventListener('drop', (e) => {
                    e.preventDefault();
                    imageUploadBox.style.borderColor = 'var(--border)';
                    if (e.dataTransfer.files.length > 0) {
                        imageInput.files = e.dataTransfer.files;
                        handleImagePreview(e.dataTransfer.files);
                    }
                });
                imageInput.addEventListener('change', () => handleImagePreview(imageInput.files));

                // File upload
                fileUploadBox.addEventListener('click', () => fileInput.click());
                fileUploadBox.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    fileUploadBox.style.borderColor = 'var(--accent-primary)';
                });
                fileUploadBox.addEventListener('dragleave', () => {
                    fileUploadBox.style.borderColor = 'var(--border)';
                });
                fileUploadBox.addEventListener('drop', (e) => {
                    e.preventDefault();
                    fileUploadBox.style.borderColor = 'var(--border)';
                    if (e.dataTransfer.files.length > 0) {
                        fileInput.files = e.dataTransfer.files;
                        handleFilePreview(e.dataTransfer.files);
                    }
                });
                fileInput.addEventListener('change', () => handleFilePreview(fileInput.files));
            }

            function handleImagePreview(files) {
                if (!imagePreview) return;
                
                imagePreview.innerHTML = '';
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (!file.type.match('image.*')) continue;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'file-preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}" style="max-width: 60px; max-height: 60px; border-radius: 4px;">
                            <span>${file.name}</span>
                            <span class="remove-file" data-index="${i}" style="cursor:pointer;">&times;</span>
                        `;
                        imagePreview.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                }
            }

            function handleFilePreview(files) {
                if (!filePreview) return;
                
                filePreview.innerHTML = '';
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    
                    const previewItem = document.createElement('div');
                    previewItem.className = 'file-preview-item';
                    previewItem.innerHTML = `
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <span class="remove-file" data-index="${i}" style="cursor:pointer;">&times;</span>
                    `;
                    filePreview.appendChild(previewItem);
                }
            }

            // Note: The remove file functionality is not implemented here as it requires more complex
            // FileList manipulation which is tricky. The dashboard.php implementation is a good reference.
            // For a quick implementation, one could just clear the previews and the user would have to re-select all files.
            
            // AJAX Comment Submission
            document.getElementById('comments').addEventListener('submit', function(e) {
                if (e.target && e.target.classList.contains('comment-submit-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const formData = new FormData(form);
                    const submitButton = form.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;

                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                    fetch(`post-details.php?id=<?php echo $bug_id; ?>`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    }).then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.isReply) {
                                // It's a reply, find the correct container.
                                // The correct container is always the one with '-replies-container'
                                let replyContainer = document.getElementById(`comment-${data.parentId}-replies-container`);
                                replyContainer.insertAdjacentHTML('afterbegin', data.html);

                                // Find the newly added reply and update its time
                                const newReply = replyContainer.firstElementChild;
                                const timeElement = newReply.querySelector('.post-time');
                                if (timeElement) {
                                    timeElement.textContent = 'Just now';
                                }

                                // Ensure the replies list is visible
                                const repliesList = document.getElementById(`replies-list-${data.parentId}`);
                                if (repliesList) {
                                    repliesList.style.display = 'block';
                                }
                                
                                toggleReplyForm(data.parentId); // Hide the form after successful reply
                            } else {
                                // It's a main comment, prepend to the list
                                const commentsList = document.getElementById('commentsList');
                                const noCommentsMessage = document.getElementById('no-comments-message');
                                if (noCommentsMessage) {
                                    noCommentsMessage.remove();
                                }
                                commentsList.insertAdjacentHTML('afterbegin', data.html);

                                // Find the newly added comment and update its time
                                const newComment = commentsList.firstElementChild;
                                const timeElement = newComment.querySelector('.post-time');
                                if (timeElement) {
                                    timeElement.textContent = 'Just now';
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

                const isDelete = target.classList.contains('delete-comment-btn') || target.classList.contains('delete-reply-btn');
                const isEdit = target.classList.contains('edit-comment-btn') || target.classList.contains('edit-reply-btn');

                if (isDelete) {
                    handleDelete(target);
                } else if (isEdit) {
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

                    fetch(`post-details.php?id=<?php echo $bug_id; ?>`, {
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

                fetch(`post-details.php?id=<?php echo $bug_id; ?>`, {
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

            // Toggle solution form visibility
            const toggleBtn = document.getElementById('toggleSolutionFormBtn');
            const solutionFormContainer = document.getElementById('solutionFormContainer');
            if (toggleBtn && solutionFormContainer) {
                toggleBtn.addEventListener('click', function() {
                    const isVisible = solutionFormContainer.style.display === 'block';
                    if (isVisible) {
                        solutionFormContainer.style.display = 'none';
                        this.innerHTML = '<i class="far fa-lightbulb"></i> Propose Solution';
                    } else {
                        solutionFormContainer.style.display = 'block';
                        this.innerHTML = '<i class="fas fa-times"></i> Hide Form';
                        solutionFormContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        window.toggleReplies = function(commentId) {
            const repliesList = document.getElementById(`replies-list-${commentId}`);
            const toggleBtn = repliesList.previousElementSibling;
            const isVisible = repliesList.style.display === 'block';
            if (isVisible) {
                repliesList.style.display = 'none';
                toggleBtn.innerHTML = `<i class="fas fa-chevron-down"></i> View ${repliesList.querySelectorAll('.comment-reply').length} replies`;
            } else {
                repliesList.style.display = 'block';
                toggleBtn.innerHTML = `<i class="fas fa-chevron-up"></i> Hide replies`;
            }
        }
    </script>
</body>
</html>