<?php 
// dashboard.php
ini_set('session.lazy_write', 0); // Must be called before session_start()

// Start session at the very top, as it's used in POST handling below.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'config/database.php';

// Protect this page: redirect to login if user is not logged in.
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// Handle removing a saved bug via POST from the dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_saved_bug'])) {
    $bug_id_to_remove = (int)$_POST['bug_id'];
    
    try {
        $remove_sql = "DELETE FROM user_bug_saves WHERE user_id = ? AND bug_id = ?";
        $remove_stmt = $pdo->prepare($remove_sql);
        $remove_stmt->execute([$_SESSION['user_id'], $bug_id_to_remove]);
        
        // Redirect back to the saved bugs tab to see the change
        header("Location: dashboard.php?tab=saved-tab&removed=1");
        exit();
        
    } catch (PDOException $e) {
        error_log("Error removing saved bug: " . $e->getMessage());
        // Redirect with an error message
        header("Location: dashboard.php?tab=saved-tab&error=remove_failed");
        exit();
    }
}
// Handle removing a saved solution via POST from the dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_saved_solution'])) {
    $solution_id_to_remove = (int)$_POST['solution_id'];
    
    try {
        $remove_sql = "DELETE FROM solution_saves WHERE user_id = ? AND solution_id = ?";
        $remove_stmt = $pdo->prepare($remove_sql);
        $remove_stmt->execute([$_SESSION['user_id'], $solution_id_to_remove]);
        
        // Redirect back to the saved solutions tab to see the change
        header("Location: dashboard.php?tab=saved-solutions-tab&removed=1");
        exit();
        
    } catch (PDOException $e) {
        error_log("Error removing saved solution: " . $e->getMessage());
        // Redirect with an error message
        header("Location: dashboard.php?tab=saved-solutions-tab&error=remove_failed");
        exit();
    }
}

// Handle deleting a bug
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bug'])) {
    $bug_id_to_delete = (int)$_POST['bug_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // Ensure the user owns the bug
        $bug_owner_stmt = $pdo->prepare("SELECT user_id FROM bugs WHERE id = ?");
        $bug_owner_stmt->execute([$bug_id_to_delete]);
        $bug_owner_id = $bug_owner_stmt->fetchColumn();

        if ($bug_owner_id == $user_id) {
            // To maintain data integrity, we should delete related records first.
            // This assumes no ON DELETE CASCADE constraints are set in the DB.
            $pdo->prepare("DELETE FROM comments WHERE bug_id = ?")->execute([$bug_id_to_delete]);
            $pdo->prepare("DELETE FROM solutions WHERE bug_id = ?")->execute([$bug_id_to_delete]);
            $pdo->prepare("DELETE FROM user_bug_saves WHERE bug_id = ?")->execute([$bug_id_to_delete]);
            $pdo->prepare("DELETE FROM bug_images WHERE bug_id = ?")->execute([$bug_id_to_delete]);
            $pdo->prepare("DELETE FROM bug_files WHERE bug_id = ?")->execute([$bug_id_to_delete]);
            
            // Finally, delete the bug itself
            $delete_stmt = $pdo->prepare("DELETE FROM bugs WHERE id = ?");
            $delete_stmt->execute([$bug_id_to_delete]);

            $pdo->commit();
            header("Location: dashboard.php?tab=my-bugs&deleted=bug_success");
        } else {
            $pdo->rollBack();
            header("Location: dashboard.php?tab=my-bugs&error=not_owner");
        }
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting bug: " . $e->getMessage());
        header("Location: dashboard.php?tab=my-bugs&error=delete_failed");
        exit();
    }
}

// Handle deleting a solution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_solution'])) {
    $solution_id_to_delete = (int)$_POST['solution_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // The query ensures the user can only delete their own solution
        $delete_stmt = $pdo->prepare("DELETE FROM solutions WHERE id = ? AND user_id = ?");
        $delete_stmt->execute([$solution_id_to_delete, $user_id]);
        header("Location: dashboard.php?tab=solutions-tab&deleted=solution_success");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting solution: " . $e->getMessage());
        header("Location: dashboard.php?tab=solutions-tab&error=delete_failed");
        exit();
    }
}

// Handle bug update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bug'])) {
    $bug_id = (int)$_POST['bug_id'];
    $title = trim($_POST['bug_title']);
    $description = trim($_POST['bug_description']);
    $code_snippet = trim($_POST['bug_code'] ?? '');
    $tags = trim($_POST['bug_tags'] ?? '');
    $priority = trim($_POST['bug_priority'] ?? 'medium');
    
    try {
        // Update bug in database
        $sql = "UPDATE bugs SET title = ?, description = ?, code_snippet = ?, tags = ?, priority = ? WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $code_snippet, $tags, $priority, $bug_id, $_SESSION['user_id']]);
        
        // Clear edit session data
        if (isset($_SESSION['edit_bug_data'])) {
            unset($_SESSION['edit_bug_data']);
        }
        
        // Redirect to the bug details page
        header("Location: post-details.php?id=$bug_id&updated=1");
        exit();
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $bug_update_error = "Failed to update bug: " . $e->getMessage();
    }
}
// Handle solution approval/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_solution'])) {
    $solution_id = (int)$_POST['solution_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $approve_sql = "UPDATE solutions s JOIN bugs b ON s.bug_id = b.id SET s.is_approved = 1, s.updated_at = NOW() WHERE s.id = ? AND b.user_id = ?";
            $approve_stmt = $pdo->prepare($approve_sql);
            $approve_stmt->execute([$solution_id, $_SESSION['user_id']]);
            
            // Update bug status to solved
            $update_bug_sql = "UPDATE bugs 
                              SET status = 'solved' 
                              WHERE id = (SELECT bug_id FROM solutions WHERE id = ?) AND user_id = ?";
            $update_bug_stmt = $pdo->prepare($update_bug_sql);
            $update_bug_stmt->execute([$solution_id, $_SESSION['user_id']]);
            
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
                $bug_owner_name = $_SESSION['user_name'];
                $message = "Your solution for \"" . htmlspecialchars(substr($solution_info['title'], 0, 30)) . "...\" was approved!";
                $link = "solution-details.php?id=$solution_id";

                $notif_sql = "INSERT INTO notifications (user_id, sender_id, type, message, link) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($notif_sql)->execute([$solution_info['user_id'], $_SESSION['user_id'], 'solution_approved', $message, $link]);
            }
            // --- End Notification Logic ---

            $solution_message = "Solution approved successfully!";
        } elseif ($action === 'decline') {
            // Delete the solution
            $decline_sql = "DELETE s FROM solutions s 
                           JOIN bugs b ON s.bug_id = b.id 
                           WHERE s.id = ? AND b.user_id = ?";
            $decline_stmt = $pdo->prepare($decline_sql);
            $decline_stmt->execute([$solution_id, $_SESSION['user_id']]);
            
            $solution_message = "Solution declined and removed!";
        }
        
        // Refresh pending solutions
        $stmt = $pdo->prepare("
            SELECT s.*, b.title as bug_title, b.id as bug_id, u.name as user_name,
                   (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes
            FROM solutions s
            JOIN bugs b ON s.bug_id = b.id
            JOIN users u ON s.user_id = u.id
            WHERE b.user_id = ? AND s.is_approved = 0
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pending_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error handling solution action: " . $e->getMessage());
        $solution_error = "Failed to process solution: " . $e->getMessage();
    }
}

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_picture'])) {
    $user_id = $_SESSION['user_id'];

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                try {
                    $sql = "UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$target_path, $user_id]);

                    $_SESSION['profile_picture'] = $target_path;
                    
                    header("Location: dashboard.php?tab=my-profile&profile_updated=1");
                    exit();
                } catch (PDOException $e) {
                    header("Location: dashboard.php?tab=my-profile&error=" . urlencode($e->getMessage()));
                    exit();
                }
            } else {
                header("Location: dashboard.php?tab=my-profile&error=" . urlencode("Failed to upload file"));
                exit();
            }
        } else {
            header("Location: dashboard.php?tab=my-profile&error=" . urlencode("Invalid file type. Please upload JPG, PNG, or GIF images only."));
            exit();
        }
    }
    header("Location: dashboard.php?tab=my-profile");
    exit();
}

// Handle profile details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_details'])) {
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
    $skills = trim($_POST['skills'] ?? '');
    $avatar_color = $_POST['avatar_color'] ?? '#6366f1';

    // Email preferences
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $email_solutions = isset($_POST['email_solutions']) ? 1 : 0;
    $email_comments = isset($_POST['email_comments']) ? 1 : 0;
    $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;

    try {
        // Check if username is already taken by another user
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$username, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            header("Location: dashboard.php?tab=my-profile&error=" . urlencode("Username already taken"));
            exit();
        }

        $sql = "UPDATE users SET 
                name = ?, username = ?, email = ?, title = ?, bio = ?, location = ?, 
                company = ?, website = ?, github = ?, twitter = ?, linkedin = ?, 
                skills = ?, avatar_color = ?, 
                email_notifications = ?, email_solutions = ?, email_comments = ?, email_newsletter = ?,
                updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, $username, $email, $title, $bio, $location, $company, $website, 
            $github, $twitter, $linkedin, $skills, $avatar_color,
            $email_notifications, $email_solutions, $email_comments, $email_newsletter,
            $user_id
        ]);

        // Update session data
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
        $_SESSION['skills'] = $skills;
        $_SESSION['avatar_color'] = $avatar_color;
        $_SESSION['email_notifications'] = $email_notifications;
        $_SESSION['email_solutions'] = $email_solutions;
        $_SESSION['email_comments'] = $email_comments;
        $_SESSION['email_newsletter'] = $email_newsletter;

        header("Location: dashboard.php?tab=my-profile&profile_updated=1");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?tab=my-profile&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Handle bug report submission
$bug_submission_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bug_title']) && !isset($_POST['update_bug'])) {
    $title = trim($_POST['bug_title']);
    $description = trim($_POST['bug_description']);
    $code_snippet = trim($_POST['bug_code'] ?? '');
    $tags = trim($_POST['bug_tags'] ?? '');
    $priority = trim($_POST['bug_priority'] ?? 'medium');
    
    // Handle file uploads
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
                $file_name = uniqid() . '_' . basename($_FILES['bug_images']['name'][$key]);
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
                $file_name = uniqid() . '_' . basename($_FILES['bug_files']['name'][$key]);
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
    
    try {
        // Insert bug into database
        $sql = "INSERT INTO bugs (user_id, title, description, code_snippet, tags, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'open')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $title, $description, $code_snippet, $tags, $priority]);
        
        $bug_id = $pdo->lastInsertId();
        
        // Insert images
        if (!empty($image_paths)) {
            $image_sql = "INSERT INTO bug_images (bug_id, image_path) VALUES (?, ?)";
            $image_stmt = $pdo->prepare($image_sql);
            
            foreach ($image_paths as $image_path) {
                $image_stmt->execute([$bug_id, $image_path]);
            }
        }
        
        // Insert files
        if (!empty($file_paths)) {
            $file_sql = "INSERT INTO bug_files (bug_id, file_path, original_name) VALUES (?, ?, ?)";
            $file_stmt = $pdo->prepare($file_sql);
            
            foreach ($file_paths as $file) {
                $file_stmt->execute([$bug_id, $file['path'], $file['original_name']]);
            }
        }
        
        // Award reputation for reporting a bug
        $rep_stmt = $pdo->prepare("UPDATE users SET reputation = reputation + 5 WHERE id = ?");
        $rep_stmt->execute([$_SESSION['user_id']]);
        
        // Update session reputation
        $_SESSION['reputation'] = ($_SESSION['reputation'] ?? 0) + 5;
        
        // Clear edit session data if exists
        if (isset($_SESSION['edit_bug_data'])) {
            unset($_SESSION['edit_bug_data']);
        }
        
        // Redirect to the bug posts page
        header("Location: bug-post.php?new_bug=1&bug_id=" . $bug_id);
        exit();
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $bug_submission_error = "Failed to submit bug: " . $e->getMessage();
    }
}

// Get user profile data
$user_profile_data = [];
try {
    // Re-fetch user data to get the latest info after updates
    $profile_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $profile_stmt->execute([$_SESSION['user_id']]);
    $user_profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
}

// Update session with fresh data before including the header
if (is_array($user_profile_data)) {
    $_SESSION = array_merge($_SESSION, $user_profile_data);
}
$profile_picture_path = $user_profile_data['profile_picture'] ?? $_SESSION['profile_picture'] ?? null;

include(__DIR__ . '/Components/header.php');
require_once 'includes/utils.php';

// Fetch user stats with real data
$user_stats = [];
$recent_activity = [];
$user_bugs = [];
$user_solutions = [];
$saved_bugs = [];
$pending_solutions = [];
$approved_solutions = [];
$saved_solutions = [];

try {
    // Get user stats with real data - FIXED QUERIES
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM bugs WHERE user_id = ?) as bugs_reported,
            (SELECT COUNT(*) FROM solutions WHERE user_id = ?) as solutions_provided,
            (SELECT COUNT(*) FROM solutions WHERE user_id = ? AND is_approved = 1) as solutions_accepted,
            (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comments_made,
            (SELECT COUNT(*) FROM user_bug_saves WHERE user_id = ?) as bugs_saved,
            (SELECT COUNT(*) FROM solution_saves WHERE user_id = ?) as solutions_saved
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity (bugs, solutions, comments, saves) - FIXED QUERY
    $stmt = $pdo->prepare("
        (SELECT 'bug' as type, id, title, created_at, NULL as parent_title, NULL as content
         FROM bugs WHERE user_id = ?)
        UNION ALL
        (SELECT 'solution' as type, s.id, s.content as title, s.created_at, b.title as parent_title, s.content
         FROM solutions s 
         JOIN bugs b ON s.bug_id = b.id 
         WHERE s.user_id = ?)
        UNION ALL
        (SELECT 'comment' as type, c.id, c.comment_text as title, c.created_at, b.title as parent_title, c.comment_text as content
         FROM comments c
         JOIN bugs b ON c.bug_id = b.id
         WHERE c.user_id = ?)
        UNION ALL
        (SELECT 'saved_bug' as type, ubs.bug_id as id, b.title, ubs.saved_at as created_at, NULL as parent_title, NULL as content
         FROM user_bug_saves ubs
         JOIN bugs b ON ubs.bug_id = b.id
         WHERE ubs.user_id = ?)
        UNION ALL
        (SELECT 'saved_solution' as type, ss.solution_id as id, s.content as title, ss.saved_at as created_at, b.title as parent_title, s.content
         FROM solution_saves ss
         JOIN solutions s ON ss.solution_id = s.id
         JOIN bugs b ON s.bug_id = b.id
         WHERE ss.user_id = ?)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's ALL bugs - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM solutions WHERE bug_id = b.id) as solution_count,
               (SELECT COUNT(*) FROM comments WHERE bug_id = b.id) as comment_count,
               (SELECT COUNT(*) FROM user_bug_saves WHERE bug_id = b.id) as save_count
        FROM bugs b
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's recent solutions - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT s.*, b.title as bug_title, b.id as bug_id,
               (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes,
               s.is_approved as accepted
        FROM solutions s
        JOIN bugs b ON s.bug_id = b.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending solutions for user's bugs - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT s.*, b.title as bug_title, b.id as bug_id, u.name as user_name,
               (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes
        FROM solutions s
        JOIN bugs b ON s.bug_id = b.id
        JOIN users u ON s.user_id = u.id
        WHERE b.user_id = ? AND s.is_approved = 0
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get approved solutions for user's bugs - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT s.*, b.title as bug_title, b.id as bug_id, u.name as user_name,
               (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes
        FROM solutions s
        JOIN bugs b ON s.bug_id = b.id
        JOIN users u ON s.user_id = u.id
        WHERE b.user_id = ? AND s.is_approved = 1
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $approved_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get saved bugs - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM solutions WHERE bug_id = b.id) as solution_count,
               (SELECT COUNT(*) FROM comments WHERE bug_id = b.id) as comment_count,
               (SELECT COUNT(*) FROM user_bug_saves WHERE bug_id = b.id) as save_count
        FROM bugs b
        JOIN user_bug_saves ubs ON b.id = ubs.bug_id
        WHERE ubs.user_id = ?
        ORDER BY ubs.saved_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saved_bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get saved solutions - FIXED QUERY
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as user_name, u.avatar_color, b.title as bug_title, b.id as bug_id,
               (SELECT COUNT(*) FROM solution_votes WHERE solution_id = s.id AND vote_type = 'up') as upvotes,
               (SELECT COUNT(*) FROM solution_views WHERE solution_id = s.id) as views_count,
               ss.saved_at
        FROM solutions s
        JOIN solution_saves ss ON s.id = ss.solution_id
        JOIN users u ON s.user_id = u.id 
        JOIN bugs b ON s.bug_id = b.id
        WHERE ss.user_id = ? 
        ORDER BY ss.saved_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saved_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // Handle error appropriately
}

// Get popular tags for autocomplete
$renowned_tags = [
    'JavaScript', 'Python', 'PHP', 'Java', 'C#', 'C++', 'Ruby', 'Go', 'Swift', 'Kotlin', 'TypeScript', 
    'HTML', 'CSS', 'SQL', 'MySQL', 'PostgreSQL', 'MongoDB', 'React', 'Angular', 'Vue.js', 'Node.js', 
    'Django', 'Flask', 'Laravel', 'Spring', 'ASP.NET', 'Docker', 'Kubernetes', 'AWS', 'Azure', 
    'Google Cloud', 'Git', 'Linux', 'Windows', 'macOS', 'Android', 'iOS', 'Tailwind CSS'
];

$popular_tags = [];
try {
    $tags_stmt = $pdo->query("
        SELECT tags, COUNT(*) as count 
        FROM bugs 
        WHERE tags IS NOT NULL AND tags != '' 
        GROUP BY tags 
        ORDER BY count DESC 
        LIMIT 50
    ");
    $tag_data = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $db_tags = [];
    foreach ($tag_data as $row) {
        $tags = explode(',', $row['tags']);
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!empty($tag) && !in_array($tag, $db_tags)) {
                $db_tags[] = $tag;
            }
        }
    }
    // Merge renowned tags with tags from DB, ensuring no duplicates and renowned come first
    $popular_tags = array_values(array_unique(array_merge($renowned_tags, $db_tags)));
} catch (PDOException $e) {
    error_log("Error fetching popular tags: " . $e->getMessage());
}

// Check if we're in edit mode
$edit_mode = false;
$edit_bug_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_bug_id = (int)$_GET['edit'];
    try {
        $edit_stmt = $pdo->prepare("SELECT * FROM bugs WHERE id = ? AND user_id = ?");
        $edit_stmt->execute([$edit_bug_id, $_SESSION['user_id']]);
        $edit_bug_data = $edit_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_bug_data) {
            $edit_mode = true;
        }
    } catch (PDOException $e) {
        error_log("Error fetching bug for edit: " . $e->getMessage());
    }
} elseif (isset($_SESSION['edit_bug_data'])) {
    $edit_bug_data = $_SESSION['edit_bug_data'];
    $edit_mode = true;
}

// Define ranks and calculate user's current rank and progress
$ranks = [
    'Beginner' => 0,
    'Intermediate' => 100,
    'Advanced' => 500,
    'Expert' => 1000,
    'Master' => 2500,
    'Grand Master' => 5000
];

$reputation = $_SESSION['reputation'] ?? 0;
$user_rank = 'Beginner';
$next_rank = 'Intermediate';
$next_rank_rep = 100;
$current_rank_rep = $ranks['Beginner'];
$progress_percentage = 0;
$is_max_rank = false;

foreach ($ranks as $rank_name => $rep_needed) {
    if ($reputation >= $rep_needed) {
        $user_rank = $rank_name;
        $current_rank_rep = $rep_needed;
    } else {
        $next_rank = $rank_name;
        $next_rank_rep = $rep_needed;
        break; // Found the next rank, no need to continue
    }
}

// Check if user is at the highest rank
$rank_keys = array_keys($ranks);
if ($user_rank === end($rank_keys)) {
    $is_max_rank = true;
}

if ($is_max_rank) {
    $progress_percentage = 100;
} else {
    $rep_for_next_rank = $next_rank_rep - $current_rank_rep;
    $progress_rep = $reputation - $current_rank_rep;
    $progress_percentage = ($rep_for_next_rank > 0) ? ($progress_rep / $rep_for_next_rank) * 100 : 100;
}
// Get chart data for dashboard
$chart_data = [];
try {
    // Weekly activity data
    $activity_stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            'bug' as type
        FROM bugs 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        UNION ALL
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            'solution' as type
        FROM solutions 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $activity_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $activity_data = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Bug status data
    $status_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM bugs 
        WHERE user_id = ? 
        GROUP BY status
    ");
    $status_stmt->execute([$_SESSION['user_id']]);
    $status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data = [
        'activity' => $activity_data,
        'status' => $status_data
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Use a specific container for the dashboard to allow a wider layout */
        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Dashboard Layout */
        .dashboard {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            padding: 40px 0;
        }

        /* Sidebar */
        .sidebar {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar-section {
            margin-bottom: 30px;
        }

        .sidebar-section h3 {
            margin-bottom: 18px;
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-section h3 i {
            color: var(--accent-primary);
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 10px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
        }

        .sidebar-nav .dropdown-toggle-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--bg-card);
            color: var(--accent-primary);
        }

        .sidebar-nav a i {
            width: 20px;
            text-align: left;
        }

        .stats-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-box h4 {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-value {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Dashboard Main */
        .dashboard-main {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 10px;
        }

        .dashboard-subheader {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 25px;
        }

        .dashboard-subheader .desktop-report-bug-btn {
            align-self: flex-end;
        }

        .dashboard-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .grid-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        /* Specific grid for the top 4 stats cards */
        .stats-grid-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            margin-bottom: 25px;
            gap: 25px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        /* Remove hover effect from main content cards */
        .dashboard-main .card {
            transform: none !important;
            box-shadow: var(--card-shadow) !important;
            border-color: var(--border) !important;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 1.3rem;
            color: var(--text-primary);
        }

        .card-header i {
            color: var(--accent-primary);
            font-size: 1.5rem;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .sub-tab-nav {
            display: none; /* Hidden by default on larger screens */
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .sub-tab-nav .btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding-bottom: 10px;
            border-bottom: 2px solid transparent;
            border-radius: 0;
        }

        .sub-tab-nav .btn.active,
        .sub-tab-nav .btn:hover {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
        }

        @media (max-width: 599px) {
            .sub-tab-nav {
                display: flex; /* Visible on smaller screens */
            }
        }

        .mobile-tab-nav {
            display: none; /* Hidden by default */
        }

        @media (max-width: 599px) {
            .mobile-tab-nav.visible {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
                padding: 10px;
                background: var(--bg-card);
                border-radius: 12px;
                border: 1px solid var(--border);
                justify-content: space-around;
            }
            .mobile-tab-nav.visible .btn {
                flex: 1;
                justify-content: center;
            }
        }

        /* Form Styles */
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

        .input-with-icon input, .input-with-icon textarea, .input-with-icon select {
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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

        /* Rank Progress Bar */
        .rank-progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }

        .rank-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .rank-details {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Custom checkbox color */
        #my-profile input[type="checkbox"] {
            accent-color: var(--accent-primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background: #dc2626; /* A darker shade of the danger color */
            border-color: #dc2626;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Activity Section */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .activity-content h3 a {
            text-decoration: none;
            color: inherit;
        }

        .activity-content h3 a:hover,
        .solution-card-title a:hover {
            color: var(--accent-primary);
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .activity-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        /* Chart Containers */
        .chart-container {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h2 {
            font-size: 1.3rem;
            color: var(--text-primary);
        }

        .chart-actions {
            display: flex;
            gap: 10px;
        }

        .chart-actions button {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }

        .chart-actions button:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
        }

        /* Badges Section */
        .badges-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .badge {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            width: calc(50% - 10px);
        }

        .badge:hover {
            border-color: var(--accent-primary);
            transform: translateY(-3px);
        }

        .badge-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .badge-content h3 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .badge-content p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .dashboard-bottom-cards {
            margin-top: 25px;
        }

        /* Your Bugs Section */
        .bugs-list {
            list-style: none;
        }

        .bug-item {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .bug-item:hover {
            border-color: var(--accent-primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .saved-bug-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .saved-bug-main {
            flex-grow: 1;
        }


        .bug-item:last-child {
            border-bottom: none;
        }

        .bug-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0;
        }

        .bug-info h3 {
            font-size: 1rem;
            margin-bottom: 0;
            color: var(--text-primary);
        }

        .bug-info h3 a {
            color: var(--text-primary);
            text-decoration: none;
        }

        .bug-info h3 a:hover {
            color: var(--accent-primary);
        }

        .bug-meta {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            font-size: 0.85rem;
            flex-wrap: wrap; /* Allow meta items to wrap */
            margin-bottom: 8px;
        }

        .bug-tags {
            margin-top: 10px;
        }

         .bug-meta.reduced-gap {
            margin-top: -22px;
            margin-top: -25px;
        }

        .bug-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-open {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .status-solved {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
        }

        .bug-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        /* Profile Section */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            position: relative;
            overflow: hidden;
            background: <?php echo $_SESSION['avatar_color'] ?? '#6366f1'; ?>;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: var(--text-muted);
        }

        .profile-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .profile-stat {
            text-align: center;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 12px;
            flex: 1;
        }

        .profile-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: 5px;
        }

        .profile-stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        #saved-solutions-tab .solution-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px; /* row-gap column-gap */
        }

        /* Profile Picture Upload */
        .profile-picture-upload {
            margin-bottom: 30px;
        }

        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            position: relative;
            overflow: hidden;
            background: <?php echo $_SESSION['avatar_color'] ?? '#6366f1'; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 3rem;
        }

        .profile-picture-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-upload label {
            display: block;
            text-align: center;
            margin-bottom: 10px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .profile-picture-input {
            display: none;
        }

        .profile-picture-btn {
            display: block;
            margin: 0 auto;
            padding: 10px 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .profile-picture-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
        }

        /* Settings Section */
        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }

        /* Error/Success Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Tag Styles */
        .tag {
            display: inline-block;
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        /* Code Snippet Styles */
        .code-snippet {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            overflow-x: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            border: 1px solid var(--border);
        }

        /* File Upload Styles */
        .file-upload-container {
            margin-bottom: 20px;
        }

        .file-upload-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload-box:hover {
            border-color: var(--accent-primary);
        }

        .file-upload-box i {
            font-size: 2rem;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .file-upload-box p {
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .file-upload-box input[type="file"] {
            display: none;
        }

        .file-preview {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .file-preview-item {
            background: var(--bg-secondary);
            border-radius: 6px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .file-preview-item img {
            max-width: 60px;
            max-height: 60px;
            border-radius: 4px;
        }

        .remove-file {
            color: var(--danger);
            cursor: pointer;
        }

        /* Autocomplete Styles */
        .autocomplete-container {
            position: relative;
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid var(--border);
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }

        .autocomplete-item:hover {
            background: var(--bg-secondary);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        /* Solution Tabs */
        .solution-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .solution-tab {
            padding: 10px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .solution-tab.active {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        .solution-tab:hover:not(.active) {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
        }

        .solution-tab-content {
            display: none;
        }

        .solution-tab-content.active {
            display: block;
        }

        /* Solution Cards */
        .solution-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .solution-card:hover {
            border-color: var(--accent-primary);
        }

        .solution-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .solution-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .solution-card-title a {
            text-decoration: none;
            color: inherit;
        }

        .solution-card-meta {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            font-size: 0.85rem;
            flex-wrap: wrap;
        }

        .solution-card-actions {
            display: flex;
            gap: 10px;
        }

        .solution-card-content {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .solution-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        #saved-solutions-tab .solution-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .solution-card-stats {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Sidebar Dropdown */
        .sidebar-nav .has-dropdown .dropdown-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-nav .has-dropdown .dropdown-icon {
            transition: transform 0.3s ease;
        }

        .sidebar-nav .has-dropdown.open > .dropdown-toggle .dropdown-icon {
            transform: rotate(180deg);
        }

        .sidebar-nav .dropdown-content {
            list-style: none;
            padding-left: 20px; /* Indent dropdown items */
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .mobile-dashboard-header {
            display: none; /* Hidden by default */
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            /* z-index: 1002; */ /* Removed to allow the icon to be part of the header's stacking context */
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 998;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 998;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        .sidebar-nav .has-dropdown.open > .dropdown-content {
            max-height: 500px; /* Adjust as needed */
            padding-top: 10px;
        }

        .sidebar-nav .dropdown-content a {
            padding: 10px 15px;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1100px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
                margin-bottom: 30px;
            }
            
            .badge {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .grid-cards {
                grid-template-columns: 1fr;
            }

            .chart-container {
                padding: 15px;
                height: 250px; /* Adjust height for smaller screens */
            }

            .chart-container .card-header h2 {
                font-size: 1.1rem;
            }

            .chart-container .card {
                padding: 20px;
            }
            
            .chart-actions {
                flex-wrap: wrap;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-stats {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .profile-stat-value {
                font-size: 1.3rem;
            }

            .profile-stat-label {
                font-size: 0.8rem;
            }

            .stats-value {
                font-size: 1.5rem;
            }

            .card-header h2 {
                font-size: 1.1rem;
            }

            .activity-content p, .activity-content h3 {
                font-size: 0.9rem;
                line-height: 1.4;
            }

            .activity-meta {
                font-size: 0.8rem;
                gap: 10px;
            }

            .bug-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .profile-picture-preview {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }

            .solution-card-header {
                flex-direction: column;
                gap: 10px;
            }

            .solution-card-actions {
                width: 100%;
                justify-content: space-between;
            }

            .solution-card-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .tag {
                font-size: 0.75rem;
                padding: 3px 8px;
            }
         
            .bug-actions .btn-sm {
                padding: 5px 10px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: fixed;
                left: -300px;
                top: 0;
                height: 100%;
                z-index: 1001;
                transition: left 0.3s ease;
                background: var(--bg-primary);
                width: 280px;
                box-shadow: 0 0 20px rgba(0,0,0,0.5);
                padding-top: 80px;
                overflow-y: auto; /* Allow vertical scrolling but hide the scrollbar */
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;  /* Firefox */
            }

            /* Hide scrollbar for Chrome, Safari and Opera */
            .sidebar::-webkit-scrollbar {
                display: none;
            }
            .sidebar.open {
                left: 0;
            }
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }

                .desktop-report-bug-btn {
                display: none;
            }
            mobile-dashboard-header .btn-primary {
                display: none;
            }
            .mobile-dashboard-header {
                display: flex;
            }

            
        }

        @media (max-width: 480px) {
            .profile-picture-preview {
                width: 100px;
                height: 100px;
                font-size: 2rem;
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .activity-icon {
                align-self: flex-start;
            }

            .solution-tabs {
                flex-direction: column;
            }
        }

       
    </style>
</head>
<body>
    <div class="sidebar-overlay"></div>
    <!-- Dashboard -->
    <main class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <aside class="sidebar" id="dashboard-sidebar">
                <div class="sidebar-section">

                </div>

                <div class="sidebar-section">
                    <h3><i class="fas fa-compass"></i> Navigation</h3>
                    <ul class="sidebar-nav">
                        <li><a href="#" class="tab-link active" data-tab="dashboard-tab"><i class="fas fa-home"></i> Dashboard</a></li>
                        
                        <li class="has-dropdown">
                            <a href="#" class="dropdown-toggle">
                                <span class="dropdown-toggle-content"><i class="fas fa-user"></i> Profile</span>
                                <i class="fas fa-chevron-down dropdown-icon"></i>
                            </a>
                            <ul class="dropdown-content">
                                <li><a href="#" class="tab-link" data-tab="my-profile">My Profile</a></li>
                                <li><a href="#" class="tab-link" data-tab="settings-tab">Settings</a></li>
                            </ul>
                        </li>

                        <li class="has-dropdown">
                            <a href="#" class="dropdown-toggle">
                                <span class="dropdown-toggle-content"><i class="fas fa-bug"></i> Bugs</span>
                                <i class="fas fa-chevron-down dropdown-icon"></i>
                            </a>
                            <ul class="dropdown-content">
                                <li><a href="#" class="tab-link" data-tab="my-bugs">My Bugs</a></li>
                                <li><a href="#" class="tab-link" data-tab="report-tab">Report Bug</a></li>
                                <li><a href="#" class="tab-link" data-tab="saved-tab">Saved Bugs</a></li>
                            </ul>
                        </li>

                        <li class="has-dropdown">
                            <a href="#" class="dropdown-toggle">
                                <span class="dropdown-toggle-content"><i class="fas fa-code"></i> Solutions</span>
                                <i class="fas fa-chevron-down dropdown-icon"></i>
                            </a>
                            <ul class="dropdown-content">
                                <li><a href="#" class="tab-link" data-tab="solutions-tab">My Solutions</a></li>
                                <li><a href="#" class="tab-link" data-tab="pending-solutions-tab">Pending Solutions</a></li>
                                <li><a href="#" class="tab-link" data-tab="approved-solutions-tab">Approved Solutions</a></li>
                                <li><a href="#" class="tab-link" data-tab="saved-solutions-tab">Saved Solutions</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <h3><i class="fas fa-fire"></i> Trending Tags</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php 
                        $trending_tags = array_slice($popular_tags, 0, 6);
                        foreach ($trending_tags as $tag): 
                        ?>
                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="dashboard-main">
                <div class="mobile-dashboard-header">
                    <a href="#" class="btn btn-primary tab-link" data-tab="report-tab"><i class="fas fa-plus"></i> Report Bug</a>
                    <button id="sidebar-toggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>
                </div>
                <!-- Dashboard Tab -->
                <div id="dashboard-tab" class="tab-content active">
                    <div class="dashboard-header">
                        <h1>Developer Dashboard</h1>
                    </div>
                    <div class="dashboard-subheader">
                            <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>! Here's what's happening in your developer community.</p>
                        <a href="#" class="btn btn-primary tab-link desktop-report-bug-btn" data-tab="report-tab"><i class="fas fa-plus"></i> Report Bug</a>
                    </div>
            <div class="mobile-tab-nav">
                <a href="#" class="btn btn-secondary tab-link" data-tab="my-bugs">My Bugs</a>
                <a href="#" class="btn btn-secondary tab-link" data-tab="report-tab">Report Bug</a>
                <a href="#" class="btn btn-secondary tab-link" data-tab="saved-tab">Saved Bugs</a>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-grid-cards">
                        <div class="card">
                            <div class="card-header">
                                <h2>Bugs Reported</h2>
                                <i class="fas fa-bug"></i>
                            </div>
                            <div class="stats-value"><?php echo $user_stats['bugs_reported'] ?? '0'; ?></div>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">Total reported bugs</p>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2>Solutions Provided</h2>
                                <i class="fas fa-code"></i>
                            </div>
                            <div class="stats-value"><?php echo $user_stats['solutions_provided'] ?? '0'; ?></div>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">Total solutions provided</p>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2>Reputation Points</h2>
                                <i class="fas fa-medal"></i>
                            </div>
                            <div class="stats-value"><?php echo $_SESSION['reputation'] ?? '0'; ?></div>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">Your community reputation</p>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2>Rank</h2>
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stats-value" style="font-size: 1.5rem; margin-bottom: 5px;"><?php echo $user_rank; ?></div>
                            <div class="rank-progress-bar">
                                <div class="rank-progress-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                            <?php if (!$is_max_rank): ?>
                                <div class="rank-details">Next: <?php echo $next_rank; ?> at <?php echo number_format($next_rank_rep); ?> rep</div>
                            <?php else: ?>
                                <div class="rank-details">You have reached the highest rank!</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="grid-cards">
                        <div class="card">
                            <div class="card-header">
                                <h2>Weekly Activity</h2>
                            </div>
                            <canvas id="activityChart" height="250"></canvas>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h2>Bug Status</h2>
                            </div>
                            <canvas id="bugStatusChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Recent Activity</h2>
                            <i class="fas fa-history"></i>
                        </div>
                        <?php if (!empty($recent_activity)): ?>
                        <ul class="activity-list">
                            <?php foreach ($recent_activity as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php if ($activity['type'] === 'bug'): ?>
                                        <i class="fas fa-bug"></i>
                                    <?php elseif ($activity['type'] === 'solution'): ?>
                                        <i class="fas fa-code"></i>
                                    <?php elseif ($activity['type'] === 'comment'): ?>
                                        <i class="fas fa-comment"></i>
                                    <?php elseif ($activity['type'] === 'saved_bug'): ?>
                                        <i class="fas fa-bookmark"></i>
                                    <?php elseif ($activity['type'] === 'saved_solution'): ?>
                                        <i class="fas fa-bookmark"></i>
                                    <?php else: ?>
                                        <i class="fas fa-history"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-content">
                                    <p>
                                        <?php if ($activity['type'] === 'bug'): ?>
                                            You reported a new bug: <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                        <?php elseif ($activity['type'] === 'solution'): ?>
                                            You provided a solution for: <strong><?php echo htmlspecialchars($activity['parent_title']); ?></strong>
                                        <?php elseif ($activity['type'] === 'comment'): ?>
                                            You commented on: <strong><?php echo htmlspecialchars($activity['parent_title']); ?></strong>
                                        <?php elseif ($activity['type'] === 'saved_bug'): ?>
                                            You saved a bug: <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                        <?php elseif ($activity['type'] === 'saved_solution'): ?>
                                            You saved a solution for: <strong><?php echo htmlspecialchars($activity['parent_title']); ?></strong>
                                        <?php endif; ?>
                                    </p>
                                    <div class="activity-meta">
                                        <span><?php echo timeAgo($activity['created_at']); ?></span>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>No recent activity yet</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Your Bugs & Solutions Row -->
                    <div class="grid-cards dashboard-bottom-cards">
                        <!-- Your Bugs -->
                        <div class="card">
                            <div class="card-header">
                                <h2>Your Recent Bugs</h2>
                                <i class="fas fa-list"></i>
                            </div>
                        <?php if (!empty(array_slice($user_bugs, 0, 5))): ?>
                            <ul class="bugs-list">
                                <?php foreach (array_slice($user_bugs, 0, 5) as $bug): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-bug"></i>
                                    </div>
                                    <div class="activity-content" style="width: 100%;">
                                        <h3 class="line-clamp-2"><a href="post-details.php?id=<?php echo $bug['id']; ?>"><?php echo htmlspecialchars($bug['title']); ?></a></h3>
                                        <div class="activity-meta">
                                            <span><?php echo $bug['solution_count']; ?> solutions</span>
                                            <span><?php echo $bug['comment_count']; ?> comments</span>
                                            <span><?php echo timeAgo($bug['created_at']); ?></span>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="bug-status status-<?php echo strtolower(str_replace(' ', '-', $bug['status'])); ?>"><?php echo htmlspecialchars($bug['status']); ?></span>
                                            <a href="?tab=report-tab&edit=<?php echo $bug['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                                <i class="fas fa-bug" style="font-size: 2rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                                <p>No bugs reported yet</p>
                                <a href="#" class="btn btn-primary tab-link" data-tab="report-tab" style="margin-top: 15px;">
                                    <i class="fas fa-plus"></i> Report Your First Bug
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Your Solutions -->
                        <div class="card">
                            <div class="card-header">
                                <h2>Your Recent Solutions</h2>
                                <i class="fas fa-code"></i>
                            </div>
                            <?php if (!empty($user_solutions)): ?>
                            <ul class="activity-list">
                                <?php foreach ($user_solutions as $solution): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-code"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h3 class="line-clamp-2"><a href="post-details.php?id=<?php echo $solution['bug_id']; ?>"><?php echo htmlspecialchars($solution['bug_title']); ?></a></h3>
                                        <p class="line-clamp-2"><?php echo htmlspecialchars($solution['content']); ?></p>
                                        <div class="activity-meta">
                                            <span><?php echo timeAgo($solution['created_at']); ?></span>
                                        <span><?php echo $solution['upvotes']; ?> votes</span>
                                            <span><?php echo $solution['accepted'] ? 'Accepted' : 'Pending'; ?></span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                                <i class="fas fa-code" style="font-size: 2rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                                <p>No solutions provided yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div id="my-profile" class="tab-content">
                    <div class="dashboard-header">
                        <h1>My Profile</h1>
                    </div>

                    <div class="card">
                        <form id="profile-picture-form" method="POST" action="dashboard.php?tab=my-profile" enctype="multipart/form-data" class="loader-form">
                        <div class="profile-header" style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid var(--border);">
                            <div class="profile-avatar" id="profileAvatar">
                                <?php if ($profile_picture_path): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info" style="flex-grow: 1;">
                                <h2><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                                <p><?php echo htmlspecialchars($_SESSION['user_title'] ?? 'Developer'); ?></p>
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">
                                    Member since <?php echo date('F Y', strtotime($_SESSION['created_at'] ?? 'now')); ?>
                                </p>
                            </div>
                            <div class="profile-actions">
                                <input type="hidden" name="update_profile_picture" value="1">
                                <input type="file" id="profilePictureInput" name="profile_picture" class="profile-picture-input" accept="image/*">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('profilePictureInput').click()"><i class="fas fa-upload"></i> Upload Picture</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Picture</button>
                            </div>
                        </div>
                        </form>
                        
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo $user_stats['bugs_reported'] ?? '0'; ?></div>
                                <div class="profile-stat-label">Bugs Reported</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo $user_stats['solutions_provided'] ?? '0'; ?></div>
                                <div class="profile-stat-label">Solutions Provided</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo $_SESSION['reputation'] ?? '0'; ?></div>
                                <div class="profile-stat-label">Reputation Points</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo $user_rank; ?></div>
                                <div class="profile-stat-label">Global Rank</div>
                            </div>
                        </div>

                        <form id="profile-details-form" method="POST" action="dashboard.php?tab=my-profile">
                            <input type="hidden" name="update_profile_details" value="1">
                            <div class="form-group">
                                <label for="profile-name">Full Name *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="profile-name" name="name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-username">Username *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-at"></i>
                                    <input type="text" id="profile-username" name="username" class="form-control" 
                                           value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-email">Email Address *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="profile-email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-title">Professional Title</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="profile-title" name="title" class="form-control" 
                                           value="<?php echo htmlspecialchars($_SESSION['user_title'] ?? ''); ?>" 
                                           placeholder="e.g., Full Stack Developer">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-bio">Bio</label>
                                <textarea id="profile-bio" name="bio" class="form-control" 
                                          placeholder="Tell us about yourself, your skills, and experience..." 
                                          rows="4"><?php echo htmlspecialchars($user_profile_data['bio'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="profile-location">Location</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" id="profile-location" name="location" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['location'] ?? ''); ?>" 
                                           placeholder="e.g., San Francisco, CA">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-company">Company/Organization</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="profile-company" name="company" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['company'] ?? ''); ?>" 
                                           placeholder="Where do you work?">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-website">Website/Blog</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-globe"></i>
                                    <input type="url" id="profile-website" name="website" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['website'] ?? ''); ?>" 
                                           placeholder="https://yourwebsite.com">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-github">GitHub Username</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-github"></i>
                                    <input type="text" id="profile-github" name="github" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['github'] ?? ''); ?>" 
                                           placeholder="your-github-username">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-twitter">Twitter Handle</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-twitter"></i>
                                    <input type="text" id="profile-twitter" name="twitter" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['twitter'] ?? ''); ?>" 
                                           placeholder="@yourusername">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-linkedin">LinkedIn URL</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-linkedin"></i>
                                    <input type="url" id="profile-linkedin" name="linkedin" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['linkedin'] ?? ''); ?>" 
                                           placeholder="https://linkedin.com/in/yourprofile">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Avatar Color</label>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php
                                    $colors = [
                                        '#6366f1' => 'Primary Blue',
                                        '#8b5cf6' => 'Purple',
                                        '#06b6d4' => 'Cyan',
                                        '#10b981' => 'Emerald',
                                        '#f59e0b' => 'Amber',
                                        '#ef4444' => 'Red',
                                        '#3b82f6' => 'Blue',
                                        '#ec4899' => 'Pink'
                                    ];
                                    
                                    $current_color = $_SESSION['avatar_color'] ?? '#6366f1';
                                    
                                    foreach ($colors as $color => $name) {
                                        $isSelected = $current_color === $color;
                                        echo '
                                        <label style="display: flex; flex-direction: column; align-items: center; cursor: pointer;">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: ' . $color . ';
                                                 border: ' . ($isSelected ? '3px solid white' : '2px solid var(--border)') . ';
                                                 box-shadow: ' . ($isSelected ? '0 0 0 2px ' . $color : 'none') . '; margin-bottom: 5px;">
                                            </div>
                                            <input type="radio" name="avatar_color" value="' . $color . '" ' . 
                                                 ($isSelected ? 'checked' : '') . ' style="display: none;">
                                            <span style="font-size: 0.8rem; color: var(--text-muted);">' . substr($name, 0, 1) . '</span>
                                        </label>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email Preferences</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="email_notifications" <?php echo ($user_profile_data['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <span>Receive email notifications</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="email_solutions" <?php echo ($user_profile_data['email_solutions'] ?? 1) ? 'checked' : ''; ?>>
                                        <span>Notify me about solutions to my bugs</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="email_comments" <?php echo ($user_profile_data['email_comments'] ?? 1) ? 'checked' : ''; ?>>
                                        <span>Notify me about comments on my posts</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="email_newsletter" <?php echo ($user_profile_data['email_newsletter'] ?? 1) ? 'checked' : ''; ?>>
                                        <span>Receive community newsletter</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="profile-skills">Skills & Technologies</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-code"></i>
                                    <input type="text" id="profile-skills" name="skills" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_profile_data['skills'] ?? ''); ?>" 
                                           placeholder="JavaScript, React, Node.js, Python (comma separated)">
                                </div>
                                <small style="color: var(--text-muted);">Separate skills with commas</small>
                            </div>

                            <button type="submit" class="form-submit" style="margin-top: 20px;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>

                            <?php if (isset($_GET['profile_updated'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Profile updated successfully!
                            </div>
                            <?php endif; ?>

                            <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i> Error: <?php echo htmlspecialchars($_GET['error']); ?>
                            </div>
                            <?php endif; ?>
                        </form>

                        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                            <a href="?logout=true" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- My Bugs Tab -->
                <div id="my-bugs" class="tab-content">
                    <div class="dashboard-header">
                        <h1>My Bugs</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link active" data-tab="my-bugs">My Bugs</a>
                        <a href="#" class="btn tab-link" data-tab="saved-tab">Saved Bugs</a>
                        <a href="#" class="btn tab-link" data-tab="report-tab">Report a Bug</a>
                    </div>


                    <div class="card">
                        <div class="card-header">
                            <h2>All Reported Bugs</h2>
                            <a href="#" class="btn btn-primary tab-link" data-tab="report-tab">Report New Bug</a>
                        </div>

                        <?php if (!empty($user_bugs)): ?>
                        <ul class="bugs-list">
                            <?php foreach ($user_bugs as $bug): ?>
                            <li class="bug-item">
                                <div class="bug-info" style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="bug-item-header" style="margin-bottom: 0;">
                                        <h3><a href="post-details.php?id=<?php echo $bug['id']; ?>"><?php echo htmlspecialchars($bug['title']); ?></a></h3>
                                        <div class="bug-actions" style="flex-direction: row; align-items: center;">
                                    <span class="bug-status status-<?php echo strtolower(str_replace(' ', '-', $bug['status'])); ?>">
                                        <?php echo htmlspecialchars($bug['status']); ?>
                                    </span>
                                    </div>
                                    </div>
                                <div class="bug-meta">
                                    <span><?php echo $bug['solution_count']; ?> solutions</span>
                                    <span><?php echo $bug['comment_count']; ?> comments</span>
                                    <span><?php echo timeAgo($bug['created_at']); ?></span>
                                </div>
                                <p class="bug-description-snippet">
                                    <?php echo htmlspecialchars($bug['description']); ?>
                                </p>
                                <div class="bug-tags">
                                    <?php if ($bug['tags']): ?>
                                        <?php 
                                        $tags = explode(',', $bug['tags']);
                                        foreach (array_slice($tags, 0, 3) as $tag): 
                                            if (!empty(trim($tag))): ?>
                                                <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                            <?php endif;
                                        endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="bug-actions" style="margin-top: 15px; justify-content: flex-end;">
                                    <div style="display: flex; flex-direction: row; gap: 8px;">
                                        <a href="?tab=report-tab&edit=<?php echo $bug['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Bug">
                                            <i class="fas fa-edit"></i><span class="btn-text"> Edit</span>
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this bug and all its data? This cannot be undone.');">
                                            <input type="hidden" name="delete_bug" value="1">
                                            <input type="hidden" name="bug_id" value="<?php echo $bug['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete Bug"><i class="fas fa-trash"></i><span class="btn-text"> Delete</span></button>
                                        </form>
                                    </div>
                                </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-bug" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p style="text-align: center;">You haven't reported any bugs yet</p>
                            <a href="#" class="btn btn-primary tab-link" data-tab="report-tab" style="margin-top: 20px;">
                                <i class="fas fa-plus"></i> Report Your First Bug
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Solutions Tab -->
                <div id="solutions-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>My Solutions</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link active" data-tab="solutions-tab">My Solutions</a>
                        <a href="#" class="btn tab-link" data-tab="pending-solutions-tab">Pending</a>
                        <a href="#" class="btn tab-link" data-tab="approved-solutions-tab">Approved</a>
                        <a href="#" class="btn tab-link" data-tab="saved-solutions-tab">Saved</a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>All Provided Solutions</h2>
                        </div>

                        <?php if (!empty($user_solutions)): ?>
                        <div class="solutions-list">
                            <?php foreach ($user_solutions as $solution): ?>
                            <div class="solution-card">
                                <div class="solution-card-header">
                                    <div>
                                        <div class="solution-card-title">
                                            <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>">Solution for: <?php echo htmlspecialchars($solution['bug_title']); ?></a>
                                        </div>
                                        <div class="solution-card-meta">
                                            <span><i class="far fa-clock"></i> <?php echo timeAgo($solution['created_at']); ?></span>
                                            <span><i class="fas fa-thumbs-up"></i> <?php echo $solution['upvotes']; ?> votes</span>
                                            <span><i class="fas fa-check-circle"></i> <?php echo $solution['accepted'] ? 'Accepted' : 'Pending'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="solution-card-content">
                                    <?php echo htmlspecialchars(substr($solution['content'], 0, 150)); ?>...
                                </div>
                                <div class="solution-card-footer" style="flex-direction: row; gap: 10px; align-items: center;">
                                    <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View Details</a>
                                    <form class="solution-delete-form" method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this solution?');" style="margin: 0;">
                                        <input type="hidden" name="delete_solution" value="1">
                                        <input type="hidden" name="solution_id" value="<?php echo $solution['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="margin-left: auto;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-code" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>You haven't provided any solutions yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Solutions Tab -->
                <div id="pending-solutions-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>Pending Solutions</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link" data-tab="solutions-tab">My Solutions</a>
                        <a href="#" class="btn tab-link active" data-tab="pending-solutions-tab">Pending</a>
                        <a href="#" class="btn tab-link" data-tab="approved-solutions-tab">Approved</a>
                        <a href="#" class="btn tab-link" data-tab="saved-solutions-tab">Saved</a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Solutions Awaiting Your Approval</h2>
                        </div>

                        <?php if (!empty($pending_solutions)): ?>
                            <?php foreach ($pending_solutions as $solution): ?>
                            <div class="solution-card">
                                <div class="solution-card-header">
                                    <div>
                                        <div class="solution-card-title">
                                            <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>"><?php echo htmlspecialchars($solution['bug_title']); ?></a>
                                        </div>
                                        <div class="solution-card-meta">
                                            <span>By: <?php echo htmlspecialchars($solution['user_name']); ?></span>
                                            <span><?php echo $solution['upvotes']; ?> votes</span>
                                            <span><?php echo timeAgo($solution['created_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="solution-card-content">
                                    <?php 
                                    $solution_text = $solution['content'] ?? $solution['description'] ?? '';
                                    echo htmlspecialchars(substr($solution_text, 0, 200)); 
                                    ?>...
                                </div>
                                <div class="solution-card-footer">
                                    <div class="solution-card-stats">
                                        <span>Status: <strong>Pending Approval</strong></span>
                                    </div>
                                    <div class="solution-card-actions">
                                        <form method="POST" action="?tab=pending-solutions-tab" style="display: inline;">
                                            <input type="hidden" name="solution_id" value="<?php echo $solution['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" name="action_solution" class="btn btn-primary btn-sm" onclick="return confirm('Are you sure you want to approve this solution?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="?tab=pending-solutions-tab" style="display: inline;">
                                            <input type="hidden" name="solution_id" value="<?php echo $solution['id']; ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" name="action_solution" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to decline this solution? This action cannot be undone.')">
                                                <i class="fas fa-times"></i> Decline
                                            </button>
                                        </form>
                                        <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>#solutions" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>No pending solutions</p>
                            <p style="font-size: 0.9rem; margin-top: 10px;">Solutions submitted to your bugs will appear here for approval</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Approved Solutions Tab -->
                <div id="approved-solutions-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>Approved Solutions</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link" data-tab="solutions-tab">My Solutions</a>
                        <a href="#" class="btn tab-link" data-tab="pending-solutions-tab">Pending</a>
                        <a href="#" class="btn tab-link active" data-tab="approved-solutions-tab">Approved</a>
                        <a href="#" class="btn tab-link" data-tab="saved-solutions-tab">Saved</a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Your Approved Solutions</h2>
                        </div>

                        <?php if (!empty($approved_solutions)): ?>
                            <?php foreach ($approved_solutions as $solution): ?>
                            <div class="solution-card approved">
                                <div class="solution-card-header">
                                    <div>
                                        <div class="solution-card-title">
                                            <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>"><?php echo htmlspecialchars($solution['bug_title']); ?></a>
                                        </div>
                                        <div class="solution-card-meta">
                                            <span>By: <?php echo htmlspecialchars($solution['user_name']); ?></span>
                                            <span><?php echo $solution['upvotes']; ?> votes</span>
                                            <span><?php echo timeAgo($solution['created_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="solution-card-content">
                                    <?php echo htmlspecialchars(substr($solution['content'], 0, 200)); ?>...
                                </div>
                                <div class="solution-card-footer">
                                    <div class="solution-card-stats">
                                        <span>Status: <strong style="color: var(--success);">Approved</strong></span>
                                    </div>
                                    <div class="solution-card-actions">
                                        <a href="post-details.php?id=<?php echo $solution['bug_id']; ?>#solutions" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>No approved solutions yet</p>
                            <p style="font-size: 0.9rem; margin-top: 10px;">Approved solutions will appear here</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Saved Bugs Tab -->
                <div id="saved-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>Saved Bugs</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link" data-tab="my-bugs">My Bugs</a>
                        <a href="#" class="btn tab-link active" data-tab="saved-tab">Saved Bugs</a>
                        <a href="#" class="btn tab-link" data-tab="report-tab">Report a Bug</a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Your Bookmarked Bugs</h2>
                        </div>

                        <?php if (!empty($saved_bugs)): ?>
                        <ul class="bugs-list">
                            <?php foreach ($saved_bugs as $bug): ?>
                            <li class="bug-item">
                                <div class="saved-bug-main">
                                    <div class="bug-info">
                                        <h3><a href="post-details.php?id=<?php echo $bug['id']; ?>"><?php echo htmlspecialchars($bug['title']); ?></a></h3>
                                        <div class="bug-meta">
                                            <span><i class="fas fa-lightbulb"></i> <?php echo $bug['solution_count']; ?> solutions</span>
                                            <span><i class="fas fa-comment"></i> <?php echo $bug['comment_count']; ?> comments</span>
                                            <span><i class="fas fa-clock"></i> <?php echo timeAgo($bug['created_at']); ?></span>
                                        </div>
                                        <p class="bug-description-snippet">
                                            <?php echo htmlspecialchars($bug['description']); ?>
                                        </p>
                                    </div>
                                    <div class="bug-tags" style="margin-top: 10px; margin-bottom: 15px;">
                                        <?php if (!empty($bug['tags'])):
                                            $tags = explode(',', $bug['tags']);
                                            foreach (array_slice($tags, 0, 3) as $tag):
                                                if (!empty(trim($tag))): ?>
                                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                <?php endif; endforeach;
                                        endif; ?>
                                    </div>
                                    <form method="POST" action="" style="display: flex; justify-content: flex-end;">
                                        <input type="hidden" name="remove_saved_bug" value="1">
                                        <input type="hidden" name="bug_id" value="<?php echo $bug['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Remove from saved" onclick="return confirm('Remove this bug from your saved list?')">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-bookmark" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>You haven't saved any bugs yet</p>
                            <p style="font-size: 0.9rem; margin-top: 10px;">Click the bookmark icon on any bug to save it here</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Saved Solutions Tab -->
                <div id="saved-solutions-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>Saved Solutions</h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link" data-tab="solutions-tab">My Solutions</a>
                        <a href="#" class="btn tab-link" data-tab="pending-solutions-tab">Pending</a>
                        <a href="#" class="btn tab-link" data-tab="approved-solutions-tab">Approved</a>
                        <a href="#" class="btn tab-link active" data-tab="saved-solutions-tab">Saved</a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Your Bookmarked Solutions</h2>
                        </div>

                        <?php if (!empty($saved_solutions)): ?>
                        <div class="solutions-list">
                            <?php foreach ($saved_solutions as $solution): ?>
                            <div class="solution-card">
                                <div class="solution-card-header">
                                    <div>
                                        <div class="solution-card-title">
                                            <a href="solution-details.php?id=<?php echo $solution['id']; ?>">Solution for: <?php echo htmlspecialchars($solution['bug_title']); ?></a>
                                        </div>
                                        <div class="solution-card-meta">
                                            <span><i class="fas fa-user"></i> By: <?php echo htmlspecialchars($solution['user_name']); ?></span>
                                            <span><i class="fas fa-thumbs-up"></i> <?php echo $solution['upvotes']; ?> votes</span>
                                            <span><i class="fas fa-eye"></i> <?php echo $solution['views_count'] ?? 0; ?> views</span>
                                            <span><i class="fas fa-bookmark"></i> Saved <?php echo timeAgo($solution['saved_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="solution-card-body">
                                    <div class="solution-card-content">
                                        <?php 
                                        $solution_text = $solution['content'] ?? $solution['description'] ?? '';
                                        echo htmlspecialchars(substr($solution_text, 0, 200)); 
                                        if (strlen($solution_text) > 200) echo '...';
                                        ?>
                                    </div>
                                </div>
                                <div class="solution-card-footer" style="flex-direction: row; align-items: center; justify-content: space-between;">
                                    <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                    <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Remove this solution from saved?')">
                                        <input type="hidden" name="remove_saved_solution" value="1">
                                        <input type="hidden" name="solution_id" value="<?php echo $solution['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this solution from saved?')">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-bookmark" style="font-size: 3rem; margin-bottom: 15px; display: block; text-align: center;"></i>
                            <p>You haven't saved any solutions yet</p>
                            <p style="font-size: 0.9rem; margin-top: 10px;">Click the save button on any solution to add it here</p>
                            <a href="solutions.php" class="btn btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-code"></i> Browse Solutions
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Report Bug Tab -->
                <div id="report-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1><?php echo $edit_mode ? 'Edit Bug' : 'Report a Bug'; ?></h1>
                    </div>

                    <div class="sub-tab-nav">
                        <a href="#" class="btn tab-link" data-tab="my-bugs">My Bugs</a>
                        <a href="#" class="btn tab-link" data-tab="saved-tab">Saved Bugs</a>
                        <a href="#" class="btn tab-link active" data-tab="report-tab">Report a Bug</a>
                    </div>

                    <div class="card">
                        <?php if (!empty($bug_submission_error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $bug_submission_error; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($bug_update_error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $bug_update_error; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['new_bug']) && isset($_GET['bug_id'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Bug reported successfully! 
                            <a href="post-details.php?id=<?php echo $_GET['bug_id']; ?>">View your bug</a>
                        </div>
                        <?php endif; ?>

                        <form id="report-bug-form" method="POST" action="" enctype="multipart/form-data" class="loader-form">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="update_bug" value="1">
                                <input type="hidden" name="bug_id" value="<?php echo $edit_bug_data['id']; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="bug-title">Bug Title *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-heading"></i>
                                    <input type="text" id="bug-title" name="bug_title" class="form-control" 
                                           placeholder="Clear, descriptive title of the bug" 
                                           value="<?php echo $edit_mode ? htmlspecialchars($edit_bug_data['title']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bug-description">Description *</label>
                                <textarea id="bug-description" name="bug_description" class="form-control" 
                                          placeholder="Detailed description of the bug, including steps to reproduce, expected behavior, and actual behavior" 
                                          rows="6" required><?php echo $edit_mode ? htmlspecialchars($edit_bug_data['description']) : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="bug-code">Code Snippet (if applicable)</label>
                                <textarea id="bug-code" name="bug_code" class="form-control code-font" 
                                          placeholder="Relevant code that demonstrates the issue. Use ``` for code blocks." 
                                          rows="8" style="font-family: 'Fira Code', monospace;"><?php echo $edit_mode ? htmlspecialchars($edit_bug_data['code_snippet']) : ''; ?></textarea>
                                <small style="color: var(--text-muted);">Use triple backticks (```) for code blocks</small>
                            </div>

                            <div class="form-group">
                                <label for="bug-tags">Tags *</label>
                                <div class="autocomplete-container">
                                    <div class="input-with-icon">
                                        <i class="fas fa-tags"></i>
                                        <input type="text" id="bug-tags" name="bug_tags" class="form-control" 
                                               placeholder="JavaScript, React, Node.js (comma separated)" 
                                               value="<?php echo $edit_mode ? htmlspecialchars($edit_bug_data['tags']) : ''; ?>" required>
                                    </div>
                                    <div id="tag-suggestions" class="autocomplete-items"></div>
                                </div>
                                <small style="color: var(--text-muted);">Add relevant tags to help others find your bug</small>
                            </div>

                            <div class="form-group">
                                <label for="bug-priority">Priority *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <select id="bug-priority" name="bug_priority" class="form-control" required>
                                        <option value="low" <?php echo ($edit_mode && $edit_bug_data['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo (!$edit_mode || ($edit_mode && $edit_bug_data['priority'] == 'medium')) ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($edit_mode && $edit_bug_data['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                        <option value="critical" <?php echo ($edit_mode && $edit_bug_data['priority'] == 'critical') ? 'selected' : ''; ?>>Critical</option>
                                    </select>
                                </div>
                            </div>

                            <?php if (!$edit_mode): ?>
                            <div class="file-upload-container">
                                <label class="file-upload-label">Screenshots (Optional)</label>
                                <div class="file-upload-box" id="image-upload-box">
                                    <i class="fas fa-image"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <input type="file" id="bug-images" name="bug_images[]" multiple accept="image/*">
                                </div>
                                <div id="image-preview" class="file-preview"></div>
                            </div>

                            <div class="file-upload-container">
                                <label class="file-upload-label">Additional Files (Optional)</label>
                                <div class="file-upload-box" id="file-upload-box">
                                    <i class="fas fa-file"></i>
                                    <p>Drag & drop files here or click to browse</p>
                                    <input type="file" id="bug-files" name="bug_files[]" multiple>
                                </div>
                                <div id="file-preview" class="file-preview"></div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-info-circle"></i> Note: File uploads cannot be modified when editing a bug. To change files, please delete and recreate the bug report.
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="form-submit">
                                <i class="fas fa-<?php echo $edit_mode ? 'save' : 'paper-plane'; ?>"></i> 
                                <?php echo $edit_mode ? 'Save Changes' : 'Submit Bug Report'; ?>
                            </button>

                            <?php if ($edit_mode): ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="post-details.php?id=<?php echo $edit_bug_data['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Bug Details
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="settings-tab" class="tab-content">
                    <div class="dashboard-header">
                        <h1>Settings</h1>
                    </div>

                    <div class="card">
                        <div class="settings-section">
                            <h3>Account Settings</h3>
                            
                            <div class="form-group">
                                <label for="settings-email">Email Address</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="settings-email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="settings-username">Username</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="settings-username" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="settings-title">Title</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="settings-title" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_title'] ?? 'Developer'); ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3>Change Password</h3>
                            
                            <div class="form-group">
                                <label for="current-password">Current Password</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="current-password" class="form-control">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="new-password">New Password</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="new-password" class="form-control">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm-password">Confirm New Password</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm-password" class="form-control">
                                </div>
                            </div>

                            <button type="submit" class="form-submit">Update Password</button>
                        </div>

                        <div class="settings-section">
                            <h3>Notification Preferences</h3>
                            
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="notif-email" checked>
                                <label for="notif-email" style="margin-bottom: 0;">Email notifications</label>
                            </div>

                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="notif-browser" checked>
                                <label for="notif-browser" style="margin-bottom: 0;">Browser notifications</label>
                            </div>

                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="notif-solutions" checked>
                                <label for="notif-solutions" style="margin-bottom: 0;">Notify me about new solutions to my bugs</label>
                            </div>

                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="notif-comments" checked>
                                <label for="notif-comments" style="margin-bottom: 0;">Notify me about comments on my bugs</label>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3>Danger Zone</h3>
                            <p style="color: var(--text-muted); margin-bottom: 20px;">Once you delete your account, there is no going back. Please be certain.</p>
                            
                            <button type="button" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Account</button>
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <a href="?logout=true" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            const mobileTabNav = document.querySelector('.mobile-tab-nav');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');
                    
                    // Update active tab link
                    tabLinks.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update active state for sub-tab-nav as well
                    const subTabLinks = document.querySelectorAll('.sub-tab-nav .tab-link');
                    subTabLinks.forEach(subLink => {
                        subLink.classList.remove('active');
                        if (subLink.getAttribute('data-tab') === tabName) {
                            subLink.classList.add('active');
                        }
                    });

                    // Show corresponding tab content
                    tabContents.forEach(tab => {
                        if (tab.id === tabName) {
                            tab.classList.add('active');
                        } else {
                            tab.classList.remove('active');
                        }
                    });
                    
                    // Scroll to top when switching tabs
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Show/hide mobile bug navigation
                    const bugTabs = ['my-bugs', 'report-tab', 'saved-tab'];
                    if (bugTabs.includes(tabName)) {
                        mobileTabNav.classList.add('visible');
                    } else {
                        mobileTabNav.classList.remove('visible');
                    }
                });
            });
            
            // Handle URL parameters for tab switching
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabLink = document.querySelector(`.tab-link[data-tab="${tabParam}"]`);

                // Open parent dropdown if the tab is inside one
                if (tabLink && tabLink.closest('.has-dropdown')) {
                    const dropdown = tabLink.closest('.has-dropdown');
                    dropdown.classList.add('open');

                    // Also make the parent dropdown toggle active
                    const parentToggle = dropdown.querySelector('.dropdown-toggle');
                    if(parentToggle) {
                        // Remove active from other top-level links
                        document.querySelectorAll('.sidebar-nav > li > a').forEach(l => l.classList.remove('active'));
                        parentToggle.classList.add('active');
                    }
                }


                if (tabLink) {
                    tabLink.click();
                }
            }
            
            // Activity Chart with Real Data
            const activityCtx = document.getElementById('activityChart').getContext('2d');

            // Sidebar Dropdown Logic
            const dropdownToggles = document.querySelectorAll('.sidebar-nav .dropdown-toggle');
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    parent.classList.toggle('open');
                });
            });

            // Keep dropdown open when a child is active
            const allTabLinks = document.querySelectorAll('.sidebar-nav .tab-link');
            allTabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Remove 'open' from all dropdowns first
                    document.querySelectorAll('.sidebar-nav .has-dropdown').forEach(dd => dd.classList.remove('open'));
                    // If the clicked link is in a dropdown, open its parent
                    if (this.closest('.has-dropdown')) {
                        this.closest('.has-dropdown').classList.add('open');
                    }
                });
            });
            
            // Prepare chart data from PHP
            const activityData = <?php echo json_encode($chart_data['activity'] ?? []); ?>;
            const statusData = <?php echo json_encode($chart_data['status'] ?? []); ?>;
            
            // Process activity data for chart
            const last7Days = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                last7Days.push(date.toISOString().split('T')[0]);
            }
            
            const bugsData = last7Days.map(date => {
                const dayData = activityData.filter(item => item.type === 'bug' && item.date === date);
                return dayData.length > 0 ? parseInt(dayData[0].count) : 0;
            });
            
            const solutionsData = last7Days.map(date => {
                const dayData = activityData.filter(item => item.type === 'solution' && item.date === date);
                return dayData.length > 0 ? parseInt(dayData[0].count) : 0;
            });
            
            const dayNames = last7Days.map(date => {
                const d = new Date(date);
                return d.toLocaleDateString('en-US', { weekday: 'short' });
            });
            
            const activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: dayNames,
                    datasets: [{
                        label: 'Bugs Reported',
                        data: bugsData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.3,
                        fill: true
                    }, {
                        label: 'Solutions Provided',
                        data: solutionsData,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            },
                            ticks: {
                                color: '#64748b'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            },
                            ticks: {
                                color: '#64748b'
                            }
                        }
                    }
                }
            });

            // Bug Status Chart with Real Data
            const bugStatusCtx = document.getElementById('bugStatusChart').getContext('2d');
            
            const statusLabels = statusData.map(item => item.status);
            const statusCounts = statusData.map(item => parseInt(item.count));
            const statusColors = {
                'open': '#10b981',
                'in-progress': '#f59e0b',
                'solved': '#6366f1',
                'closed': '#8b5cf6'
            };
            
            const backgroundColors = statusLabels.map(status => statusColors[status] || '#64748b');
            
            const bugStatusChart = new Chart(bugStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels.map(status => status.charAt(0).toUpperCase() + status.slice(1)),
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: backgroundColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#cbd5e1',
                                padding: 15
                            }
                        }
                    }
                }
            });

            // File upload functionality
            const imageUploadBox = document.getElementById('image-upload-box');
            const fileUploadBox = document.getElementById('file-upload-box');
            const imageInput = document.getElementById('bug-images');
            const fileInput = document.getElementById('bug-files');
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
                            <img src="${e.target.result}" alt="${file.name}">
                            <span>${file.name}</span>
                            <span class="remove-file" data-index="${i}">&times;</span>
                        `;
                        imagePreview.appendChild(previewItem);
                        
                        // Add remove functionality
                        previewItem.querySelector('.remove-file').addEventListener('click', function() {
                            const index = parseInt(this.getAttribute('data-index'));
                            const newFiles = Array.from(imageInput.files);
                            newFiles.splice(index, 1);
                            
                            // Create a new FileList (simulated)
                            const dataTransfer = new DataTransfer();
                            newFiles.forEach(f => dataTransfer.items.add(f));
                            imageInput.files = dataTransfer.files;
                            
                            previewItem.remove();
                        });
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
                        <span class="remove-file" data-index="${i}">&times;</span>
                    `;
                    filePreview.appendChild(previewItem);
                    
                    // Add remove functionality
                    previewItem.querySelector('.remove-file').addEventListener('click', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        const newFiles = Array.from(fileInput.files);
                        newFiles.splice(index, 1);
                        
                        // Create a new FileList (simulated)
                        const dataTransfer = new DataTransfer();
                        newFiles.forEach(f => dataTransfer.items.add(f));
                        fileInput.files = dataTransfer.files;
                        
                        previewItem.remove();
                    });
                }
            }

            // Tag autocomplete functionality
            const tagsInput = document.getElementById('bug-tags');
            const tagSuggestions = document.getElementById('tag-suggestions');
            const popularTags = <?php echo json_encode($popular_tags ?? []); ?>;

            if (tagsInput && tagSuggestions) {
                tagsInput.addEventListener('input', function() {
                    const allTags = this.value.split(',').map(t => t.trim());
                    const currentTag = allTags[allTags.length - 1].toLowerCase();

                    if (currentTag.length < 1) {
                        tagSuggestions.style.display = 'none';
                        return;
                    }

                    const existingTags = allTags.slice(0, -1);
                    const filteredTags = popularTags.filter(tag =>
                        tag.toLowerCase().includes(currentTag) &&
                        !existingTags.some(t => t.toLowerCase() === tag.toLowerCase())
                    );

                    tagSuggestions.innerHTML = '';
                    if (filteredTags.length > 0) {
                        tagSuggestions.style.display = 'block';
                        filteredTags.slice(0, 5).forEach(tag => {
                            const item = document.createElement('div');
                            item.className = 'autocomplete-item';
                            item.textContent = tag;
                            item.addEventListener('click', function() {
                                allTags[allTags.length - 1] = tag;
                                tagsInput.value = allTags.join(', ') + ', ';
                                tagSuggestions.style.display = 'none';
                                tagsInput.focus();
                            });
                            tagSuggestions.appendChild(item);
                        });
                    } else {
                        tagSuggestions.style.display = 'none';
                    }
                });

                // Hide suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!tagsInput.contains(e.target) && !tagSuggestions.contains(e.target)) {
                        tagSuggestions.style.display = 'none';
                    }
                });
            }
            // Bug report form validation
            const bugForm = document.querySelector('#report-tab form');
            if (bugForm) {
                bugForm.addEventListener('submit', function(e) {
                    let valid = true;
                    const requiredFields = ['bug-title', 'bug-description', 'bug-tags'];
                    
                    requiredFields.forEach(fieldId => {
                        const field = document.getElementById(fieldId);
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = 'var(--danger)';
                        } else {
                            field.style.borderColor = 'var(--border)';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }

            // Form submission loader
            const formsWithLoader = document.querySelectorAll('.loader-form');
            formsWithLoader.forEach(form => {
                form.addEventListener('submit', function() {
                    const loader = document.getElementById('universal-loader');
                    if (loader) {
                        loader.style.display = 'flex';
                        loader.style.opacity = '1';
                    }
                });
            });

        });

        // Sidebar Toggle for Mobile
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('dashboard-sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');

        if (sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                document.body.classList.toggle('sidebar-overlay-active');
                sidebarOverlay.classList.toggle('active');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                document.body.classList.remove('sidebar-overlay-active');
                sidebarOverlay.classList.remove('active');
            });

            // Close sidebar when a nav link is clicked
            const tabLinksInSidebar = sidebar.querySelectorAll('.sidebar-nav a.tab-link');
            tabLinksInSidebar.forEach(link => {
                link.addEventListener('click', function() {
                    // Add a small delay to allow tab switching logic to run
                    setTimeout(() => {
                        sidebar.classList.remove('open');
                        sidebarOverlay.classList.remove('active');
                        document.body.classList.remove('sidebar-overlay-active');
                    }, 100);
                });
            });
        }
    </script>
</body>
</html>