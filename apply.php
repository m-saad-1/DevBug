<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$job_title = isset($_GET['job']) ? htmlspecialchars($_GET['job']) : 'this position';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo $job_title; ?> - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .apply-content {
            padding: 80px 0;
        }
        .coming-soon {
            text-align: center;
            padding: 100px 20px;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            max-width: 800px;
            margin: 0 auto;
        }
        .coming-soon i {
            font-size: 4rem;
            color: var(--accent-primary);
            margin-bottom: 25px;
        }
        .coming-soon h2 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        .coming-soon p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            display: inline-flex; /* Use flexbox for alignment */
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "Apply for " . $job_title;
    $pageSubtitle = "We're excited for your interest. Our application system is currently under construction.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="apply-content">
            <div class="coming-soon">
                <i class="fas fa-tools"></i>
                <h2>Application System Coming Soon!</h2>
                <p>We're putting the finishing touches on our new application portal. In the meantime, you can send your resume and a cover letter for the "<?php echo $job_title; ?>" position to our careers email.</p>
                <a href="mailto:careers@devbug.com?subject=Application for <?php echo rawurlencode($job_title); ?>" class="btn btn-primary">
                    Email Us Your Application
                </a>
            </div>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>
</body>
</html>