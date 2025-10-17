<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .careers-content {
            padding: 80px 0;
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

        .why-join {
            margin-bottom: 80px;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .benefit-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .benefit-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .benefit-card i {
            font-size: 3rem;
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .benefit-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .benefit-card p {
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .open-positions {
            margin-bottom: 80px;
        }

        .position-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-btn {
            padding: 12px 24px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .position-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
        }

        .position-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .position-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-fulltime {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .badge-remote {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .badge-internship {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .position-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .position-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .position-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .position-description {
            color: var(--text-secondary);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .position-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .position-tag {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent-primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .hiring-process {
            margin-bottom: 80px;
        }

        .process-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            counter-reset: process-step;
        }

        .process-step {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            border: 1px solid var(--border);
            position: relative;
            transition: var(--transition);
        }

        .process-step:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
        }

        .process-step:before {
            counter-increment: process-step;
            content: counter(process-step);
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .process-step i {
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .process-step h4 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .process-step p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .cta-section {
            text-align: center;
            padding: 80px 0;
            background: var(--bg-secondary);
            border-radius: 16px;
            margin: 80px 0;
            border: 1px solid var(--border);
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .cta-section p {
            color: var(--text-secondary);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        @media (max-width: 968px) {
            .positions-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .position-card {
                padding: 25px 20px;
            }
            
            .position-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .cta-section h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    // The header component now correctly resides inside the body
    include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Join Our Team";
    $pageSubtitle = "Help us build the future of collaborative bug solving while growing your career with cutting-edge technology.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="careers-content">
            <!-- Why Join Us -->
            <section class="why-join">
                <div class="section-title">
                    <h2>Why Join DevBug?</h2>
                    <p>We're building more than a platform - we're building a community</p>
                </div>
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <i class="fas fa-code"></i>
                        <h3>Technical Excellence</h3>
                        <p>Work with modern technologies and solve complex technical challenges that impact thousands of developers daily.</p>
                    </div>
                    <div class="benefit-card">
                        <i class="fas fa-users"></i>
                        <h3>Collaborative Culture</h3>
                        <p>Join a team that values collaboration, knowledge sharing, and continuous learning in a supportive environment.</p>
                    </div>
                    <div class="benefit-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Growth Opportunities</h3>
                        <p>Grow your skills with mentorship programs, conference attendance, and challenging projects that push your boundaries.</p>
                    </div>
                    <div class="benefit-card">
                        <i class="fas fa-heart"></i>
                        <h3>Make an Impact</h3>
                        <p>Your work will directly help developers around the world solve problems faster and learn from each other.</p>
                    </div>
                    <div class="benefit-card">
                        <i class="fas fa-home"></i>
                        <h3>Remote-First</h3>
                        <p>Work from anywhere with our flexible remote-first policy and modern collaboration tools.</p>
                    </div>
                    <div class="benefit-card">
                        <i class="fas fa-award"></i>
                        <h3>Competitive Benefits</h3>
                        <p>Enjoy competitive compensation, equity options, comprehensive health benefits, and unlimited PTO.</p>
                    </div>
                </div>
            </section>

            <!-- Open Positions -->
            <section class="open-positions">
                <div class="section-title">
                    <h2>Open Positions</h2>
                    <p>Find your perfect role and help shape the future of developer collaboration</p>
                </div>
                
                <div class="position-filters">
                    <button class="filter-btn active" data-filter="all">All Positions</button>
                    <button class="filter-btn" data-filter="engineering">Engineering</button>
                    <button class="filter-btn" data-filter="product">Product</button>
                    <button class="filter-btn" data-filter="design">Design</button>
                    <button class="filter-btn" data-filter="marketing">Marketing</button>
                </div>
                
                <div class="positions-grid">
                    <div class="position-card" data-category="engineering">
                        <span class="position-badge badge-remote">Remote</span>
                        <h3 class="position-title">Senior Full Stack Developer</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Full-time</span>
                            <span><i class="fas fa-clock"></i> Posted 2 days ago</span>
                        </div>
                        <p class="position-description">We're looking for an experienced full-stack developer to help build and scale our core platform. You'll work across the entire stack and have significant impact on our product direction.</p>
                        <div class="position-tags">
                            <span class="position-tag">JavaScript</span>
                            <span class="position-tag">React</span>
                            <span class="position-tag">Node.js</span>
                            <span class="position-tag">PostgreSQL</span>
                            <span class="position-tag">AWS</span>
                        </div>
                        <a href="apply.php?job=Senior+Full+Stack+Developer" class="btn btn-primary">Apply Now</a>
                    </div>
                    
                    <div class="position-card" data-category="engineering">
                        <span class="position-badge badge-remote">Remote</span>
                        <h3 class="position-title">DevOps Engineer</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Full-time</span>
                            <span><i class="fas fa-clock"></i> Posted 1 week ago</span>
                        </div>
                        <p class="position-description">Join our infrastructure team to build and maintain our cloud infrastructure, CI/CD pipelines, and ensure high availability and performance of our platform.</p>
                        <div class="position-tags">
                            <span class="position-tag">AWS</span>
                            <span class="position-tag">Docker</span>
                            <span class="position-tag">Kubernetes</span>
                            <span class="position-tag">Terraform</span>
                            <span class="position-tag">CI/CD</span>
                        </div>
                        <a href="apply.php?job=DevOps+Engineer" class="btn btn-primary">Apply Now</a>
                    </div>
                    
                    <div class="position-card" data-category="product">
                        <span class="position-badge badge-remote">Remote</span>
                        <h3 class="position-title">Product Manager</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Full-time</span>
                            <span><i class="fas fa-clock"></i> Posted 3 days ago</span>
                        </div>
                        <p class="position-description">Lead product initiatives from conception to launch, working closely with engineering, design, and community teams to deliver exceptional user experiences.</p>
                        <div class="position-tags">
                            <span class="position-tag">Product Strategy</span>
                            <span class="position-tag">User Research</span>
                            <span class="position-tag">Agile</span>
                            <span class="position-tag">Analytics</span>
                        </div><a href="apply.php?job=Product+Manager" class="btn btn-primary">Apply Now</a>
                    </div>
                    
                    <div class="position-card" data-category="design">
                        <span class="position-badge badge-remote">Remote</span>
                        <h3 class="position-title">UI/UX Designer</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Full-time</span>
                            <span><i class="fas fa-clock"></i> Posted 5 days ago</span>
                        </div>
                        <p class="position-description">Design intuitive and beautiful user experiences for our platform. You'll work across the entire product and help shape our design system and visual language.</p>
                        <div class="position-tags">
                            <span class="position-tag">Figma</span>
                            <span class="position-tag">UI Design</span>
                            <span class="position-tag">UX Research</span>
                            <span class="position-tag">Prototyping</span>
                        </div>
                        <a href="apply.php?job=UI%2FUX+Designer" class="btn btn-primary">Apply Now</a>
                    </div>
                    
                    <div class="position-card" data-category="marketing">
                        <span class="position-badge badge-remote">Remote</span>
                        <h3 class="position-title">Community Manager</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Full-time</span>
                            <span><i class="fas fa-clock"></i> Posted 2 weeks ago</span>
                        </div>
                        <p class="position-description">Build and nurture our developer community, create engaging content, and help developers get the most value from our platform.</p>
                        <div class="position-tags">
                            <span class="position-tag">Community Building</span>
                            <span class="position-tag">Content Creation</span>
                            <span class="position-tag">Social Media</span>
                            <span class="position-tag">Developer Relations</span>
                        </div>
                        <a href="apply.php?job=Community+Manager" class="btn btn-primary">Apply Now</a>
                    </div>
                    
                    <div class="position-card" data-category="engineering">
                        <span class="position-badge badge-internship">Internship</span>
                        <h3 class="position-title">Frontend Developer Intern</h3>
                        <div class="position-meta">
                            <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                            <span><i class="fas fa-briefcase"></i> Internship</span>
                            <span><i class="fas fa-clock"></i> Posted 1 day ago</span>
                        </div>
                        <p class="position-description">Great opportunity for students to gain real-world experience working on our React-based frontend with mentorship from senior developers.</p>
                        <div class="position-tags">
                            <span class="position-tag">React</span>
                            <span class="position-tag">JavaScript</span>
                            <span class="position-tag">CSS</span>
                            <span class="position-tag">Learning</span>
                        </div>
                        <a href="apply.php?job=Frontend+Developer+Intern" class="btn btn-primary">Apply Now</a>
                    </div>
                </div>
            </section>

            <!-- Hiring Process -->
            <section class="hiring-process">
                <div class="section-title">
                    <h2>Our Hiring Process</h2>
                    <p>Transparent and respectful - we value your time and effort</p>
                </div>
                <div class="process-steps">
                    <div class="process-step">
                        <i class="fas fa-file-alt"></i>
                        <h4>Application Review</h4>
                        <p>Our team reviews your application and portfolio. We aim to respond within 3 business days.</p>
                    </div>
                    <div class="process-step">
                        <i class="fas fa-video"></i>
                        <h4>Initial Screening</h4>
                        <p>A 30-minute video call to discuss your experience, skills, and interest in joining our team.</p>
                    </div>
                    <div class="process-step">
                        <i class="fas fa-laptop-code"></i>
                        <h4>Technical Assessment</h4>
                        <p>A practical coding challenge that reflects real work you'd do at DevBug.</p>
                    </div>
                    <div class="process-step">
                        <i class="fas fa-users"></i>
                        <h4>Team Interviews</h4>
                        <p>Meet with team members to discuss technical challenges and collaborative problem-solving.</p>
                    </div>
                    <div class="process-step">
                        <i class="fas fa-handshake"></i>
                        <h4>Offer</h4>
                        <p>We extend an offer and welcome you to the DevBug family!</p>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="cta-section">
                <h2>Don't See the Perfect Role?</h2>
                <p>We're always looking for talented people who are passionate about our mission. Send us your resume and tell us how you can contribute.</p>
                <a href="mailto:careers@devbug.com" class="btn btn-primary">Send Open Application</a>
            </section>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Position filtering
            const filterBtns = document.querySelectorAll('.filter-btn');
            const positionCards = document.querySelectorAll('.position-card');
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterBtns.forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    positionCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-category') === filter) {
                            card.style.display = 'block';
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, 10);
                        } else {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                card.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });
            
            // Add animation to process steps on scroll
            const processSteps = document.querySelectorAll('.process-step');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            processSteps.forEach(step => {
                step.style.opacity = '0';
                step.style.transform = 'translateY(30px)';
                step.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(step);
            });
            
            // Add hover effect to position cards
            positionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-5px)';
                });
            });
        });
    </script>
</body>
</html>