<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - DevBug</title>
    <link rel="stylesheet" href="/devbug/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing styles from contact.php for consistency */
        .faq-content { padding: 80px 0; }
        .faq-grid { display: grid; grid-template-columns: 1fr; gap: 15px; max-width: 900px; margin: 0 auto; }
        .faq-item { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; transition: var(--transition); }
        .faq-item.active { border-color: var(--accent-primary); }
        .faq-question { padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: var(--transition); }
        .faq-question h4 { font-size: 1.2rem; color: var(--text-primary); margin: 0; }
        .faq-question i { color: var(--accent-primary); transition: transform 0.3s ease; }
        .faq-item.active .faq-question i { transform: rotate(180deg); }
        .faq-answer { padding: 0 30px; max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; }
        .faq-item.active .faq-answer { padding: 0 30px 25px; max-height: 500px; }
        .faq-answer p { color: var(--text-secondary); line-height: 1.7; margin: 0; }
        .faq-answer a { color: var(--accent-primary); text-decoration: none; }
        .faq-answer a:hover { text-decoration: underline; }
        .cta-section { text-align: center; padding: 80px 0; background: var(--bg-secondary); border-radius: 16px; margin: 80px 0; border: 1px solid var(--border); }
        .cta-section h2 { font-size: 2.5rem; margin-bottom: 20px; }
        .cta-section p { color: var(--text-secondary); font-size: 1.2rem; max-width: 600px; margin: 0 auto 40px; }

        @media (max-width: 768px) {
            .faq-question { padding: 20px; }
            .faq-question h4 { font-size: 1.1rem; }
            .faq-answer { padding: 0 20px; }
            .faq-item.active .faq-answer { padding: 0 20px 20px; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Frequently Asked Questions";
    $pageSubtitle = "Find quick answers to common questions about DevBug.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="faq-content">
            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">
                        <h4>How do I report a bug effectively?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>To report a bug effectively, provide a clear title, a detailed description of the problem, steps to reproduce it, what you expected to happen, and what actually happened. Including code snippets, error messages, and screenshots is highly recommended. Check out our <a href="community-guidelines.php">Community Guidelines</a> for more tips.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Is DevBug free to use?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, DevBug is completely free for individual developers. Our mission is to make collaborative problem-solving accessible to everyone. We may introduce premium features for teams in the future, but the core functionality will always remain free.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>How does the reputation system work?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You earn reputation points for positive contributions to the community, such as when your bug report or solution is upvoted, or when a solution you provided is marked as "approved". Losing points can happen if your content is downvoted. Higher reputation unlocks new privileges on the site. You can learn more on our <a href="reputation-system.php">Reputation System</a> page.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>What programming languages are supported?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>DevBug is language-agnostic! You can ask for help with any programming language, framework, or technology. Our community includes experts in JavaScript, Python, Java, C#, PHP, Ruby, Go, Rust, and many more. Just be sure to tag your bug report correctly so the right people can find it.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>How do I format my code snippets?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You can format code by wrapping it in triple backticks (```). For syntax highlighting, you can add the language name after the backticks, for example: ```javascript. This makes your code much easier to read and understand.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Can I edit my bug report or solution after posting?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, you can edit your own posts. Look for the "Edit" button on your bug report or solution. This is useful for adding more details, correcting mistakes, or providing updates on your progress.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>How do I delete my account?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You can delete your account from the "Settings" tab in your dashboard. Please be aware that this action is permanent and cannot be undone. All of your contributions will be anonymized.</p>
                    </div>
                </div>
            </div>

            <section class="cta-section">
                <h2>Still Have Questions?</h2>
                <p>If you can't find the answer you're looking for, feel free to reach out to our support team or ask the community.</p>
                <a href="contact.php" class="btn btn-primary">Contact Us</a>
            </section>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', function() {
                    const wasActive = item.classList.contains('active');
                    
                    // Optional: Close all other items
                    // faqItems.forEach(otherItem => {
                    //     otherItem.classList.remove('active');
                    // });
                    
                    if (!wasActive) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            });
        });
    </script>
</body>
</html>