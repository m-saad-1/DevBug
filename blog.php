<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Placeholder data for blog posts. In a real application, this would come from a database table for articles.
$posts = [
    [
        'id' => 1,
        'title' => '10 Tips for More Effective Debugging',
        'excerpt' => 'Tired of spending hours chasing down elusive bugs? Streamline your process and fix issues faster with these ten expert tips for effective debugging.',
        'content' => "Debugging is an art as much as it is a science. Here are ten tips to help you master it:<br><br><strong>1. Understand the Problem:</strong> Before you write a single line of code, make sure you can reproduce the bug consistently and understand what the expected behavior is.<br><br><strong>2. Read the Error Message:</strong> It sounds simple, but developers often skim error messages. Read it carefully. It usually tells you exactly where the problem is.<br><br><strong>3. Use a Debugger:</strong> Step through your code line by line. A debugger is your most powerful tool for understanding the state of your application at any given moment.<br><br><strong>4. Isolate the Problem:</strong> Comment out code, create a minimal reproducible example, or use binary searching on your commits to pinpoint where the bug was introduced.<br><br><strong>5. Talk It Out (Rubber Duck Debugging):</strong> Explain the problem to a colleague or even a rubber duck. The act of verbalizing the issue often reveals the solution.<br><br><strong>6. Check Your Assumptions:</strong> Don't assume a variable holds a certain value or a function returns what you expect. Verify everything.<br><br><strong>7. Take a Break:</strong> Staring at the same problem for hours can lead to tunnel vision. Step away, take a walk, and come back with a fresh perspective.<br><br><strong>8. Use Version Control:</strong> Use `git bisect` to quickly find the commit that introduced the bug.<br><br><strong>9. Write Tests:</strong> A good test suite can prevent regressions and help you quickly identify what broke when a new bug appears.<br><br><strong>10. Keep It Simple:</strong> Often, the simplest solution is the best one. Don't over-engineer your fixes.",
        'image_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60',
        'tags' => ['Debugging', 'Productivity'],
        'author_name' => 'Alex Johnson',
        'author_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-28',
        'read_time' => 7,
    ],
    [
        'id' => 2,
        'title' => 'Understanding Asynchronous JavaScript: Callbacks, Promises, and Async/Await',
        'excerpt' => 'Dive deep into the world of asynchronous JavaScript. This guide breaks down callbacks, Promises, and the modern async/await syntax to help you write cleaner, more efficient code.',
        'content' => "Asynchronous JavaScript is a cornerstone of modern web development, allowing you to perform tasks like fetching data from an API without blocking the main thread. Let's break down the evolution of handling async operations.<br><br><h3>Callbacks</h3><p>The original way to handle asynchronous operations. A callback is a function passed into another function as an argument, which is then invoked inside the outer function to complete some kind of routine or action.</p><pre><code>getData(id, function(data) { \n  // work with data\n});</code></pre><p>This can lead to 'Callback Hell' with nested callbacks, making code hard to read and maintain.</p><br><h3>Promises</h3><p>Promises provide a cleaner way to handle asynchronous results. A Promise is an object representing the eventual completion or failure of an asynchronous operation. It can be in one of three states: pending, fulfilled, or rejected.</p><pre><code>getData(id)\n  .then(data => processData(data))\n  .then(processedData => displayData(processedData))\n  .catch(error => console.error(error));</code></pre><br><h3>Async/Await</h3><p>Built on top of Promises, async/await provides syntactic sugar that makes asynchronous code look and behave more like synchronous code. This makes it much easier to read and reason about.</p><pre><code>async function fetchData(id) {\n  try {\n    const data = await getData(id);\n    const processedData = await processData(data);\n    displayData(processedData);\n  } catch (error) {\n    console.error(error);\n  }\n}</code></pre>",
        'image_url' => 'https://images.unsplash.com/photo-1627398242454-45a1465c2479?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60',
        'tags' => ['JavaScript', 'Web Dev'],
        'author_name' => 'Sarah Chen',
        'author_avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-22',
        'read_time' => 12,
    ],
    [
        'id' => 3,
        'title' => 'DevBug Platform Update: New Features in Q4 2023',
        'excerpt' => 'We\'ve been hard at work! Check out the latest features and improvements we\'ve rolled out this quarter, including a redesigned dashboard and enhanced search functionality.',
        'content' => "This quarter has been a busy one for the DevBug team! We're excited to announce a host of new features and improvements designed to make your bug-solving experience even better.<br><br><strong>Redesigned Dashboard:</strong> Your new dashboard provides a clearer overview of your activity, including your recent bugs, solutions, and reputation progress.<br><br><strong>Enhanced Search:</strong> We've supercharged our search functionality. You can now filter bugs more effectively by status, priority, and multiple tags.<br><br><strong>Solution Voting:</strong> You can now upvote helpful solutions, helping the best answers rise to the top and rewarding knowledgeable community members.<br><br><strong>What's Next?</strong> We're already working on our Q1 2024 roadmap, which includes real-time notifications, team collaboration features, and a brand new API for developers. Stay tuned!",
        'image_url' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60',
        'tags' => ['Update', 'Platform'],
        'author_name' => 'Marcus Rivera',
        'author_avatar' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=60',
        'publish_date' => '2023-10-15',
        'read_time' => 5,
    ],
    // You can add more placeholder blog posts here
];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .blog-content { padding: 80px 0; }
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        .blog-card {
            background: var(--bg-card);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow);
        }
        .blog-card-image {
            height: 220px;
            background-color: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 2rem;
        }
        .blog-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .blog-card-body {
            padding: 25px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .blog-card-tags {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .blog-card-title {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .blog-card-title a {
            text-decoration: none;
            color: inherit;
        }
        .blog-card-title a:hover {
            color: var(--accent-primary);
        }
        .blog-card-excerpt {
            color: var(--text-secondary);
            line-height: 1.7;
            flex-grow: 1;
            margin-bottom: 20px;
        }
        .blog-card-footer {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            font-size: 0.9rem;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        .author-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent-secondary);
            overflow: hidden;
        }
        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "DevBug Blog";
    $pageSubtitle = "Insights, articles, and updates from the DevBug team.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="blog-content">
            <?php if (empty($posts)): ?>
                <div class="coming-soon">
                    <i class="fas fa-newspaper"></i>
                    <h2>Our Blog is Coming Soon!</h2>
                    <p>We're working hard to bring you insightful articles, tips, and news. Stay tuned!</p>
                </div>
            <?php else: ?>
                <div class="blog-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="blog-card">
                            <div class="blog-card-image">
                                <a href="blog-post.php?id=<?php echo $post['id']; ?>"><img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>"></a>
                            </div>
                            <div class="blog-card-body">
                                <div class="blog-card-tags">
                                    <?php foreach ($post['tags'] as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <h3 class="blog-card-title"><a href="blog-post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                <div class="blog-card-footer">
                                    <div class="author-avatar"><img src="<?php echo htmlspecialchars($post['author_avatar']); ?>" alt="<?php echo htmlspecialchars($post['author_name']); ?>"></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($post['author_name']); ?></strong><br>
                                        <span><?php echo date('M j, Y', strtotime($post['publish_date'])); ?> &bull; <?php echo $post['read_time']; ?> min read</span>
                                    </div>
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