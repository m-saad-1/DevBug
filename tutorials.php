<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Placeholder data for tutorials. In a real application, this would come from a database.
$tutorials = [
    [
        'id' => 1,
        'title' => 'Mastering CSS Flexbox: A Comprehensive Guide',
        'excerpt' => 'Learn how to build complex, responsive layouts with ease using CSS Flexbox. This guide covers all the essential properties and provides practical examples.',
        'content' => "...", // Full content is in tutorial-details.php
        'author_name' => 'Jane Smith',
        'author_avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-11-01',
        'level' => 'Beginner',
        'duration' => '25 min',
        'tags' => ['CSS', 'Frontend', 'Layout'],
        'icon' => 'fab fa-css3-alt'
    ],
    [
        'id' => 2,
        'title' => 'Building a REST API with Node.js and Express',
        'excerpt' => 'A step-by-step tutorial on creating a robust RESTful API from scratch using Node.js, Express, and connecting to a database.',
        'content' => '...',
        'author_name' => 'John Doe',
        'author_avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-25',
        'level' => 'Intermediate',
        'duration' => '45 min',
        'tags' => ['Node.js', 'Backend', 'API'],
        'icon' => 'fab fa-node-js'
    ],
    [
        'id' => 3,
        'title' => 'Introduction to SQL: From Queries to Joins',
        'excerpt' => 'Get started with SQL, the standard language for relational databases. Learn to select, insert, update, and join data with practical examples.',
        'content' => '...',
        'author_name' => 'Emily White',
        'author_avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-18',
        'level' => 'Beginner',
        'duration' => '30 min',
        'tags' => ['SQL', 'Database', 'Backend'],
        'icon' => 'fas fa-database'
    ],
    // Add more tutorials here
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutorials - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tutorials-content {
            padding: 80px 0;
        }
        .tutorial-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .tutorial-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            transition: var(--transition);
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        .tutorial-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow);
            border-color: var(--accent-primary);
        }
        .tutorial-icon {
            font-size: 2.5rem;
            color: var(--accent-primary);
            background: rgba(99, 102, 241, 0.1);
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .tutorial-body {
            flex-grow: 1;
        }
        .tutorial-title {
            font-size: 1.4rem;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .tutorial-title a {
            text-decoration: none;
            color: inherit;
        }
        .tutorial-title a:hover {
            color: var(--accent-primary);
        }
        .tutorial-excerpt {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .tutorial-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: var(--text-muted);
            align-items: center;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tag {
            background: rgba(99, 102, 241, 0.15); color: var(--accent-primary); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500;
        }
        .coming-soon {
            text-align: center;
            padding: 100px 20px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
        }
        .coming-soon i {
            font-size: 4rem;
            color: var(--accent-primary);
            margin-bottom: 25px;
        }
        .coming-soon h2 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        .coming-soon p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Tutorials";
    $pageSubtitle = "Step-by-step guides to help you master new technologies and solve complex problems.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="tutorials-content">
            <?php if (empty($tutorials)): ?>
                <div class="coming-soon">
                    <i class="fas fa-graduation-cap"></i>
                    <h2>Tutorials Are On The Way!</h2>
                    <p>We are preparing a library of high-quality tutorials to help you learn and grow as a developer. Check back soon for guides on everything from basic concepts to advanced techniques.</p>
                    <a href="/devbug/bug-post.php" class="btn btn-primary">Browse Bugs in the Meantime</a>
                </div>
            <?php else: ?>
                <div class="tutorial-list">
                    <?php foreach ($tutorials as $tutorial): ?>
                        <div class="tutorial-card">
                            <div class="tutorial-icon">
                                <i class="<?php echo htmlspecialchars($tutorial['icon']); ?>"></i>
                            </div>
                            <div class="tutorial-body">
                                <h3 class="tutorial-title">
                                    <a href="tutorial-details.php?id=<?php echo $tutorial['id']; ?>">
                                        <?php echo htmlspecialchars($tutorial['title']); ?>
                                    </a>
                                </h3>
                                <p class="tutorial-excerpt"><?php echo htmlspecialchars($tutorial['excerpt']); ?></p>
                                <div class="tutorial-meta">
                                    <div class="meta-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($tutorial['author_name']); ?></div>
                                    <div class="meta-item"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($tutorial['level']); ?></div>
                                    <div class="meta-item"><i class="far fa-clock"></i> <?php echo htmlspecialchars($tutorial['duration']); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>
</body>
</html>