<?php
// leaderboard.php
// Start session at the very top of the script
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- AJAX Handler for fetching leaderboard data ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (isset($_POST['get_leaderboard_data'])) {
        header('Content-Type: application/json');
        
        require_once 'config/database.php';
        
        $period = $_POST['period'] ?? 'all';
        $interval = '';

        switch ($period) {
            case 'month': $interval = '1 MONTH'; break;
            case 'week': $interval = '1 WEEK'; break;
            case 'today': $interval = '1 DAY'; break;
            default: $interval = ''; break;
        }

        if ($period === 'all') {
            $sql = "
                SELECT u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation,
                       COUNT(DISTINCT s.id) as solutions_count,
                       COUNT(DISTINCT b.id) as bugs_count
                FROM users u
                LEFT JOIN solutions s ON u.id = s.user_id
                LEFT JOIN bugs b ON u.id = b.user_id
                GROUP BY u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation
                HAVING reputation > 0
                ORDER BY reputation DESC
                LIMIT 20
            ";
        } else {
            $sql = "
                SELECT u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation,
                       (SELECT COUNT(DISTINCT s.id) FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval) as solutions_count,
                       (SELECT COUNT(DISTINCT b.id) FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval) as bugs_count,
                       (SELECT COUNT(DISTINCT s.id) FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval) + 
                       (SELECT COUNT(DISTINCT b.id) FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval) as activity_count
                FROM users u
                -- We join to ensure we only select users who have been active in the period
                WHERE EXISTS (SELECT 1 FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval)
                   OR EXISTS (SELECT 1 FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval)
                GROUP BY u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation
                ORDER BY reputation DESC, activity_count DESC
                LIMIT 20
            ";
        }
        
        $stmt = $pdo->query($sql);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        if (!empty($leaderboard)) {
            foreach ($leaderboard as $index => $user) {
                include 'leaderboard_row.php';
            }
        } else {
            echo '
            <div class="no-data">
                <i class="fas fa-trophy"></i>
                <h3>No activity in this period</h3>
                <p>Be the first to solve bugs and earn reputation points!</p>
            </div>
            ';
        }
        $html = ob_get_clean();
        
        echo json_encode(['success' => true, 'html' => $html]);
        exit();
    }
}



// Include header at the top to handle any session/header logic before output
include(__DIR__ . '/Components/header.php');

// Database connection
require_once 'config/database.php';

// --- Time Period Logic ---
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$interval = '';
$period_condition = '';
switch ($period) {
    case 'month':
        $interval = '1 MONTH';
        break;
    case 'week':
        $interval = '1 WEEK';
        break;
    case 'today':
        $interval = '1 DAY';
        break;
    case 'all':
    default:
        break;
}

// Fetch leaderboard data from database
$leaderboard = [];
$categories = [];
$userRank = null;

try {
    if ($period === 'all') {
        $sql = "
            SELECT 
                u.id, 
                u.name, 
                u.title, 
                u.avatar_color, 
                u.profile_picture,
                u.reputation,
                COUNT(DISTINCT s.id) as solutions_count,
                COUNT(DISTINCT b.id) as bugs_count
            FROM users u
            LEFT JOIN solutions s ON u.id = s.user_id
            LEFT JOIN bugs b ON u.id = b.user_id
            GROUP BY u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation
            HAVING reputation > 0
            ORDER BY reputation DESC
            LIMIT 20
        ";
    } else {
        $sql = "
            SELECT u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation,
                   (SELECT COUNT(DISTINCT s.id) FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval) as solutions_count,
                   (SELECT COUNT(DISTINCT b.id) FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval) as bugs_count,
                   (SELECT COUNT(DISTINCT s.id) FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval) + 
                   (SELECT COUNT(DISTINCT b.id) FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval) as activity_count
            FROM users u
            -- We use EXISTS for an efficient check for activity within the period
            WHERE EXISTS (SELECT 1 FROM solutions s WHERE s.user_id = u.id AND s.created_at >= NOW() - INTERVAL $interval)
               OR EXISTS (SELECT 1 FROM bugs b WHERE b.user_id = u.id AND b.created_at >= NOW() - INTERVAL $interval)
            GROUP BY u.id, u.name, u.title, u.avatar_color, u.profile_picture, u.reputation
            ORDER BY reputation DESC, activity_count DESC
            LIMIT 20
        ";
    }
    
    $stmt = $pdo->query($sql);
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get category leaders (remains all-time for now)
    $categorySql = "
        SELECT 
            u.id, 
            u.name,
            u.profile_picture,
            u.avatar_color,
            SUM(s.upvotes) as score
        FROM users u
        JOIN (
            SELECT s.user_id, s.id, COUNT(sv.id) as upvotes
            FROM solutions s
            JOIN bugs b ON s.bug_id = b.id
            LEFT JOIN solution_votes sv ON s.id = sv.solution_id AND sv.vote_type = 'up'
            WHERE b.tags LIKE ?
            GROUP BY s.id, s.user_id
        ) s ON u.id = s.user_id
        GROUP BY u.id, u.name, u.profile_picture, u.avatar_color
        ORDER BY score DESC
        LIMIT 3
    ";

    $jsStmt = $pdo->prepare($categorySql);
    $jsStmt->execute(['%javascript%']);
    $categories['JavaScript'] = $jsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pyStmt = $pdo->prepare($categorySql);
    $pyStmt->execute(['%python%']);
    $categories['Python'] = $pyStmt->fetchAll(PDO::FETCH_ASSOC);

    $dbStmt = $pdo->prepare($categorySql);
    $dbStmt->execute(['%database%']);
    $categories['Database'] = $dbStmt->fetchAll(PDO::FETCH_ASSOC);

    $categoryStmt = $pdo->query(" -- This query is for PHP, but let's use the prepared statement approach for consistency.
        SELECT 
            'Database' as category,
            u.id, 
            u.name,
            u.profile_picture,
            u.avatar_color,
            COUNT(DISTINCT s.id) as solutions_count,
            (SELECT COUNT(*) FROM solution_votes sv WHERE sv.solution_id = s.id AND sv.vote_type = 'up') as score
        FROM users u
        JOIN solutions s ON u.id = s.user_id
        WHERE s.bug_id IN (SELECT id FROM bugs WHERE tags LIKE '%php%')
        GROUP BY u.id, u.name, u.avatar_color, u.profile_picture
        ORDER BY score DESC
        LIMIT 3
    ");
    $categories['Database'] = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current user's rank if logged in
    if (isset($_SESSION['user_id'])) {
        // User rank logic can be complex with dynamic periods, so we'll keep it simple for now
        // This part might need more work for perfect accuracy across periods
    }

} catch (PDOException $e) {
    $error = "Error fetching leaderboard data: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - DevBug</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
       

        /* Page Header */
      .page-header {
            padding: 80px 0 20px;
            text-align: center;
        }
        .page-header h1 {
            font-size: 2.8rem;
            margin-bottom: 16px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Time Filters */
        .time-filters {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 40px;
        }

        .time-filter {
            padding: 10px 20px;
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .time-filter:hover, .time-filter.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        /* Leaderboard */
        .leaderboard-container {
            background: var(--bg-card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 60px;
            border: 1px solid var(--border);
        }

        .leaderboard-header {
            display: grid;
            grid-template-columns: 80px 1fr 120px 120px 120px;
            padding: 20px 30px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--accent-primary);
        }

        .leaderboard-row {
            display: grid;
            grid-template-columns: 80px 1fr 120px 120px 120px;
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            align-items: center;
            transition: var(--transition);
        }

        .leaderboard-row:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .leaderboard-row:last-child {
            border-bottom: none;
        }

        .rank {
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
        }

        .rank-1, .rank-2, .rank-3 {
            position: relative;
        }

        .rank-1::before {
            content: '🥇';
            position: absolute;
            left: -5px;
            top: 50%;
            transform: translateY(-50%);
        }

        .rank-2::before {
            content: '🥈';
            position: absolute;
            left: -5px;
            top: 50%;
            transform: translateY(-50%);
        }

        .rank-3::before {
            content: '🥉';
            position: absolute;
            left: -5px;
            top: 50%;
            transform: translateY(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .user-name a,
        .top-user-name a {
            color: inherit;
            text-decoration: none;
            transition: var(--transition);
        }

        .user-name a:hover,
        .top-user-name a:hover {
            color: var(--accent-primary);
        }

      
        .stats-value {
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Category Leaderboards */
        .category-leaderboards {
            margin-top: 60px;
            margin-bottom: 60px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .section-title p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .category-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .category-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .category-icon {
            font-size: 2rem;
            color: var(--accent-primary);
        }

        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .top-user-list {
            list-style: none;
        }

        .top-user-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
        }

        .top-user-rank {
            font-size: 1.1rem;
            font-weight: 700;
            width: 30px;
            text-align: center;
            color: var(--text-muted);
        }

        .top-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            overflow: hidden;
        }

        .top-user-details {
            flex: 1;
        }

        .top-user-name {
            font-weight: 500;
        }

        .top-user-score {
            font-weight: 600;
            color: var(--accent-primary);
        }

        /* Make the stats container's children act as direct grid items on desktop */
        .stats-container {
            display: contents;
        }

        /* No data message */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--accent-primary);
        }

        .no-data h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        /* Other styles from original file... */

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2.2rem;
            }
            .page-header p {
                font-size: 1.1rem;
            }
            .leaderboard-header, .leaderboard-row {
                grid-template-columns: 60px 1fr 90px 90px 90px;
                padding: 15px 20px;
                font-size: 0.9rem;
            }
            .stats-value {
                font-size: 1rem;
            }
            .user-name a {
                font-size: 1rem;
            }
            .section-title h2 {
                font-size: 1.8rem;
            }
            .category-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 640px) {
            .page-header h1 {
                font-size: 1.9rem;
            }
            .page-header p {
                font-size: 0.95rem;
            }
            .time-filters {
                gap: 8px;
                flex-wrap: wrap;
            }
            .time-filter {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            .leaderboard-header {
                display: none; /* Hide header on mobile, labels are implicit */
            }
            .leaderboard-row {
                grid-template-columns: 50px 1fr; /* Rank and user info */
                grid-template-areas:
                    "rank user"
                    ".    stats";
                row-gap: 10px;
                padding: 15px;
                align-items: start;
            }
            .rank {
                grid-area: rank;
                padding-top: 10px;
            }
            .user-info {
                grid-area: user;
            }
            .stats-container {
                grid-area: stats;
                display: flex;
                justify-content: space-around;
                background: var(--bg-secondary);
                padding: 10px;
                border-radius: 8px;
                margin-top: 5px;
                gap: 10px;
            }
            .stats-container .stats-value {
                display: flex;
                flex-direction: column;
                align-items: center;
                font-size: 0.9rem;
            }
            .stats-container .stats-value::before {
                content: attr(data-label);
                font-size: 0.7rem;
                color: var(--text-muted);
                margin-bottom: 4px;
                font-weight: 400;
            }
        }
    </style>
</head>
<body> 
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Developer Leaderboard</h1>
            <p>See how you rank against other developers in the community</p>
        </div>
    </section>

    <!-- Time Filters -->
    <div class="container">
        <div class="time-filters">
            <a href="?period=all" class="time-filter <?php echo ($period === 'all') ? 'active' : ''; ?>">All Time</a>
            <a href="?period=month" class="time-filter <?php echo ($period === 'month') ? 'active' : ''; ?>">This Month</a>
            <a href="?period=week" class="time-filter <?php echo ($period === 'week') ? 'active' : ''; ?>">This Week</a>
            <a href="?period=today" class="time-filter <?php echo ($period === 'today') ? 'active' : ''; ?>">Today</a>
        </div>
    </div>

    <!-- Leaderboard -->
    <main class="container">
        <div class="leaderboard-container">
            <div class="leaderboard-header">
                <div class="header-item">Rank</div>
                <div class="header-item">Developer</div>
                <div class="header-item">Reputation</div>
                <div class="header-item">Solutions</div>
                <div class="header-item">Bugs</div>
            </div>
            <div id="leaderboard-body">
                <?php if (!empty($leaderboard)): ?>
                    <?php foreach ($leaderboard as $index => $user): ?>
                        <?php include 'leaderboard_row.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-trophy"></i>
                    <h3>No activity in this period</h3>
                    <p>Be the first to solve bugs and earn reputation points!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Leaderboards -->
        <section class="category-leaderboards">
            <div class="section-title">
                <h2>Category Leaders</h2>
                <p>Top contributors in popular technologies</p>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $categoryName => $topUsers): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <i class="category-icon fas fa-code"></i>
                            <h3 class="category-title"><?php echo htmlspecialchars($categoryName); ?></h3>
                        </div>
                        <?php if (!empty($topUsers)): ?>
                            <ul class="top-user-list">
                                <?php foreach ($topUsers as $index => $topUser): ?>
                                    <li class="top-user-item">
                                        <div class="top-user-rank"><?php echo $index + 1; ?></div>
                                        <div class="top-user-avatar" style="background: <?php echo htmlspecialchars($topUser['avatar_color']); ?>">
                                            <?php if (!empty($topUser['profile_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars($topUser['profile_picture']); ?>" alt="<?php echo htmlspecialchars($topUser['name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($topUser['name'], 0, 2)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="top-user-details">
                                            <div class="top-user-name">
                                                <a href="profile.php?id=<?php echo $topUser['id']; ?>"><?php echo htmlspecialchars($topUser['name']); ?></a>
                                            </div>
                                        </div>
                                        <div class="top-user-score">
                                            <?php echo number_format($topUser['score']); ?> pts
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="no-data" style="padding: 20px 0;">
                                <p>No leaders in this category yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php include 'footer.html'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timeFilters = document.querySelectorAll('.time-filter');
            const leaderboardBody = document.getElementById('leaderboard-body');

            timeFilters.forEach(filter => {
                filter.addEventListener('click', function(e) {
                    e.preventDefault();

                    const period = this.href.split('period=')[1];
                    const url = this.href;

                    // Update active class
                    timeFilters.forEach(f => f.classList.remove('active'));
                    this.classList.add('active');

                    // Show loading state
                    leaderboardBody.innerHTML = `
                        <div class="no-data" style="padding: 80px 20px;">
                            <i class="fas fa-spinner fa-spin"></i>
                            <h3>Loading...</h3>
                        </div>
                    `;

                    // Fetch new data
                    fetch('leaderboard.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `get_leaderboard_data=true&period=${period}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            leaderboardBody.innerHTML = data.html;
                            // Update URL without reloading
                            history.pushState({period: period}, '', url);
                        } else {
                            leaderboardBody.innerHTML = `
                                <div class="no-data">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error loading data</h3>
                                    <p>Please try again later.</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        leaderboardBody.innerHTML = `
                            <div class="no-data">
                                <i class="fas fa-exclamation-circle"></i>
                                <h3>Network Error</h3>
                                <p>Could not fetch leaderboard data.</p>
                            </div>
                        `;
                    });
                });
            });

            // Handle back/forward browser buttons
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.period) {
                    const tabToActivate = document.querySelector(`.time-filter[href="?period=${e.state.period}"]`);
                    if (tabToActivate) {
                        tabToActivate.click();
                    }
                } else {
                    // Handle initial state if needed
                    const initialTab = document.querySelector('.time-filter[href="?period=all"]');
                    if(initialTab) initialTab.click();
                }
            });
        });
    </script>
</body>
</html>
