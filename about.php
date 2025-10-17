<?php
// about.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/Components/header.php');
?>
<?php
// Get platform statistics
$stats = [];
require_once 'config/database.php'; // Moved here to be after session start

try {
    // Fetch simple counts
    $stats_sql = "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM bugs) as total_bugs,
        (SELECT COUNT(*) FROM bugs WHERE status = 'solved') as solved_bugs";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch and count unique tags for 'technologies'
    $tags_sql = "SELECT tags FROM bugs WHERE tags IS NOT NULL AND tags != ''";
    $tags_stmt = $pdo->query($tags_sql);
    $all_tags = [];
    while ($row = $tags_stmt->fetch(PDO::FETCH_ASSOC)) {
        $tag_list = explode(',', $row['tags']);
        foreach ($tag_list as $tag) {
            $tag = trim($tag);
            if (!empty($tag) && !in_array($tag, $all_tags)) {
                $all_tags[] = $tag;
            }
        }
    }
    $stats['technologies'] = count($all_tags);
} catch (PDOException $e) {
    error_log("Error fetching stats for about page: " . $e->getMessage());
    // Provide default stats if the query fails
    $stats = [
        'total_users' => '12,489',
        'total_bugs' => '5,281',
        'solved_bugs' => '4,362',
        'technologies' => '27'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Page Header */
         .page-header {
            padding: 80px 0 20px;
            text-align: center;
        }

        /* Mission Section */
        .mission {
            padding: 80px 0;
            background: var(--bg-secondary);
            border-radius: 20px;
            margin-bottom: 80px;
            position: relative;
        }

        .mission::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--accent-primary), var(--accent-secondary), var(--accent-tertiary));
            border-radius: 22px;
            z-index: -1;
        }

        .mission-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .mission-text h2 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            color: var(--text-primary);
        }

        .mission-text p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .mission-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .stat-box {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-box:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
            box-shadow: var(--card-shadow);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Team Section */
        .team {
            padding: 80px 0;
            margin-bottom: 80px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--text-primary);
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 2px;
        }

        .section-title p {
            color: var(--text-secondary);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .team-member {
            background: var(--bg-card);
            border-radius: 16px;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .team-member:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-primary);
        }

        .member-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .member-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .team-member:hover .member-image img {
            transform: scale(1.05);
        }

        .member-info {
            padding: 25px;
        }

        .member-name {
            font-size: 1.4rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .member-role {
            color: var(--accent-primary);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .member-desc {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .member-social {
            display: flex;
            gap: 15px;
        }

        .member-social a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .member-social a:hover {
            background: var(--accent-primary);
            color: white;
            transform: translateY(-3px);
        }

        /* Values Section */
        .values {
            padding: 80px 0;
            background: var(--bg-secondary);
            border-radius: 20px;
            margin-bottom: 80px;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .value-card {
            background: var(--bg-card);
            padding: 40px 30px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .value-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
            box-shadow: var(--card-shadow);
        }

        .value-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2rem;
            color: var(--accent-primary);
        }

        .value-title {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .value-desc {
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* Timeline Section */
        .timeline {
            padding: 80px 0;
            margin-bottom: 80px;
        }

        .timeline-container {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .timeline-container::after {
            content: '';
            position: absolute;
            width: 4px;
            background: linear-gradient(to bottom, var(--accent-primary), var(--accent-secondary));
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -2px;
            border-radius: 10px;
        }

        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
            box-sizing: border-box;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-content {
            padding: 20px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            position: relative;
            transition: var(--transition);
        }

        .timeline-content:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
            box-shadow: var(--card-shadow);
        }

        .timeline-date {
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: 10px;
        }

        .timeline-title {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .timeline-desc {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--accent-primary);
            border-radius: 50%;
            top: 50%;
            right: -10px;
            z-index: 1;
            transform: translateY(-50%);
        }

        .timeline-item:nth-child(even)::after {
            left: -10px;
        }

        /* CTA Section */
        .cta {
            padding: 100px 0;
            text-align: center;
            background: linear-gradient(135deg, rgba(26, 26, 40, 0.8) 0%, rgba(26, 26, 40, 0.9) 100%);
            border-radius: 20px;
            margin-bottom: 80px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .cta p {
            color: var(--text-secondary);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .page-header h1 {
                font-size: 3rem;
            }
            .page-header p {
                font-size: 1.2rem;
            }
            .mission-text h2, .section-title h2, .cta h2 {
                font-size: 2.2rem;
            }
            .stat-value {
                font-size: 2.2rem;
            }
            .member-name, .value-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 968px) {
            .mission-content {
                grid-template-columns: 1fr;
            }
            
            .timeline-container::after {
                left: 31px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 25px;
            }
            
            .timeline-item:nth-child(even) {
                left: 0;
            }
            
            .timeline-item::after {
                left: 21px;
                right: auto;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 15px;
            }
            .page-header h1 {
                font-size: 2.5rem;
            }
            .page-header p {
                font-size: 1.1rem;
            }
            .mission, .team, .values, .timeline, .cta {
                padding: 60px 0;
                margin-bottom: 60px;
            }
            .mission-text h2, .section-title h2, .cta h2 {
                font-size: 2rem;
            }
            .stat-value {
                font-size: 2rem;
            }
            .member-name, .value-title {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 600px) {
            .mission-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .section-title h2 {
                font-size: 2rem;
            }
            
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .values-grid {
                grid-template-columns: 1fr;
            }
            .stat-box {
                padding: 15px;
            }
        }

        @media (max-width: 500px) {
            .page-header h1 {
                font-size: 2.2rem;
            }
            .mission-text h2, .section-title h2, .cta h2 {
                font-size: 1.8rem;
            }
            .mission-text p, .section-title p, .member-desc, .value-desc, .timeline-desc {
                font-size: 1rem;
            }
            .stat-value {
                font-size: 2rem;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $pageTitle = "About DevBug";
    $pageSubtitle = "We're building the world's largest community-driven platform for developers to solve coding challenges together.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Mission Section -->
    <section class="mission">
        <div class="container">
            <div class="mission-content">
                <div class="mission-text">
                    <h2>Our Mission</h2>
                    <p>DevBug was founded with a simple goal: to create a space where developers of all skill levels can collaborate to solve programming challenges, share knowledge, and grow together.</p>
                    <p>We believe that the best solutions come from diverse perspectives working together. Our platform empowers developers to learn from each other and build better software, one bug at a time.</p>
                </div>
                <div class="mission-stats">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Developers</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($stats['total_bugs']); ?></div>
                        <div class="stat-label">Bugs Posted</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($stats['solved_bugs']); ?></div>
                        <div class="stat-label">Bugs Solved</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($stats['technologies']); ?></div>
                        <div class="stat-label">Technologies</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team">
        <div class="container">
            <div class="section-title">
                <h2>Our Team</h2>
                <p>The passionate developers behind DevBug</p>
            </div>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-image">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Team Member">
                    </div>
                    <div class="member-info">
                        <h3 class="member-name">Alex Johnson</h3>
                        <div class="member-role">Founder & CEO</div>
                        <p class="member-desc">Full-stack developer with 10+ years of experience. Passionate about building communities and solving complex technical challenges.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-github"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
                <div class="team-member">
                    <div class="member-image">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Team Member">
                    </div>
                    <div class="member-info">
                        <h3 class="member-name">Sarah Chen</h3>
                        <div class="member-role">Lead Developer</div>
                        <p class="member-desc">JavaScript expert specializing in React and Node.js. Loves creating elegant solutions to complex problems.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-github"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
                <div class="team-member">
                    <div class="member-image">
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Team Member">
                    </div>
                    <div class="member-info">
                        <h3 class="member-name">Marcus Rivera</h3>
                        <div class="member-role">Community Manager</div>
                        <p class="member-desc">DevOps engineer with a passion for building inclusive developer communities and fostering collaboration.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-github"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="values">
        <div class="container">
            <div class="section-title">
                <h2>Our Values</h2>
                <p>The principles that guide everything we do</p>
            </div>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="value-title">Community First</h3>
                    <p class="value-desc">We believe that the best solutions emerge when developers collaborate. Our platform is designed to foster meaningful connections and knowledge sharing.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3 class="value-title">Continuous Learning</h3>
                    <p class="value-desc">We're committed to creating an environment where developers of all levels can learn, grow, and expand their skills through practical problem-solving.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="value-title">Inclusivity</h3>
                    <p class="value-desc">We welcome developers from all backgrounds and experience levels. Diverse perspectives lead to better solutions and a stronger community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Timeline Section -->
    <section class="timeline">
        <div class="container">
            <div class="section-title">
                <h2>Our Journey</h2>
                <p>How DevBug came to be</p>
            </div>
            <div class="timeline-container">
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date">January 2021</div>
                        <h3 class="timeline-title">Concept Born</h3>
                        <p class="timeline-desc">The idea for DevBug was conceived when our founder struggled to find help with a complex bug late at night.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date">June 2021</div>
                        <h3 class="timeline-title">First Prototype</h3>
                        <p class="timeline-desc">We built the first working prototype and invited 100 developers to test the platform and provide feedback.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date">January 2022</div>
                        <h3 class="timeline-title">Public Launch</h3>
                        <p class="timeline-desc">DevBug officially launched to the public, welcoming developers from around the world to join our community.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date">Present</div>
                        <h3 class="timeline-title">Growing Community</h3>
                        <p class="timeline-desc">Our community has grown to over 12,000 developers who have collectively solved more than 4,000 bugs.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <h2>Join Our Community</h2>
                <p>Become part of a growing network of developers helping each other solve challenges and advance their skills.</p>
                <a href="auth.php" class="btn btn-primary">Create Your Account</a>
            <?php else: ?>
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Developer'); ?>!</h2>
                <p>Continue your journey with us. Explore bugs, share solutions, or head to your dashboard.</p>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'footer.html'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animation for timeline items
            const timelineItems = document.querySelectorAll('.timeline-content');
            
            function checkScroll() {
                timelineItems.forEach(item => {
                    const itemTop = item.getBoundingClientRect().top;
                    const windowHeight = window.innerHeight;
                    
                    if (itemTop < windowHeight * 0.9) {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }
                });
            }
            
            // Initialize opacity and position
            timelineItems.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            });
            
            // Check on load and scroll
            checkScroll();
            window.addEventListener('scroll', checkScroll);
            
            // Team member hover effect
            const teamMembers = document.querySelectorAll('.team-member');
            teamMembers.forEach(member => {
                member.addEventListener('mouseenter', function() {
                    this.querySelector('.member-social').style.opacity = '1';
                    this.querySelector('.member-social').style.transform = 'translateY(0)';
                });
                
                member.addEventListener('mouseleave', function() {
                    this.querySelector('.member-social').style.opacity = '0';
                    this.querySelector('.member-social').style.transform = 'translateY(10px)';
                });
                
                // Initialize social icons
                const social = member.querySelector('.member-social');
                social.style.opacity = '0';
                social.style.transform = 'translateY(10px)';
                social.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            });
            
            // Value cards animation
            const valueCards = document.querySelectorAll('.value-card');
            
            function checkValueCards() {
                valueCards.forEach(card => {
                    const cardTop = card.getBoundingClientRect().top;
                    const windowHeight = window.innerHeight;
                    
                    if (cardTop < windowHeight * 0.85) {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }
                });
            }
            
            // Initialize opacity and position
            valueCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            });
            
            // Check on load and scroll
            checkValueCards();
            window.addEventListener('scroll', checkValueCards);
        });
    </script>
</body>
</html>