<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reusing styles from documentation.php for consistency */
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
    $pageTitle = "Privacy Policy";
    $pageSubtitle = "Your privacy is important to us. This policy explains what data we collect and how we use it.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="legal-content">
            <div class="legal-layout">
                <!-- Navigation -->
                <aside class="legal-nav">
                    <h3>Privacy Policy</h3>
                    <ul>
                        <li><a href="#introduction" class="active">Introduction</a></li>
                        <li><a href="#information-we-collect">Information We Collect</a></li>
                        <li><a href="#how-we-use-information">How We Use Information</a></li>
                        <li><a href="#sharing-information">Sharing Information</a></li>
                        <li><a href="#data-security">Data Security</a></li>
                        <li><a href="#your-rights">Your Rights</a></li>
                        <li><a href="#changes-to-policy">Changes to This Policy</a></li>
                        <li><a href="#contact-us">Contact Us</a></li>
                    </ul>
                </aside>

                <!-- Policy Sections -->
                <div class="legal-main">
                    <p>Last updated: October 29, 2023</p>
                    <section id="introduction" class="legal-section">
                        <h2>1. Introduction</h2>
                        <p>Welcome to DevBug. We are committed to protecting your personal information and your right to privacy. If you have any questions or concerns about our policy, or our practices with regards to your personal information, please contact us.</p>
                    </section>

                    <section id="information-we-collect" class="legal-section">
                        <h2>2. Information We Collect</h2>
                        <p>We collect personal information that you voluntarily provide to us when you register on the website, express an interest in obtaining information about us or our products and services, when you participate in activities on the website or otherwise when you contact us.</p>
                        <h3>Information you provide to us:</h3>
                        <ul>
                            <li><strong>Account Information:</strong> When you create an account, we collect your name, username, email address, and password.</li>
                            <li><strong>Profile Information:</strong> You may choose to provide additional information for your public profile, such as a bio, location, company, website, and social media links.</li>
                            <li><strong>User Content:</strong> We collect the content you post to the service, including bug reports, solutions, comments, and file uploads.</li>
                        </ul>
                    </section>

                    <section id="how-we-use-information" class="legal-section">
                        <h2>3. How We Use Your Information</h2>
                        <p>We use the information we collect or receive:</p>
                        <ul>
                            <li>To facilitate account creation and logon process.</li>
                            <li>To post your content and make it visible to other users.</li>
                            <li>To send you administrative information, such as updates to our terms and policies.</li>
                            <li>To protect our Services from abuse and ensure security.</li>
                            <li>To respond to your inquiries and solve any potential issues you might have with the use of our Services.</li>
                        </ul>
                    </section>

                    <section id="sharing-information" class="legal-section">
                        <h2>4. Sharing Your Information</h2>
                        <p>We do not share your personal information with third parties except in the following circumstances:</p>
                        <ul>
                            <li><strong>With Your Consent:</strong> We may share your information with your consent or at your direction.</li>
                            <li><strong>For Legal Reasons:</strong> We may share information if we believe it's required by law, such as to comply with a subpoena or other legal process.</li>
                        </ul>
                    </section>

                    <section id="data-security" class="legal-section">
                        <h2>5. Data Security</h2>
                        <p>We have implemented appropriate technical and organizational security measures designed to protect the security of any personal information we process. However, please also remember that we cannot guarantee that the internet itself is 100% secure.</p>
                    </section>

                    <section id="your-rights" class="legal-section">
                        <h2>6. Your Rights</h2>
                        <p>You have the right to access, correct, or delete your personal information. You can manage your account information from your user dashboard. If you wish to delete your account, you can do so from the settings page.</p>
                    </section>

                    <section id="changes-to-policy" class="legal-section">
                        <h2>7. Changes to This Policy</h2>
                        <p>We may update this privacy policy from time to time. The updated version will be indicated by an updated "Last updated" date and the updated version will be effective as soon as it is accessible. We encourage you to review this privacy policy frequently to be informed of how we are protecting your information.</p>
                    </section>

                    <section id="contact-us" class="legal-section">
                        <h2>8. Contact Us</h2>
                        <p>If you have questions or comments about this policy, you may <a href="contact.php">contact us by email</a> at privacy@devbug.com.</p>
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