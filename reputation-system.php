<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reputation System - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .reputation-content { padding: 80px 0; }
        .section { margin-bottom: 80px; }
        .section-title { text-align: center; margin-bottom: 60px; }
        .section-title h2 { font-size: 2.5rem; margin-bottom: 20px; color: var(--text-primary); position: relative; display: inline-block; }
        .section-title h2::after { content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary)); border-radius: 2px; }
        .section-title p { color: var(--text-secondary); font-size: 1.2rem; max-width: 700px; margin: 0 auto; }

        /* How it works */
        .how-it-works-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .work-card { background: var(--bg-card); border-radius: 16px; padding: 40px 30px; text-align: center; border: 1px solid var(--border); transition: var(--transition); }
        .work-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); border-color: var(--accent-primary); }
        .work-card i { font-size: 3rem; margin-bottom: 25px; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .work-card h3 { font-size: 1.5rem; margin-bottom: 15px; color: var(--text-primary); }
        .work-card p { color: var(--text-secondary); line-height: 1.7; }

        /* Earning Reputation */
        .reputation-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .reputation-table th, .reputation-table td { padding: 20px; text-align: left; border-bottom: 1px solid var(--border); }
        .reputation-table th { background: var(--bg-secondary); color: var(--text-primary); font-size: 1.1rem; }
        .reputation-table td { color: var(--text-secondary); }
        .rep-points { font-weight: 700; font-size: 1.2rem; color: var(--success); font-family: 'Fira Code', monospace; }
        .rep-points.negative { color: var(--danger); }

        /* Ranks Section */
        .ranks-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; }
        .rank-card { background: var(--bg-card); border-radius: 16px; padding: 30px; border: 1px solid var(--border); text-align: center; transition: var(--transition); }
        .rank-card:hover { transform: translateY(-5px); border-color: var(--accent-primary); }
        .rank-icon { font-size: 2.5rem; margin-bottom: 20px; }
        .rank-beginner { color: #9ca3af; }
        .rank-intermediate { color: #3b82f6; }
        .rank-advanced { color: #8b5cf6; }
        .rank-expert { color: #ec4899; }
        .rank-master { color: #f59e0b; }
        .rank-grandmaster { color: #ef4444; }
        .rank-card h3 { font-size: 1.4rem; margin-bottom: 10px; color: var(--text-primary); }
        .rank-points { font-size: 1rem; color: var(--text-muted); font-family: 'Fira Code', monospace; }

        /* CTA Section */
        .cta-section { text-align: center; padding: 80px 0; background: var(--bg-secondary); border-radius: 16px; margin: 80px 0; border: 1px solid var(--border); }
        .cta-section h2 { font-size: 2.5rem; margin-bottom: 20px; }
        .cta-section p { color: var(--text-secondary); font-size: 1.2rem; max-width: 600px; margin: 0 auto 40px; }

        @media (max-width: 768px) {
            .section-title h2 { font-size: 2rem; }
            .reputation-table th, .reputation-table td { padding: 15px; }
            .rep-points { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Reputation System";
    $pageSubtitle = "Earn recognition and unlock new privileges by contributing to the DevBug community.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="reputation-content">
            <!-- How It Works Section -->
            <section class="section">
                <div class="section-title">
                    <h2>What is Reputation?</h2>
                    <p>Reputation is a measure of your contributions and the community's trust in you. It's earned by performing helpful actions on the site and is a key part of how our community governs itself.</p>
                </div>
                <div class="how-it-works-grid">
                    <div class="work-card">
                        <i class="fas fa-question-circle"></i>
                        <h3>Ask Good Questions</h3>
                        <p>Well-researched and clear bug reports attract helpful solutions and upvotes from the community.</p>
                    </div>
                    <div class="work-card">
                        <i class="fas fa-lightbulb"></i>
                        <h3>Provide Quality Solutions</h3>
                        <p>Sharing accurate and well-explained solutions is the best way to earn reputation and help others.</p>
                    </div>
                    <div class="work-card">
                        <i class="fas fa-users"></i>
                        <h3>Build Trust</h3>
                        <p>Higher reputation unlocks new privileges, showing that the community trusts your expertise and judgment.</p>
                    </div>
                </div>
            </section>

            <!-- Earning Reputation Section -->
            <section class="section">
                <div class="section-title">
                    <h2>How to Earn Reputation</h2>
                    <p>You gain (or lose) reputation based on the quality of your contributions.</p>
                </div>
                <table class="reputation-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Reputation Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Your solution is approved</td>
                            <td><span class="rep-points">+15</span></td>
                        </tr>
                        <tr>
                            <td>Your bug report is upvoted</td>
                            <td><span class="rep-points">+10</span></td>
                        </tr>
                        <tr>
                            <td>Your solution is upvoted</td>
                            <td><span class="rep-points">+10</span></td>
                        </tr>
                        <tr>
                            <td>You report a bug</td>
                            <td><span class="rep-points">+5</span></td>
                        </tr>
                        <tr>
                            <td>Your bug report is downvoted</td>
                            <td><span class="rep-points negative">-2</span></td>
                        </tr>
                        <tr>
                            <td>Your solution is downvoted</td>
                            <td><span class="rep-points negative">-2</span></td>
                        </tr>
                        <tr>
                            <td>You downvote a bug or solution</td>
                            <td><span class="rep-points negative">-1</span></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Ranks Section -->
            <section class="section">
                <div class="section-title">
                    <h2>Reputation Ranks</h2>
                    <p>As you earn reputation, you'll advance through the ranks, unlocking new abilities.</p>
                </div>
                <div class="ranks-grid">
                    <div class="rank-card">
                        <i class="fas fa-seedling rank-icon rank-beginner"></i>
                        <h3>Beginner</h3>
                        <p class="rank-points">0+ rep</p>
                    </div>
                    <div class="rank-card">
                        <i class="fas fa-user-graduate rank-icon rank-intermediate"></i>
                        <h3>Intermediate</h3>
                        <p class="rank-points">100+ rep</p>
                    </div>
                    <div class="rank-card">
                        <i class="fas fa-code rank-icon rank-advanced"></i>
                        <h3>Advanced</h3>
                        <p class="rank-points">500+ rep</p>
                    </div>
                    <div class="rank-card">
                        <i class="fas fa-star rank-icon rank-expert"></i>
                        <h3>Expert</h3>
                        <p class="rank-points">1,000+ rep</p>
                    </div>
                    <div class="rank-card">
                        <i class="fas fa-trophy rank-icon rank-master"></i>
                        <h3>Master</h3>
                        <p class="rank-points">2,500+ rep</p>
                    </div>
                    <div class="rank-card">
                        <i class="fas fa-crown rank-icon rank-grandmaster"></i>
                        <h3>Grand Master</h3>
                        <p class="rank-points">5,000+ rep</p>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="cta-section">
                <h2>Ready to Start Contributing?</h2>
                <p>The best way to gain reputation is to start helping others. Browse open bugs and share your knowledge with the community.</p>
                <a href="bug-post.php" class="btn btn-primary">Find a Bug to Solve</a>
            </section>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>
</body>
</html>