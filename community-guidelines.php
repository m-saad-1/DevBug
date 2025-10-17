<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Guidelines - DevBug</title>
    <link rel="stylesheet" href="/devbug/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing styles from terms-services.php for consistency */
        .guidelines-content { padding: 80px 0; max-width: 1200px; margin: 0 auto; }
        .guidelines-layout { display: grid; grid-template-columns: 300px 1fr; gap: 60px; align-items: flex-start; }
        .guidelines-nav { position: sticky; top: 100px; background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border); }
        .guidelines-nav h3 { margin-bottom: 20px; color: var(--text-primary); font-size: 1.3rem; }
        .guidelines-nav ul { list-style: none; padding: 0; margin: 0; }
        .guidelines-nav li { margin-bottom: 12px; }
        .guidelines-nav a { color: var(--text-secondary); text-decoration: none; transition: var(--transition); display: block; padding: 8px 12px; border-radius: 6px; }
        .guidelines-nav a:hover, .guidelines-nav a.active { color: var(--accent-primary); background: rgba(99, 102, 241, 0.1); }
        .guidelines-section { margin-bottom: 60px; scroll-margin-top: 120px; }
        .guidelines-section h2 { font-size: 2.2rem; margin-bottom: 25px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .guidelines-section h3 { font-size: 1.5rem; margin: 35px 0 20px; color: var(--text-primary); }
        .guidelines-section p { color: var(--text-secondary); line-height: 1.8; margin-bottom: 20px; font-size: 1.05rem; }
        .guidelines-section ul { color: var(--text-secondary); line-height: 1.8; margin-bottom: 25px; padding-left: 25px; }
        .guidelines-section li { margin-bottom: 10px; }
        .guideline-box { background: var(--bg-secondary); border-radius: 12px; padding: 30px; margin: 30px 0; border: 1px solid var(--border); }
        .guideline-box h4 { font-size: 1.3rem; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .guideline-box.positive h4 { color: var(--success); }
        .guideline-box.negative h4 { color: var(--danger); }
        .guideline-box p { margin-bottom: 0; }

        @media (max-width: 968px) {
            .guidelines-layout { grid-template-columns: 1fr; }
            .guidelines-nav { position: static; margin-bottom: 40px; }
        }
        @media (max-width: 768px) {
            .guidelines-section h2 { font-size: 1.8rem; }
            .guidelines-section h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Community Guidelines";
    $pageSubtitle = "Fostering a positive, respectful, and collaborative environment for all developers.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="guidelines-content">
            <div class="guidelines-layout">
                <!-- Navigation -->
                <aside class="guidelines-nav">
                    <h3>Guidelines</h3>
                    <ul>
                        <li><a href="#introduction" class="active">Introduction</a></li>
                        <li><a href="#be-respectful">Be Respectful</a></li>
                        <li><a href="#be-constructive">Be Constructive</a></li>
                        <li><a href="#stay-on-topic">Stay On-Topic</a></li>
                        <li><a href="#no-spam">No Spam or Self-Promotion</a></li>
                        <li><a href="#reporting">Reporting Violations</a></li>
                        <li><a href="#enforcement">Enforcement</a></li>
                    </ul>
                </aside>

                <!-- Guideline Sections -->
                <div class="guidelines-main">
                    <section id="introduction" class="guidelines-section">
                        <h2>Introduction</h2>
                        <p>Welcome to the DevBug community! Our mission is to create a supportive and inclusive space where developers can learn, share, and grow together. These guidelines are designed to ensure that our community remains a welcoming place for everyone. By participating, you agree to abide by these rules.</p>
                    </section>

                    <section id="be-respectful" class="guidelines-section">
                        <h2>Be Respectful and Inclusive</h2>
                        <p>Treat everyone with respect. We are a diverse community of people with different backgrounds and experience levels. Healthy debates are encouraged, but kindness is required.</p>
                        <div class="guideline-box positive">
                            <h4><i class="fas fa-check-circle"></i> Do</h4>
                            <p>Be friendly, patient, and welcoming. Assume good intent and give others the benefit of the doubt. Respect that people have different opinions.</p>
                        </div>
                        <div class="guideline-box negative">
                            <h4><i class="fas fa-times-circle"></i> Don't</h4>
                            <p>Engage in personal attacks, harassment, trolling, or any form of discrimination. Do not post inflammatory, offensive, or insulting content.</p>
                        </div>
                    </section>

                    <section id="be-constructive" class="guidelines-section">
                        <h2>Be Constructive</h2>
                        <p>Whether you're asking a question or providing a solution, aim to be constructive. Your contributions should help move the conversation forward and contribute to a positive learning environment.</p>
                        <div class="guideline-box positive">
                            <h4><i class="fas fa-check-circle"></i> Do</h4>
                            <p>Provide clear, detailed bug reports. When offering a solution, explain why it works. If you disagree with a solution, provide a constructive alternative.</p>
                        </div>
                        <div class="guideline-box negative">
                            <h4><i class="fas fa-times-circle"></i> Don't</h4>
                            <p>Post low-effort comments like "+1" or "this doesn't work" without explanation. Don't be dismissive of others' questions, even if they seem simple.</p>
                        </div>
                    </section>

                    <section id="stay-on-topic" class="guidelines-section">
                        <h2>Stay On-Topic</h2>
                        <p>Keep discussions focused on the bug report or solution at hand. While some friendly chatter is fine, please avoid derailing threads with off-topic conversations.</p>
                        <ul>
                            <li>Comments should be used to ask for clarification or suggest improvements to the original post.</li>
                            <li>Solutions should directly address the problem described in the bug report.</li>
                            <li>For broader discussions, consider using our community forum or Discord server.</li>
                        </ul>
                    </section>

                    <section id="no-spam" class="guidelines-section">
                        <h2>No Spam or Self-Promotion</h2>
                        <p>This community is for learning and collaboration, not for advertising. Overt self-promotion and spam are not allowed and will be removed.</p>
                        <div class="guideline-box negative">
                            <h4><i class="fas fa-times-circle"></i> Don't</h4>
                            <p>Post promotional links to your products or services. Do not post the same content repeatedly. Linking to your own blog post is acceptable only if it is directly relevant and provides a solution to the question.</p>
                        </div>
                    </section>

                    <section id="reporting" class="guidelines-section">
                        <h2>Reporting Violations</h2>
                        <p>If you see a comment, bug report, or solution that violates these guidelines, please report it to our moderation team using the "Report" button. This helps us maintain a healthy community.</p>
                        <p>Do not engage in public arguments with users who violate the guidelines. Simply report the content and let our moderators handle it.</p>
                    </section>

                    <section id="enforcement" class="guidelines-section">
                        <h2>Enforcement</h2>
                        <p>Our moderation team will review all reported content. Violations of these guidelines may result in:</p>
                        <ul>
                            <li>A warning from a moderator.</li>
                            <li>Removal of the offending content.</li>
                            <li>Temporary suspension of your account.</li>
                            <li>Permanent ban from the platform for repeated or severe violations.</li>
                        </ul>
                        <p>We aim to be fair and transparent in our moderation process. If you believe a moderation action was made in error, you can appeal by contacting our support team.</p>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for guidelines navigation
            const navLinks = document.querySelectorAll('.guidelines-nav a');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        window.scrollTo({
                            top: targetSection.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Update active link on scroll
            const sections = document.querySelectorAll('.guidelines-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, { 
                threshold: 0.5,
                rootMargin: '-100px 0px -50% 0px'
            });
            
            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>