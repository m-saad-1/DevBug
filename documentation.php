<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation - DevBug</title>
    <link rel="stylesheet" href="/devbug/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
        /* Reusing styles from api-documentation.php for consistency */
        .docs-content { padding: 80px 0; max-width: 1200px; margin: 0 auto; }
        .docs-layout { display: grid; grid-template-columns: 300px 1fr; gap: 60px; align-items: flex-start; }
        .docs-sidebar { position: sticky; top: 100px; height: calc(100vh - 120px); overflow-y: auto; }
        .docs-sidebar::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, and Opera */
        }
        .docs-sidebar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .docs-nav { background: var(--bg-card); border-radius: 16px; padding: 30px; border: 1px solid var(--border); }
        .docs-nav h3 { font-size: 1.2rem; margin-bottom: 20px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .nav-section { margin-bottom: 25px; }
        .nav-section h4 { font-size: 1rem; margin-bottom: 15px; color: var(--accent-primary); font-weight: 600; }
        .nav-links { list-style: none; padding: 0; margin: 0; }
        .nav-links li { margin-bottom: 8px; }
        .nav-links a { color: var(--text-secondary); text-decoration: none; padding: 8px 12px; border-radius: 6px; display: block; transition: var(--transition); font-size: 0.95rem; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-primary); background: rgba(99, 102, 241, 0.1); }
        .docs-main { min-height: 1000px; }
        .docs-section { margin-bottom: 60px; scroll-margin-top: 100px; }
        .docs-section h2 { font-size: 2.2rem; margin-bottom: 25px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .docs-section h3 { font-size: 1.5rem; margin: 35px 0 20px; color: var(--text-primary); }
        .docs-section p { color: var(--text-secondary); line-height: 1.8; margin-bottom: 20px; font-size: 1.05rem; }
        .docs-section a {
            color: var(--accent-primary);
            text-decoration: none;
        }
        .docs-section a:hover { text-decoration: underline; }
        .code-block { background: rgba(0, 0, 0, 0.3); border-radius: 8px; padding: 25px; margin: 25px 0; overflow-x: auto; border: 1px solid var(--border); font-family: 'Fira Code', monospace; font-size: 0.9rem; line-height: 1.5; }
        .info-box { background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--accent-primary); padding: 25px; border-radius: 8px; margin: 30px 0; }
        .info-box h4 { color: var(--accent-primary); margin-bottom: 10px; font-size: 1.2rem; }
        .info-box p { margin-bottom: 0; color: var(--text-secondary); }

        @media (max-width: 968px) {
            .docs-layout { grid-template-columns: 1fr; gap: 40px; }
            .docs-sidebar { position: static; height: auto; }
        }
        @media (max-width: 768px) {
            .docs-section h2 { font-size: 1.8rem; }
            .docs-section h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Documentation";
    $pageSubtitle = "Find guides, resources, and tutorials to help you use DevBug.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="docs-content">
            <div class="docs-layout">
                <!-- Sidebar Navigation -->
                <aside class="docs-sidebar">
                    <div class="docs-nav">
                        <h3>Documentation</h3>
                        
                        <div class="nav-section">
                            <h4>Getting Started</h4>
                            <ul class="nav-links">
                                <li><a href="#introduction" class="active">Introduction</a></li>
                                <li><a href="#account-setup">Account Setup</a></li>
                            </ul>
                        </div>

                        <div class="nav-section">
                            <h4>User Guide</h4>
                            <ul class="nav-links">
                                <li><a href="#reporting-bugs">Reporting Bugs</a></li>
                                <li><a href="#providing-solutions">Providing Solutions</a></li>
                                <li><a href="#reputation-system">Reputation System</a></li>
                            </ul>
                        </div>

                        <div class="nav-section">
                            <h4>Developer Guide</h4>
                            <ul class="nav-links">
                                <li><a href="/devbug/api-documentation.php">API Reference</a></li>
                                <li><a href="#integrations">Integrations</a></li>
                            </ul>
                        </div>
                    </div>
                </aside>

                <!-- Main Documentation Content -->
                <div class="docs-main">
                    <!-- Introduction Section -->
                    <section id="introduction" class="docs-section">
                        <h2>Introduction</h2>
                        <p>Welcome to the DevBug documentation. This is your central hub for learning how to use our platform effectively, whether you're a new user getting started or an experienced developer looking to integrate with our services.</p>
                        <div class="info-box">
                            <h4>Need Help?</h4>
                            <p>If you can't find what you're looking for, feel free to <a href="contact.php">contact our support team</a> or ask the community in a new bug post.</p>
                        </div>
                    </section>

                    <!-- Account Setup Section -->
                    <section id="account-setup" class="docs-section">
                        <h2>Account Setup</h2>
                        <p>Creating an account is the first step to becoming part of the DevBug community. With an account, you can report bugs, post solutions, comment, and earn reputation.</p>
                        <h3>Creating Your Account</h3>
                        <p>To create an account, click the "Sign Up" button on the homepage. You'll need to provide a name, a unique username, a valid email address, and a password. After registering, you'll be prompted to complete your profile, which helps other users get to know you.</p>
                    </section>

                    <!-- Reporting Bugs Section -->
                    <section id="reporting-bugs" class="docs-section">
                        <h2>Reporting Bugs</h2>
                        <p>A well-written bug report is crucial for getting a quick and accurate solution. When you report a bug, please include:</p>
                        <ul>
                            <li>A clear and descriptive title.</li>
                            <li>A detailed description of the problem, including what you expected to happen and what actually happened.</li>
                            <li>Steps to reproduce the bug.</li>
                            <li>Relevant code snippets, formatted correctly.</li>
                            <li>Any error messages you received.</li>
                            <li>Tags for the technologies involved (e.g., JavaScript, PHP, React).</li>
                        </ul>
                    </section>

                    <!-- Providing Solutions Section -->
                    <section id="providing-solutions" class="docs-section">
                        <h2>Providing Solutions</h2>
                        <p>Sharing your knowledge by providing solutions is a great way to help others and build your reputation. A good solution should:</p>
                        <ul>
                            <li>Directly address the problem described in the bug report.</li>
                            <li>Include a clear explanation of why the solution works.</li>
                            <li>Provide a corrected code snippet if applicable.</li>
                            <li>Be respectful and constructive.</li>
                        </ul>
                    </section>

                     <!-- Reputation System Section -->
                    <section id="reputation-system" class="docs-section">
                        <h2>Reputation System</h2>
                        <p>Our reputation system is designed to recognize and reward helpful contributions to the community. You can earn reputation points for various activities, such as:</p>
                        <ul>
                            <li><strong>+15 points:</strong> Your solution is approved by the bug reporter.</li>
                            <li><strong>+10 points:</strong> Your solution receives an upvote.</li>
                            <li><strong>+5 points:</strong> You report a new bug.</li>
                            <li><strong>-2 points:</strong> Your solution receives a downvote.</li>
                        </ul>
                        <p>Earning reputation unlocks new privileges and helps you climb the <a href="leaderboard.php">leaderboard</a>.</p>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            hljs.highlightAll();

            const navLinks = document.querySelectorAll('.nav-links a');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        e.preventDefault();
                        navLinks.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                        const targetId = this.getAttribute('href');
                        const targetSection = document.querySelector(targetId);
                        if (targetSection) {
                            window.scrollTo({ top: targetSection.offsetTop - 100, behavior: 'smooth' });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>