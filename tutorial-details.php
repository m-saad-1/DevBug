<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Placeholder data for tutorials. This should match tutorials.php
$tutorials = [
    [
        'id' => 1,
        'title' => 'Mastering CSS Flexbox: A Comprehensive Guide',
        'excerpt' => 'Learn how to build complex, responsive layouts with ease using CSS Flexbox. This guide covers all the essential properties and provides practical examples.',
        'content' => "CSS Flexbox is a one-dimensional layout model that offers an efficient way to lay out, align, and distribute space among items in a container, even when their size is unknown or dynamic.<br><br><h3>The Two Axes</h3><p>The main idea behind flexbox is the concept of a main axis and a cross axis. The main axis is defined by the `flex-direction` property, and the cross axis is perpendicular to it.</p><br><h3>Key Properties for the Container</h3><ul><li><strong>display: flex;</strong> - This enables flex context for all direct children.</li><li><strong>flex-direction:</strong> - This establishes the main-axis, thus defining the direction flex items are placed in the flex container. (row, row-reverse, column, column-reverse)</li><li><strong>justify-content:</strong> - This defines the alignment along the main axis.</li><li><strong>align-items:</strong> - This defines the default behavior for how flex items are laid out along the cross axis.</li></ul><br><h3>Example</h3><pre><code>.container {\n  display: flex;\n  justify-content: space-between;\n  align-items: center;\n}</code></pre><p>This simple example will evenly space the items in the container and vertically center them. It's a powerful tool for creating navigation bars, grids, and more.</p>",
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
        'content' => "Building a REST API is a fundamental skill for backend developers. This tutorial will guide you through creating a simple API using Node.js and the Express framework.<br><br><h3>Prerequisites</h3><ul><li>Node.js and npm installed.</li><li>Basic understanding of JavaScript.</li><li>A code editor like VS Code.</li></ul><br><h3>Step 1: Setup Project</h3><p>Initialize a new Node.js project and install Express:</p><pre><code>mkdir my-api\ncd my-api\nnpm init -y\nnpm install express</code></pre><br><h3>Step 2: Create the Server</h3><p>Create a file named `index.js` and set up a basic Express server:</p><pre><code>const express = require('express');\nconst app = express();\nconst port = 3000;\n\napp.get('/', (req, res) => {\n  res.send('Hello World!');\n});\n\napp.listen(port, () => {\n  console.log(`Example app listening at http://localhost:\${port}`);\n});</code></pre><p>This sets up a basic server that responds to GET requests on the root URL. From here, you can add more routes, connect to a database, and build out your API logic.</p>",
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
        'content' => "SQL (Structured Query Language) is essential for anyone working with data. It's the language used to communicate with relational databases.<br><br><h3>Basic SELECT Statement</h3><p>The `SELECT` statement is used to query the database and retrieve data that matches criteria that you specify.</p><pre><code>SELECT column1, column2 FROM table_name;</code></pre><br><h3>Filtering with WHERE</h3><p>The `WHERE` clause is used to filter records and extract only those that fulfill a specified condition.</p><pre><code>SELECT * FROM products WHERE price > 50;</code></pre><br><h3>Joining Tables</h3><p>The `JOIN` clause is used to combine rows from two or more tables, based on a related column between them.</p><pre><code>SELECT orders.order_id, customers.customer_name\nFROM orders\nINNER JOIN customers ON orders.customer_id = customers.customer_id;</code></pre><p>Mastering these basic concepts is the first step to becoming proficient in SQL.</p>",
        'author_name' => 'Emily White',
        'author_avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-18',
        'level' => 'Beginner',
        'duration' => '30 min',
        'tags' => ['SQL', 'Database', 'Backend'],
        'icon' => 'fas fa-database'
    ],
];

$tutorial_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tutorial = null;

foreach ($tutorials as $t) {
    if ($t['id'] === $tutorial_id) {
        $tutorial = $t;
        break;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tutorial ? htmlspecialchars($tutorial['title']) : 'Tutorial Not Found'; ?> - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing styles from blog-post.php for consistency */
        .post-container { padding: 80px 0; max-width: 900px; margin: 0 auto; }
        .post-header { text-align: center; margin-bottom: 40px; }
        .post-title { font-size: 2.8rem; color: var(--text-primary); margin-bottom: 20px; line-height: 1.3; }
        .post-meta { display: flex; justify-content: center; align-items: center; gap: 20px; color: var(--text-muted); font-size: 0.9rem; flex-wrap: wrap; }
        .author-info { display: flex; align-items: center; gap: 10px; }
        .author-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; }
        .author-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .post-content { color: var(--text-secondary); line-height: 1.8; font-size: 1.1rem; }
        .post-content h3 { font-size: 1.6rem; color: var(--text-primary); margin: 40px 0 20px; }
        .post-content p { margin-bottom: 20px; }
        .post-content ul { list-style: disc; padding-left: 25px; margin-bottom: 20px; }
        .post-content pre { background: var(--bg-secondary); padding: 20px; border-radius: 8px; overflow-x: auto; font-family: 'Fira Code', monospace; border: 1px solid var(--border); }
        .post-tags { margin-top: 40px; display: flex; gap: 10px; flex-wrap: wrap; }
        .tag { background: rgba(99, 102, 241, 0.15); color: var(--accent-primary); padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .not-found { text-align: center; padding: 100px 20px; }
        .not-found i { font-size: 4rem; color: var(--accent-primary); margin-bottom: 25px; }
        .not-found h2 { font-size: 2.5rem; color: var(--text-primary); margin-bottom: 15px; }
        .not-found p { color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 30px; }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <main class="container">
        <div class="post-container">
            <?php if ($tutorial): ?>
                <article>
                    <header class="post-header">
                        <h1 class="post-title"><?php echo htmlspecialchars($tutorial['title']); ?></h1>
                        <div class="post-meta">
                            <div class="author-info">
                                <div class="author-avatar"><img src="<?php echo htmlspecialchars($tutorial['author_avatar']); ?>" alt="<?php echo htmlspecialchars($tutorial['author_name']); ?>"></div>
                                <span>By <strong><?php echo htmlspecialchars($tutorial['author_name']); ?></strong></span>
                            </div>
                            <span>&bull;</span>
                            <span><?php echo date('M j, Y', strtotime($tutorial['publish_date'])); ?></span>
                            <span>&bull;</span>
                            <span><?php echo htmlspecialchars($tutorial['level']); ?></span>
                            <span>&bull;</span>
                            <span><?php echo htmlspecialchars($tutorial['duration']); ?> read</span>
                        </div>
                    </header>

                    <div class="post-content">
                        <?php echo $tutorial['content']; // Content is assumed to be safe HTML for this placeholder ?>
                    </div>

                    <div class="post-tags">
                        <?php foreach ($tutorial['tags'] as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php else: ?>
                <div class="not-found">
                    <i class="fas fa-graduation-cap"></i>
                    <h2>Tutorial Not Found</h2>
                    <p>Sorry, the tutorial you are looking for does not exist or has been moved.</p>
                    <a href="/devbug/tutorials.php" class="btn btn-primary">Back to Tutorials</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>
</body>
</html>