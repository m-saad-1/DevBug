<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/Components/header.php');

// Database connection
require_once 'config/database.php';
require_once 'includes/utils.php';

// Fetch recent bugs from database
$recent_bugs = [];
try {
    $sql = "SELECT b.*, u.name as user_name, u.avatar_color, u.profile_picture,
                   (SELECT COUNT(*) FROM comments WHERE bug_id = b.id) as comment_count
            FROM bugs b 
            LEFT JOIN users u ON b.user_id = u.id 
            ORDER BY b.created_at DESC 
            LIMIT 3";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recent_bugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent bugs: " . $e->getMessage());
}

// Get platform statistics
$stats = [];    
try {
    $stats_sql = "SELECT 
        (SELECT COUNT(*) FROM bugs) as total_bugs,
        (SELECT COUNT(*) FROM bugs WHERE status = 'solved') as solved_bugs,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(DISTINCT tags) FROM bugs WHERE tags IS NOT NULL) as technologies";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    // Default stats if query fails
    $stats = [
        'total_bugs' => '5,281',
        'solved_bugs' => '4,362',
        'total_users' => '12,489',
        'technologies' => '27'
    ];
}




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevBug - Community-Powered Bug Solving</title>
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
            :root { --scale-factor: 0.95; }
        }
        @media (max-width: 768px) {
            :root { --scale-factor: 0.9; }
        }
        @media (max-width: 600px) {
            :root { --scale-factor: 0.85; }
        }
        @media (max-width: 400px) {
            :root { --scale-factor: 0.8; }
        }

        /* Apply scaling to all elements */
        * {
            box-sizing: border-box;
        }

        html {
            font-size: calc(1rem * var(--scale-factor));
        }

        .page-header {
            padding: calc(80px * var(--scale-factor)) 0 calc(20px * var(--scale-factor));
            text-align: center;
        }
    
        /* Hero Section */
        .hero {
            padding: calc(80px * var(--scale-factor)) 0 calc(20px * var(--scale-factor));
            position: relative;
            overflow: hidden;
        }

        #particles-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .hero-content {
            text-align: center;
            max-width: calc(800px * var(--scale-factor));
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: calc(3.5rem * var(--scale-factor));
            margin-bottom: calc(24px * var(--scale-factor));
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: calc(1.3rem * var(--scale-factor));
            color: var(--text-secondary);
            margin-bottom: calc(40px * var(--scale-factor));
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            gap: calc(20px * var(--scale-factor));
            justify-content: center;
            margin-bottom: calc(60px * var(--scale-factor));
        }

        .search-bar {
            max-width: calc(600px * var(--scale-factor));
            margin: calc(60px * var(--scale-factor)) auto;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: calc(18px * var(--scale-factor)) calc(25px * var(--scale-factor));
            border-radius: calc(12px * var(--scale-factor));
            border: 1px solid var(--border);
            background: rgba(26, 26, 40, 0.8);
            backdrop-filter: blur(calc(10px * var(--scale-factor)));
            color: var(--text-primary);
            font-size: calc(1.1rem * var(--scale-factor));
            box-shadow: 0 calc(8px * var(--scale-factor)) calc(32px * var(--scale-factor)) rgba(0, 0, 0, 0.3);
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 calc(8px * var(--scale-factor)) calc(32px * var(--scale-factor)) rgba(99, 102, 241, 0.3);
        }

        .search-bar button {
            position: absolute;
            right: 8px;
            top: 8px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            border: none;
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(10px * var(--scale-factor)) calc(22px * var(--scale-factor));
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .search-bar button:hover {
            transform: translateY(-2px);
            box-shadow: var(--glow);
        }

        /* Main Content */
        .main-content {
            padding: calc(60px * var(--scale-factor)) 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: calc(60px * var(--scale-factor));
            margin-top: calc(40px * var(--scale-factor));
            position: relative;
        }

        .section-title h2 {
            font-size: calc(2.5rem * var(--scale-factor));
            font-weight: 700;
            margin-bottom: calc(16px * var(--scale-factor));
            display: inline-block;
        }

        .section-title h2:after {
            content: '';
            position: absolute;
            bottom: calc(-10px * var(--scale-factor));
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 2px;
        }

        .section-title p {
            color: var(--text-secondary);
            font-size: calc(1.2rem * var(--scale-factor));
            max-width: calc(600px * var(--scale-factor));
            margin: 0 auto;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(calc(300px * var(--scale-factor)), 1fr));
            gap: calc(30px * var(--scale-factor));
            margin-bottom: calc(80px * var(--scale-factor));
        }

        .feature-card {
            background: var(--bg-card);
            border-radius: calc(16px * var(--scale-factor));
            padding: calc(40px * var(--scale-factor)) calc(30px * var(--scale-factor));
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(calc(-10px * var(--scale-factor)));
            box-shadow: 0 calc(20px * var(--scale-factor)) calc(40px * var(--scale-factor)) rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .feature-card i {
            font-size: calc(3rem * var(--scale-factor));
            margin-bottom: calc(25px * var(--scale-factor));
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .feature-card h3 {
            margin-bottom: calc(20px * var(--scale-factor));
            font-size: calc(1.5rem * var(--scale-factor));
            color: var(--text-primary);
        }

        .feature-card p {
            color: var(--text-secondary);
        }

        /* Recent Bugs */
        .bugs-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: calc(25px * var(--scale-factor));
            margin-bottom: calc(60px * var(--scale-factor));
        }

        .bug-card {
            background: var(--bg-card);
            border-radius: calc(16px * var(--scale-factor));
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .bug-card:hover {
            transform: translateY(calc(-5px * var(--scale-factor)));
            box-shadow: 0 calc(15px * var(--scale-factor)) calc(30px * var(--scale-factor)) rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .bug-header {
            padding: calc(25px * var(--scale-factor)) calc(25px * var(--scale-factor)) calc(15px * var(--scale-factor));
            border-bottom: 1px solid var(--border);
        }

        .bug-title {
            font-size: calc(1.3rem * var(--scale-factor));
            line-height: 1.4;
            margin-bottom: calc(15px * var(--scale-factor));
            color: var(--text-primary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.8em; /* line-height (1.4) * 2 lines */
        }

        .bug-title a {
            color: var(--text-primary);
            text-decoration: none;
        }

        .bug-title a:hover {
            color: var(--accent-primary);
        }

        .bug-tags {
            display: flex;
            gap: calc(8px * var(--scale-factor));
            flex-wrap: wrap;
            margin-top: calc(12px * var(--scale-factor));
        }

        .tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: calc(6px * var(--scale-factor)) calc(12px * var(--scale-factor));
            border-radius: calc(20px * var(--scale-factor));
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
            white-space: nowrap;
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

        .tag.java {
            background: rgba(237, 139, 0, 0.15);
            color: #ed8b00;
            border-color: rgba(237, 139, 0, 0.3);
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

        .bug-body {
            padding: calc(20px * var(--scale-factor)) calc(25px * var(--scale-factor));
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .bug-description {
            color: var(--text-secondary);
            margin-bottom: calc(15px * var(--scale-factor));
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
            max-height: 3em; /* Fallback for browsers that don't support -webkit-line-clamp. (line-height * lines) */
        }

        .code-snippet {
            background: rgba(0, 0, 0, 0.3);
            border-radius: calc(8px * var(--scale-factor));
            padding: calc(15px * var(--scale-factor));
            margin: calc(10px * var(--scale-factor)) 0;
            overflow-x: hidden;
            border: 1px solid var(--border);
            font-family: 'Fira Code', monospace;
            font-size: calc(0.85rem * var(--scale-factor));
            line-height: 1.4;
            /* Hide scrollbar for different browsers */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        .code-snippet::-webkit-scrollbar {
            display: none; /* Chrome, Safari, and Opera */
        }

        .code-snippet pre {
            margin: 0;
            white-space: pre-wrap; /* Allow code to wrap */
            background: transparent !important;
        }

        .code-snippet code {
            background: transparent !important;
            padding: 0 !important;
        }

        .view-full-code {
            display: block;
            text-align: center;
            margin-top: calc(8px * var(--scale-factor));
            color: var(--accent-primary);
            font-size: 0.85rem;
            text-decoration: none;
        }

        .view-full-code:hover {
            text-decoration: underline;
        }

        .bug-footer {
            padding: calc(20px * var(--scale-factor)) calc(25px * var(--scale-factor));
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid var(--border);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: calc(12px * var(--scale-factor));
            margin-bottom: calc(15px * var(--scale-factor));
        }

        .user-avatar {
            width: calc(36px * var(--scale-factor));
            height: calc(36px * var(--scale-factor));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .user-name {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .bug-activity {
            display: flex;
            gap: calc(20px * var(--scale-factor));
            justify-content: flex-end;
        }

        .bug-activity span {
            display: flex;
            align-items: center;
            gap: calc(6px * var(--scale-factor));
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Stats Section */
        .stats {
            background: linear-gradient(135deg, rgba(26, 26, 40, 0.8) 0%, rgba(26, 26, 40, 0.9) 100%);
            padding: calc(80px * var(--scale-factor)) 0;
            text-align: center;
            margin: calc(80px * var(--scale-factor)) 0;
            position: relative;
            border: 1px solid var(--border);
            border-radius: calc(16px * var(--scale-factor));
            overflow: hidden;
        }

        .stats:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: calc(40px * var(--scale-factor));
            position: relative;
            z-index: 2;
        }

        .stat-item {
            padding: calc(20px * var(--scale-factor));
        }

        .stat-item i {
            font-size: calc(2.8rem * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number {
            font-size: calc(2.8rem * var(--scale-factor));
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: calc(1.1rem * var(--scale-factor));
        }

        /* CTA Section */
        .cta {
            text-align: center;
            padding: calc(80px * var(--scale-factor)) 0;
            background: var(--bg-secondary);
            border-radius: calc(16px * var(--scale-factor));
            margin: calc(80px * var(--scale-factor)) 0;
            border: 1px solid var(--border);
        }

        .cta h2 {
            font-size: calc(2.5rem * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
        }

        .cta p {
            color: var(--text-secondary);
            font-size: calc(1.2rem * var(--scale-factor));
            max-width: calc(600px * var(--scale-factor));
            margin: 0 auto calc(40px * var(--scale-factor));
        }

        /* No Bugs Message */
        .no-bugs {
            text-align: center;
            padding: calc(60px * var(--scale-factor)) calc(20px * var(--scale-factor));
            color: var(--text-muted);
            grid-column: 1 / -1;
        }

        .no-bugs i {
            font-size: calc(3rem * var(--scale-factor));
            margin-bottom: calc(20px * var(--scale-factor));
            color: var(--accent-primary);
        }

        .no-bugs h3 {
            font-size: calc(1.5rem * var(--scale-factor));
            margin-bottom: calc(10px * var(--scale-factor));
            color: var(--text-primary);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features, .bugs-container {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            }

            .bug-header {
                padding: 20px 20px 12px;
            }

            .bug-body {
                padding: 15px 20px;
            }

            .bug-footer {
                padding: 15px 20px;
            }

            .bug-title {
                font-size: 1.2rem;
            }

            .bug-description {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 768px) {
            .stats {
                padding: 60px 0;
            }
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-title h2 {
                font-size: 2rem;
            }
            
            .hero h1 {
                font-size: 2.3rem;
            }

            /* Mobile adjustments for bug cards */
            .bugs-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .bug-card {
                max-width: 100%;
            }

            .bug-header {
                padding: 18px 18px 10px;
            }

            .bug-body {
                padding: 12px 18px;
            }

            .bug-footer {
                padding: 12px 18px;
            }

            .bug-title {
                font-size: 1.1rem;
                min-height: 2.8em;
            }

            .bug-description {
                font-size: 0.9rem;
                -webkit-line-clamp: 2;
            }

            .code-snippet {
                padding: 12px;
                font-size: 0.8rem;
            }

            .user-info {
                gap: 10px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }

            .user-name {
                font-size: 0.9rem;
            }

            .bug-activity {
                gap: 12px;
            }

            .bug-activity span {
                font-size: 0.8rem;
            }

            .tag {
                padding: 5px 10px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .stats-container {
                gap: 15px;
                
            }

            .stat-item {
                padding: 15px;
            }

            .stat-item i {
                font-size: 1.8rem;
                margin-bottom: 15px;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .section-title h2 {
                font-size: 1.8rem;
            }

            /* Small mobile adjustments */
            .bug-header {
                padding: 15px 15px 8px;
            }

            .bug-body {
                padding: 10px 15px;
            }

            .bug-footer {
                padding: 10px 15px;
            }

            .bug-title {
                font-size: 1rem;
                min-height: 2.6em;
            }

            .bug-tags {
                gap: 6px;
            }

            .tag {
                padding: 4px 8px;
                font-size: 0.7rem;
            }

            .bug-activity {
                flex-wrap: wrap;
                gap: 8px;
            }

            .bug-activity span {
                flex: 1;
                min-width: 80px;
                justify-content: center;
            }
        }

        @media (max-width: 360px) {
            .bug-activity {
                flex-direction: column;
                gap: 8px;
            }

            .bug-activity span {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
 
    <!-- Hero Section -->
    <section class="hero">
        <canvas id="particles-canvas"></canvas>
        <div class="container">
            <div class="hero-content">
                <h1>Solve Bugs Together, <span class="code-font">Grow Faster</span></h1>
                <p>A community-driven platform where developers help each other solve programming challenges, share knowledge, and advance their skills.</p>
                <div class="hero-buttons">
                    <a href="dashboard.php?tab=report-tab" class="btn btn-primary"><i class="fas fa-bug"></i> Report a Bug</a>
                    <a href="solutions.php" class="btn btn-outline"><i class="fas fa-code"></i> Explore Solutions</a>
                </div>
                <div class="search-bar">
                    <form action="bug-post.php" method="GET">
                        <input type="text" name="search" placeholder="Search bugs by language, technology, or keyword...">
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container">
        <div class="container">
            <!-- Features Section -->
            <section>
                <div class="section-title">
                    <h2>How It Works</h2>
                    <p>Join our community of developers helping each other solve coding challenges</p>
                </div>
                <div class="features">
                    <div class="feature-card">
                        <i class="fas fa-bug"></i>
                        <h3>Post Bugs</h3>
                        <p>Share the programming issues you're facing with code snippets, descriptions, and screenshots.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-users"></i>
                        <h3>Collaborate</h3>
                        <p>Get help from developers worldwide who offer solutions, suggestions, and best practices.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-medal"></i>
                        <h3>Earn Reputation</h3>
                        <p>Gain points and badges for helping others solve their programming challenges.</p>
                    </div>
                </div>
            </section>

            <!-- Recent Bugs Section -->
            <section class="recent-bugs-section">
                <div class="section-title">
                    <h2>Recently Posted Bugs</h2>
                    <p>Check out the latest coding challenges from our community</p>
                </div>
                <div class="bugs-container">
                    <?php if (!empty($recent_bugs)): ?>
                        <?php foreach ($recent_bugs as $bug): 
                            // Get solution count for this bug
                            try {
                                $solution_sql = "SELECT COUNT(*) as solution_count FROM solutions WHERE bug_id = ?";
                                $solution_stmt = $pdo->prepare($solution_sql);
                                $solution_stmt->execute([$bug['id']]);
                                $solution_count = $solution_stmt->fetch(PDO::FETCH_ASSOC)['solution_count'];
                            } catch (PDOException $e) {
                                $solution_count = 0;
                            }
                        ?>
                            <div class="bug-card">
                                <div class="bug-header">
                                    <h3 class="bug-title">
                                        <a href="post-details.php?id=<?php echo $bug['id']; ?>"><?php echo htmlspecialchars($bug['title']); ?></a>
                                    </h3>
                                    <div class="bug-tags">
                                        <?php 
                                        if (!empty($bug['tags'])) {
                                            $tags = explode(',', $bug['tags']);
                                            $displayed_tags = 0;
                                            foreach ($tags as $tag):
                                                $tag = trim($tag);
                                                if (!empty($tag) && $displayed_tags < 3): // Limit to 3 tags
                                                    $displayed_tags++;
                                        ?>
                                            <span class="tag <?php echo strtolower($tag); ?>"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php 
                                                endif;
                                            endforeach;
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="bug-body">
                                    <p class="bug-description">
                                        <?php 
                                        $description = htmlspecialchars($bug['description']);
                                        if (strlen($description) > 150) {
                                            echo substr($description, 0, 150) . '...';
                                        } else {
                                            echo $description;
                                        }
                                        ?>
                                    </p>
                                    
                                    <?php if (!empty($bug['code_snippet'])): ?>
                                    <div class="code-snippet">
                                        <pre><code class="language-<?php echo detectLanguage($bug['tags']); ?>"><?php echo htmlspecialchars(substr($bug['code_snippet'], 0, 200)); ?></code></pre>
                                    </div>
                                    <a href="post-details.php?id=<?php echo $bug['id']; ?>#code" class="view-full-code">View full code snippet</a>
                                    <?php endif; ?>
                                </div>
                                <div class="bug-footer">
                                    <div class="user-info">
                                        <div class="user-avatar" style="background: <?php echo $bug['avatar_color'] ?? '#6366f1'; ?>; overflow: hidden;">
                                            <?php if (!empty($bug['profile_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars($bug['profile_picture']); ?>" alt="<?php echo htmlspecialchars($bug['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($bug['user_name'], 0, 2)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="user-name"><?php echo htmlspecialchars($bug['user_name']); ?></span>
                                    </div>
                                    <div class="bug-activity">
                                        <span><i class="far fa-comment"></i> <?php echo $bug['comment_count']; ?></span>
                                        <span><i class="far fa-lightbulb"></i> <?php echo $solution_count; ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo timeAgo($bug['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-bugs">
                            <i class="fas fa-bug"></i>
                            <h3>No bugs posted yet</h3>
                            <p>Be the first to report a bug and start the conversation!</p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="dashboard.php?tab=report-tab" class="btn btn-primary" style="margin-top: calc(15px * var(--scale-factor));">
                                    <i class="fas fa-plus"></i> Report Your First Bug
                                </a>
                            <?php else: ?>
                                <a href="auth.php" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-sign-in-alt"></i> Sign In to Report a Bug
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-top: calc(40px * var(--scale-factor));">
                    <a href="bug-post.php" class="btn btn-primary">View All Bugs</a>
                </div>
            </section> 
        </div>

        <!-- Stats Section -->
        <section class="stats">
            <div class="container">
                <div class="stats-container">
                    <div class="stat-item">
                        <i class="fas fa-bug"></i>
                        <div class="stat-number"><?php echo number_format($stats['total_bugs']); ?></div>
                        <div class="stat-label">Bugs Posted</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="stat-number"><?php echo number_format($stats['solved_bugs']); ?></div>
                        <div class="stat-label">Bugs Solved</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Developers</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-code"></i>
                        <div class="stat-number"><?php echo number_format($stats['technologies']); ?></div>
                        <div class="stat-label">Technologies</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta">
            <div class="container">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <h2>Ready to Join Our Developer Community?</h2>
                    <p>Sign up today and start solving bugs together with developers from around the world.</p>
                    <a href="auth.php" class="btn btn-primary">Create Your Account</a>
                <?php else: ?>
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Developer'); ?>!</h2>
                    <p>Continue exploring bugs or check your dashboard for updates.</p>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </section> 
    </main>

    <!-- Footer -->
    <?php include(__DIR__ . '/footer.html'); ?> 

    <script>
        // Particles animation for the hero section
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('particles-canvas');
            const ctx = canvas.getContext('2d');
            
            // Set canvas size
            function setCanvasSize() {
                canvas.width = window.innerWidth;
                canvas.height = canvas.parentElement.offsetHeight;
            }
            
            setCanvasSize();
            window.addEventListener('resize', setCanvasSize);
            
            // Create particles
            const particles = [];
            const particleCount = 100;
            
            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    radius: Math.random() * 2 + 1,
                    speed: Math.random() * 2 + 0.5,
                    opacity: Math.random() * 0.5 + 0.1,
                    direction: Math.random() * Math.PI * 2
                });
            }
            
            // Draw particles
            function drawParticles() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Draw gradient background
                const gradient = ctx.createRadialGradient(
                    canvas.width / 2,
                    canvas.height / 2,
                    0,
                    canvas.width / 2,
                    canvas.height / 2,
                    Math.max(canvas.width, canvas.height) / 2
                );
                gradient.addColorStop(0, 'rgba(26, 26, 40, 0.8)');
                gradient.addColorStop(1, 'rgba(10, 10, 15, 0.8)');
                
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Draw particles
                particles.forEach(particle => {
                    ctx.beginPath();
                    ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(99, 102, 241, ${particle.opacity})`;
                    ctx.fill();
                    
                    // Move particle
                    particle.x += Math.cos(particle.direction) * particle.speed;
                    particle.y += Math.sin(particle.direction) * particle.speed;
                    
                    // Wrap around edges
                    if (particle.x < 0) particle.x = canvas.width;
                    if (particle.x > canvas.width) particle.x = 0;
                    if (particle.y < 0) particle.y = canvas.height;
                    if (particle.y > canvas.height) particle.y = 0;
                });
                
                requestAnimationFrame(drawParticles);
            }
            
            drawParticles();
            
            // Button hover effects
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Search functionality placeholder
            const searchButton = document.querySelector('.search-bar button');
            if (searchButton) {
                searchButton.addEventListener('click', function() {
                    const searchInput = document.querySelector('.search-bar input');
                    if (searchInput && searchInput.value.trim() === '') {
                        // Add a subtle shake animation
                        searchInput.style.animation = 'shake 0.5s';
                        setTimeout(() => {
                            searchInput.style.animation = '';
                        }, 500);
                    } else {
                        // In a real application, this would trigger a search API call
                        console.log('Searching for:', searchInput.value);
                    }
                });
            }

            // Initialize syntax highlighting
            hljs.highlightAll();
            
            // Add CSS for shake animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-8px); }
                    40%, 80% { transform: translateX(8px); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>