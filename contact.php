<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .contact-content {
            padding: 80px 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }

        .contact-info {
            padding-right: 20px;
        }

        .contact-info h2 {
            font-size: 2.5rem;
            margin-bottom: 30px;
            color: var(--text-primary);
        }

        .contact-info p {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }

        .contact-methods {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .contact-method {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .method-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .method-content h4 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .method-content p {
            color: var(--text-secondary);
            margin-bottom: 5px;
            line-height: 1.6;
        }

        .method-content a {
            color: var(--accent-primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .method-content a:hover {
            color: var(--accent-secondary);
        }

        .contact-form-container {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 50px 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .contact-form-container h3 {
            font-size: 1.8rem;
            margin-bottom: 30px;
            color: var(--text-primary);
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .section-title p {
            color: var(--text-secondary);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .faq-section {
            margin-bottom: 80px;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: var(--transition);
        }

        .faq-item.active {
            border-color: var(--accent-primary);
        }

        .faq-question {
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .faq-question h4 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin: 0;
        }

        .faq-question i {
            color: var(--accent-primary);
            transition: var(--transition);
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 30px;
            max-height: 0;
            overflow: hidden;
            transition: var(--transition);
        }

        .faq-item.active .faq-answer {
            padding: 0 30px 25px;
            max-height: 500px;
        }

        .faq-answer p {
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        .map-section {
            margin-bottom: 80px;
        }

        .map-placeholder {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .map-placeholder i {
            font-size: 4rem;
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .map-placeholder h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .map-placeholder p {
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 500px;
            margin: 0 auto;
        }

        .emergency-support {
            background: linear-gradient(135deg, rgba(26, 26, 40, 0.8) 0%, rgba(26, 26, 40, 0.9) 100%);
            padding: 60px 40px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
            margin: 80px 0;
            position: relative;
        }

        .emergency-support:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
        }

        .emergency-support i {
            font-size: 3rem;
            margin-bottom: 25px;
            color: #ef4444;
        }

        .emergency-support h3 {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .emergency-support p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            line-height: 1.7;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .emergency-support .btn {
            justify-content: center;
        }

        @media (max-width: 968px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .contact-info {
                padding-right: 0;
            }
            
        }

        @media (max-width: 768px) {
            .contact-info h2 {
                font-size: 2rem;
            }
            
            .contact-form-container {
                padding: 40px 30px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 480px) {
            .contact-method {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .method-icon {
                align-self: center;
            }
            
            .faq-question {
                padding: 20px;
            }
            
            .faq-question h4 {
                font-size: 1.1rem;
            }
            
            .emergency-support {
                padding: 40px 25px;
            }
        }
    </style>
</head>
<body>
    <?php 
    // The header component now correctly resides inside the body
    include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Get In Touch";
    $pageSubtitle = "Have questions, feedback, or need support? We're here to help and would love to hear from you.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="contact-content">
            <!-- Contact Grid -->
            <section>
                <div class="contact-grid">
                    <div class="contact-info">
                        <h2>Let's Start a Conversation</h2>
                        <p>Whether you're a developer needing help, a potential partner, or just want to say hello, we're always excited to connect with members of our community.</p>
                        
                        <div class="contact-methods">
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="method-content">
                                    <h4>Email Us</h4>
                                    <p>General Inquiries</p>
                                    <a href="mailto:hello@devbug.com">hello@devbug.com</a>
                                    <p>Support</p>
                                    <a href="mailto:support@devbug.com">support@devbug.com</a>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="method-content">
                                    <h4>Call Us</h4>
                                    <p>Mon - Fri, 9am - 6pm EST</p>
                                    <a href="tel:+1-555-123-4567">+1 (555) 123-4567</a>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="method-content">
                                    <h4>Visit Us</h4>
                                    <p>123 Developer Lane</p>
                                    <p>San Francisco, CA 94102</p>
                                    <p>United States</p>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="method-content">
                                    <h4>Community</h4>
                                    <p>Join our Discord server to chat with other developers and our team.</p>
                                    <a href="https://discord.gg/devbug" target="_blank">Join Discord Community</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-form-container">
                        <h3>Send Us a Message</h3>
                        <form id="contact-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first-name">First Name *</label>
                                    <input type="text" id="first-name" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="last-name">Last Name *</label>
                                    <input type="text" id="last-name" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <select id="subject" name="subject" required>
                                    <option value="">Select a subject</option>
                                    <option value="general">General Inquiry</option>
                                    <option value="support">Technical Support</option>
                                    <option value="partnership">Partnership</option>
                                    <option value="careers">Careers</option>
                                    <option value="feedback">Feedback</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message *</label>
                                <textarea id="message" name="message" placeholder="Tell us how we can help you..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="newsletter" checked>
                                    <span>Subscribe to our newsletter for updates and tips</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="faq-section">
                <div class="section-title">
                    <h2>Frequently Asked Questions</h2>
                    <p>Quick answers to common questions</p>
                </div>
                
                <div class="faq-grid">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>How do I report a bug on the platform?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To report a bug, simply click on the "Report a Bug" button in the navigation or on the homepage. You'll need to be logged in to submit a bug report. Provide a clear title, description, relevant code snippets, and tags to help others understand and solve your issue.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Is DevBug free to use?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, DevBug is completely free for individual developers. We believe in making collaborative bug solving accessible to everyone. We may introduce premium features for teams in the future, but the core functionality will always remain free.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>How can I become a moderator?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Moderators are selected from our most active and helpful community members. If you consistently provide high-quality solutions, help other users, and maintain a positive presence in the community, you may be invited to become a moderator. You can also express your interest by contacting our community team.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>What programming languages are supported?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We support all major programming languages including JavaScript, Python, Java, C#, PHP, Ruby, Go, Rust, and many more. Our platform is language-agnostic, meaning you can get help with any programming language or technology stack.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>How do I delete my account?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can delete your account from the account settings page in your dashboard. Please note that this action is irreversible and will remove all your bug reports, solutions, and comments from the platform. If you're experiencing issues, consider contacting support first - we might be able to help!</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Map Section -->
            <section class="map-section">
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Our Headquarters</h3>
                    <p>While our team is distributed across the globe, our main office is located in San Francisco where we coordinate our efforts to build the best bug-solving platform for developers everywhere.</p>
                </div>
            </section>

            <!-- Emergency Support -->
            <section class="emergency-support">
                <i class="fas fa-life-ring"></i>
                <h3>Urgent Technical Issues?</h3>
                <p>If you're experiencing critical issues with our platform that prevent you from using it effectively, our emergency support team is available 24/7 to help resolve the problem quickly.</p>
                <a href="mailto:urgent@devbug.com" class="btn btn-primary">
                    <i class="fas fa-exclamation-triangle"></i> Contact Emergency Support
                </a>
            </section>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // FAQ Accordion
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', function() {
                    // Close all other items
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item && otherItem.classList.contains('active')) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current item
                    item.classList.toggle('active');
                });
            });
            
            // Contact form submission
            const contactForm = document.getElementById('contact-form');
            
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get form data
                const formData = new FormData(contactForm);
                const formValues = Object.fromEntries(formData.entries());
                
                // In a real application, you would send this data to your server
                console.log('Form submitted:', formValues);
                
                // Show success message
                alert('Thank you for your message! We will get back to you within 24 hours.');
                contactForm.reset();
            });
            
            // Add animation to contact methods on scroll
            const contactMethods = document.querySelectorAll('.contact-method');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateX(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            contactMethods.forEach(method => {
                method.style.opacity = '0';
                method.style.transform = 'translateX(-20px)';
                method.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(method);
            });
            
            // Add focus effects to form inputs
            const formInputs = document.querySelectorAll('.form-group input, .form-group textarea, .form-group select');
            
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        });
    </script>
</body>
</html>