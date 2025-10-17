<?php
// profile.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/Components/header.php');
require_once 'config/database.php';
require_once 'includes/utils.php';

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // If no ID, redirect to own profile if logged in, or to login page
    if (isset($_SESSION['user_id'])) {
        header('Location: profile.php?id=' . $_SESSION['user_id']);
    } else {
        header('Location: auth.php');
    }
    exit();
}

$user_id = (int)$_GET['id'];

// Fetch user data
$user = null;
$user_stats = [];
$user_bugs = [];
$user_solutions = [];

try {
    // Get user profile data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // User not found, redirect to a 404 page or leaderboard
        header('Location: leaderboard.php');
        exit();
    }

    // Get user stats
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM bugs WHERE user_id = ?) as bugs_reported,
            (SELECT COUNT(*) FROM solutions WHERE user_id = ?) as solutions_provided,
            (SELECT COUNT(*) FROM solutions WHERE user_id = ? AND is_approved = 1) as solutions_accepted
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get user's recent bugs
    $stmt = $pdo->prepare("
        SELECT b.*, COUNT(DISTINCT s.id) as solution_count, COUNT(DISTINCT c.id) as comment_count
        FROM bugs b
        LEFT JOIN solutions s ON b.id = s.bug_id
        LEFT JOIN comments c ON b.id = c.bug_id
        WHERE b.user_id = ?
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $user_bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's recent solutions
    $stmt = $pdo->prepare("
        SELECT s.*, b.title as bug_title, b.id as bug_id_for_link, COUNT(sv.id) as upvotes
        FROM solutions s
        JOIN bugs b ON s.bug_id = b.id
        LEFT JOIN solution_votes sv ON s.id = sv.solution_id AND sv.vote_type = 'up'
        WHERE s.user_id = ?
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $user_solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching profile data: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?>'s Profile - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        /* Sidebar */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .profile-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--border);
        }

        .profile-header {
            text-align: center;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 3rem;
            flex-shrink: 0;
            margin: 0 auto 20px;
            border: 4px solid var(--border);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-header h1 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }

        .profile-header .user-title {
            font-size: 1.2rem;
            color: var(--accent-primary);
            margin-bottom: 15px;
        }

        .profile-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .profile-details-list {
            list-style: none;
            margin-top: 20px;
        }

        .profile-details-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .profile-details-list i {
            color: var(--text-muted);
            width: 20px;
            text-align: center;
        }

        .profile-details-list a {
            color: var(--accent-primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .profile-details-list a:hover {
            text-decoration: underline;
        }

        .profile-card h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .skill-tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-decoration: none;
        }

        .skill-tag:hover {
            background: rgba(99, 102, 241, 0.25);
        }

        /* Main Content */
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .profile-bio {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-secondary);
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-primary);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .activity-item a:hover {
            color: var(--accent-primary);
        }

        .activity-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* ========== RESPONSIVE DESIGN ========== */

        /* For Tablets (e.g., iPad Pro landscape) */
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 280px 1fr;
                gap: 25px;
            }
            .profile-avatar {
                width: 130px;
                height: 130px;
                font-size: 2.5rem;
            }
            .profile-header h1 {
                font-size: 2.2rem;
            }
            .stat-value {
                font-size: 1.8rem;
            }
        }

        /* For Tablets (e.g., iPad portrait) */
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                display: flex;
                flex-direction: column;
            }
            .profile-sidebar {
                order: 0;
            }
            .profile-main {
                order: 1;
                display: flex;
                flex-direction: column;
                gap: 25px;
            }
            .profile-stats-container {
                order: 1;
            }
            .profile-card-about {
                order: 2;
            }
            .profile-card-bugs {
                order: 3;
            }
            .profile-card-solutions {
                order: 4;
            }
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            .profile-header h1 {
                font-size: 2rem;
            }
        }

        /* For smaller tablets and large phones */
        @media (max-width: 600px) {
            .profile-stats {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .stat-item {
                padding: 15px;
            }
            .stat-value {
                font-size: 1.6rem;
            }
            .profile-card {
                padding: 20px;
            }
        }

        /* For mobile phones */
        @media (max-width: 500px) {
            .profile-avatar {
                width: 120px;
                height: 120px;
            }

            .profile-header h1 {
                font-size: 2rem;
            }
            .profile-stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container profile-container">
        <?php if ($user): ?>
            <!-- Profile Sidebar -->
            <aside class="profile-sidebar">
                <div class="profile-card profile-header">
                    <div class="profile-avatar" style="background-color: <?php echo htmlspecialchars($user['avatar_color']); ?>">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>'s Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                    <p class="user-title"><?php echo htmlspecialchars($user['title'] ?? 'Developer'); ?></p>

                    <div class="profile-actions">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                            <a href="chat.php?user_id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Message
                            </a>
                        <?php endif; ?>
                    </div>

                    <ul class="profile-details-list">
                        <?php if (!empty($user['location'])): ?>
                            <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($user['company'])): ?>
                            <li><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['company']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($user['website'])): ?>
                            <li><i class="fas fa-globe"></i> <a href="<?php echo htmlspecialchars($user['website']); ?>" target="_blank" rel="noopener noreferrer">Personal Website</a></li>
                        <?php endif; ?>
                        <li><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></li>
                    </ul>

                    <ul class="profile-details-list">
                        <?php if (!empty($user['github'])): ?>
                            <li><i class="fab fa-github"></i> <a href="https://github.com/<?php echo htmlspecialchars($user['github']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($user['github']); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($user['twitter'])): ?>
                            <li><i class="fab fa-twitter"></i> <a href="https://twitter.com/<?php echo htmlspecialchars(str_replace('@', '', $user['twitter'])); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($user['twitter']); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($user['linkedin'])): ?>
                            <li><i class="fab fa-linkedin"></i> <a href="<?php echo htmlspecialchars($user['linkedin']); ?>" target="_blank" rel="noopener noreferrer">LinkedIn Profile</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (!empty($user['skills'])): ?>
                <div class="profile-card profile-card-skills">
                    <h2>Skills & Technologies</h2>
                    <div class="skills-container">
                        <?php 
                        $skills = explode(',', $user['skills']);
                        foreach ($skills as $skill): 
                            $trimmed_skill = trim($skill);
                            if (!empty($trimmed_skill)):
                        ?>
                            <a href="/devbug/bug-post.php?technology=<?php echo urlencode($trimmed_skill); ?>" class="skill-tag"><?php echo htmlspecialchars($trimmed_skill); ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <!-- Profile Main Content -->
            <main class="profile-main">
                <div class="profile-stats profile-stats-container">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($user['reputation'] ?? 0); ?></div>
                        <div class="stat-label">Reputation</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_stats['bugs_reported'] ?? 0; ?></div>
                        <div class="stat-label">Bugs Reported</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_stats['solutions_provided'] ?? 0; ?></div>
                        <div class="stat-label">Solutions Provided</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_stats['solutions_accepted'] ?? 0; ?></div>
                        <div class="stat-label">Solutions Accepted</div>
                    </div>
                </div>

                <?php if (!empty($user['bio'])): ?>
                <div class="profile-card profile-card-about">
                    <h2>About Me</h2>
                    <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="profile-card profile-card-bugs">
                    <h2>Recent Bugs</h2>
                    <ul class="activity-list">
                        <?php foreach($user_bugs as $bug): ?>
                            <li class="activity-item">
                                <a href="post-details.php?id=<?php echo $bug['id']; ?>"><?php echo htmlspecialchars(substr($bug['title'], 0, 100)); ?><?php if(strlen($bug['title']) > 100) echo '...'; ?></a>
                                <div class="activity-meta">
                                    <span><?php echo timeAgo($bug['created_at']); ?></span> &bull;
                                    <span><?php echo $bug['solution_count']; ?> Solutions</span> &bull;
                                    <span><?php echo $bug['comment_count']; ?> Comments</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($user_bugs)): ?>
                            <p>No bugs reported yet.</p>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="profile-card profile-card-solutions">
                    <h2>Recent Solutions</h2>
                     <ul class="activity-list">
                        <?php foreach($user_solutions as $solution): ?>
                            <li class="activity-item">
                                <a href="post-details.php?id=<?php echo $solution['bug_id_for_link']; ?>#solution-<?php echo $solution['id']; ?>">Solution for: <?php echo htmlspecialchars(substr($solution['bug_title'], 0, 100)); ?><?php if(strlen($solution['bug_title']) > 100) echo '...'; ?></a>
                                <div class="activity-meta">
                                    <span><?php echo timeAgo($solution['created_at']); ?></span> &bull;
                                    <span><?php echo $solution['upvotes']; ?> Upvotes</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($user_solutions)): ?>
                            <p>No solutions provided yet.</p>
                        <?php endif; ?>
                    </ul>
                </div>
            </main>

        <?php else: ?>
            <div class="profile-details">
                <h2>User Not Found</h2>
                <p>The user profile you are looking for does not exist.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include(__DIR__ . '/footer.html'); ?>
</body>
</html>
