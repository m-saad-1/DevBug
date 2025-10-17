<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing styles from privacy-policy.php for consistency */
        .legal-content { padding: 80px 0; max-width: 1200px; margin: 0 auto; }
        .legal-layout { display: grid; grid-template-columns: 300px 1fr; gap: 60px; align-items: flex-start; }
        .legal-nav { position: sticky; top: 100px; background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border); }
        .legal-nav h3 { margin-bottom: 20px; color: var(--text-primary); font-size: 1.3rem; }
        .legal-nav ul { list-style: none; padding: 0; margin: 0; }
        .legal-nav li { margin-bottom: 12px; }
        .legal-nav a { color: var(--text-secondary); text-decoration: none; transition: var(--transition); display: block; padding: 8px 12px; border-radius: 6px; }
        .legal-nav a:hover, .legal-nav a.active { color: var(--accent-primary); background: rgba(99, 102, 241, 0.1); }
        .legal-section { margin-bottom: 60px; scroll-margin-top: 120px; }
        .legal-section h2 { font-size: 2.2rem; margin-bottom: 25px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .legal-section h3 { font-size: 1.5rem; margin: 35px 0 20px; color: var(--text-primary); }
        .legal-section p, .legal-section ul { color: var(--text-secondary); line-height: 1.8; margin-bottom: 20px; font-size: 1.05rem; }
        .legal-section ul { padding-left: 25px; }
        .legal-section a {
            color: var(--accent-primary);
            text-decoration: none;
        }
        .legal-section a:hover { text-decoration: underline; }
        .legal-section li { margin-bottom: 10px; }

        @media (max-width: 968px) {
            .legal-layout { grid-template-columns: 1fr; }
            .legal-nav { position: static; margin-bottom: 40px; }
        }
        @media (max-width: 768px) {
            .legal-section h2 { font-size: 1.8rem; }
            .legal-section h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Terms of Service";
    $pageSubtitle = "The rules and guidelines for using the DevBug platform.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="legal-content">
            <div class="legal-layout">
                <!-- Navigation -->
                <aside class="legal-nav">
                    <h3>Terms of Service</h3>
                    <ul>
                        <li><a href="#agreement" class="active">1. Agreement to Terms</a></li>
                        <li><a href="#accounts">2. User Accounts</a></li>
                        <li><a href="#content">3. User Content</a></li>
                        <li><a href="#prohibited">4. Prohibited Activities</a></li>
                        <li><a href="#ip">5. Intellectual Property</a></li>
                        <li><a href="#termination">6. Termination</a></li>
                        <li><a href="#disclaimer">7. Disclaimer</a></li>
                        <li><a href="#liability">8. Limitation of Liability</a></li>
                        <li><a href="#contact">9. Contact Us</a></li>
                    </ul>
                </aside>

                <!-- Policy Sections -->
                <div class="legal-main">
                    <p>Last updated: October 29, 2023</p>
                    <section id="agreement" class="legal-section">
                        <h2>1. Agreement to Terms</h2>
                        <p>By using our services, you agree to be bound by these Terms of Service. If you do not agree to these terms, you may not use our services. We may modify these terms at any time, and such modifications will be effective immediately upon posting.</p>
                    </section>

                    <section id="accounts" class="legal-section">
                        <h2>2. User Accounts</h2>
                        <p>To access most features of DevBug, you must register for an account. You agree to provide accurate, current, and complete information during the registration process. You are responsible for safeguarding your password and for all activities that occur under your account.</p>
                    </section>

                    <section id="content" class="legal-section">
                        <h2>3. User Content</h2>
                        <p>You are solely responsible for the content, such as bug reports, solutions, and comments, that you post on the platform ("User Content"). You retain ownership of your User Content, but you grant DevBug a worldwide, non-exclusive, royalty-free license to use, reproduce, and display it in connection with the service.</p>
                        <p>You agree not to post User Content that is illegal, obscene, defamatory, threatening, or otherwise objectionable. We reserve the right to remove any User Content that violates our <a href="community-guidelines.php">Community Guidelines</a>.</p>
                    </section>

                    <section id="prohibited" class="legal-section">
                        <h2>4. Prohibited Activities</h2>
                        <p>You agree not to engage in any of the following prohibited activities:</p>
                        <ul>
                            <li>Using the service for any illegal purpose or in violation of any local, state, national, or international law.</li>
                            <li>Harassing, threatening, or defrauding other users.</li>
                            <li>Interfering with the proper working of the service, including by introducing viruses or other malicious code.</li>
                            <li>Attempting to gain unauthorized access to another user's account.</li>
                        </ul>
                    </section>

                    <section id="ip" class="legal-section">
                        <h2>5. Intellectual Property</h2>
                        <p>All rights, title, and interest in and to the DevBug platform (excluding User Content) are and will remain the exclusive property of DevBug and its licensors. The service is protected by copyright, trademark, and other laws.</p>
                    </section>

                    <section id="termination" class="legal-section">
                        <h2>6. Termination</h2>
                        <p>We may terminate or suspend your account and bar access to the service immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                    </section>

                    <section id="disclaimer" class="legal-section">
                        <h2>7. Disclaimer of Warranties</h2>
                        <p>The service is provided on an "AS IS" and "AS AVAILABLE" basis. DevBug makes no warranties, expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property.</p>
                    </section>

                    <section id="liability" class="legal-section">
                        <h2>8. Limitation of Liability</h2>
                        <p>In no event shall DevBug, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the service.</p>
                    </section>

                    <section id="contact" class="legal-section">
                        <h2>9. Contact Us</h2>
                        <p>If you have any questions about these Terms, please <a href="contact.php">contact us</a> at legal@devbug.com.</p>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.legal-nav a');
            const sections = document.querySelectorAll('.legal-section');

            function changeActiveLink() {
                let index = sections.length;
                while(--index && window.scrollY + 120 < sections[index].offsetTop) {}
                navLinks.forEach((link) => link.classList.remove('active'));
                if (navLinks[index]) {
                    navLinks[index].classList.add('active');
                }
            }

            window.addEventListener('scroll', changeActiveLink);
            changeActiveLink();

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    if (targetSection) {
                        window.scrollTo({ top: targetSection.offsetTop - 100, behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>
</html>