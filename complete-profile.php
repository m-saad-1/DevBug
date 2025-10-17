<?php
session_start();
require_once 'config/database.php';

// Redirect if no pending registration
if (!isset($_SESSION['pending_user_id'])) {
    header("Location: auth.php");
    exit();
}

$errors = [];
$success_msg = "";

// Handle "skip for now" action
if (isset($_GET['skip_profile']) && $_GET['skip_profile'] == '1') {
    // Set session variables to log the user in
    $_SESSION['user_id'] = $_SESSION['pending_user_id'];
    $_SESSION['user_name'] = $_SESSION['pending_name'];
    $_SESSION['user_email'] = $_SESSION['pending_email'];
    $_SESSION['username'] = $_SESSION['pending_username'];
    
    // Fetch other essential user data that might be missing
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION = array_merge($_SESSION, $user);
    }

    // Clear pending registration data
    unset($_SESSION['pending_user_id'], $_SESSION['pending_name'], $_SESSION['pending_username'], $_SESSION['pending_email']);
    
    header("Location: dashboard.php?welcome=1");
    exit();
}

// Handle profile completion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $bio = trim($_POST['bio']);
    $location = trim($_POST['location']);
    $company = trim($_POST['company']);
    $website = trim($_POST['website']);
    $github = trim($_POST['github']);
    $twitter = trim($_POST['twitter']);
    $linkedin = trim($_POST['linkedin']);
    $skills = trim($_POST['skills']);
    
    // Handle profile picture upload
    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $new_filename = 'user_' . $_SESSION['pending_user_id'] . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                $profile_picture_path = $target_path;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        } else {
            $errors[] = "Invalid file type. Please upload JPG, PNG, or GIF images only.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Update user profile
            $sql = "UPDATE users SET 
                    title = ?, bio = ?, location = ?, company = ?, website = ?, 
                    github = ?, twitter = ?, linkedin = ?, skills = ?, profile_picture = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title, $bio, $location, $company, $website, 
                $github, $twitter, $linkedin, $skills, $profile_picture_path,
                $_SESSION['pending_user_id']
            ]);
            
            // Set session variables for the newly registered user
            $_SESSION['user_id'] = $_SESSION['pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_name'];
            $_SESSION['user_email'] = $_SESSION['pending_email'];
            $_SESSION['username'] = $_SESSION['pending_username'];
            $_SESSION['user_title'] = $title;
            $_SESSION['bio'] = $bio;
            $_SESSION['location'] = $location;
            $_SESSION['company'] = $company;
            $_SESSION['website'] = $website;
            $_SESSION['github'] = $github;
            $_SESSION['twitter'] = $twitter;
            $_SESSION['linkedin'] = $linkedin;
            $_SESSION['skills'] = $skills;
            $_SESSION['profile_picture'] = $profile_picture_path;
            
            // Clear pending registration data
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_name']);
            unset($_SESSION['pending_username']);
            unset($_SESSION['pending_email']);
            
            // Redirect to dashboard
            header("Location: dashboard.php?welcome=1");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "Failed to save profile: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - DevBug</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-completion-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 0;
            min-height: 100vh;
        }

        .profile-completion-container {
            max-width: 800px;
            width: 100%;
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            position: relative;
        }

        .profile-completion-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
        }

        .completion-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .completion-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-tertiary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .completion-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-secondary);
            border-radius: 3px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            width: 50%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: var(--accent-primary);
        }

        .profile-picture-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: <?php echo $_SESSION['avatar_color'] ?? '#6366f1'; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 3rem;
            position: relative;
            overflow: hidden;
            border: 4px solid var(--border);
        }

        .profile-picture-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-input {
            display: none;
        }

        .profile-picture-btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .profile-picture-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .input-with-icon input, .input-with-icon textarea {
            padding-left: 45px;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-submit {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }

        .form-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .skip-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .skip-link:hover {
            color: var(--accent-primary);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateX(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification.error {
            background: var(--danger);
        }

        @media (max-width: 768px) {
            .profile-completion-container {
                padding: 30px 20px;
                margin: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .completion-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Temporarily set session variables for the header to recognize the user
    $is_temp_session = false;
    if (isset($_SESSION['pending_user_id']) && !isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['user_name'] = $_SESSION['pending_name'];
        $_SESSION['user_email'] = $_SESSION['pending_email'];
        $_SESSION['username'] = $_SESSION['pending_username'];
        $is_temp_session = true;
    }

    include(__DIR__ . '/Components/header.php'); 
    
    ?>

    <!-- Profile Completion Section -->
    <section class="profile-completion-section">
        <div class="container">
            <div class="profile-completion-container">
                <div class="completion-header">
                    <h1>Complete Your Profile</h1>
                    <p>Tell us more about yourself to get the most out of DevBug</p>
                </div>

                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="notification error show">
                        <?php echo $errors[0]; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="loader-form">
                    <!-- Profile Picture Section -->
                    <div class="profile-picture-section">
                        <div class="profile-picture-preview" id="profilePicturePreview">
                            <?php echo strtoupper(substr($_SESSION['pending_name'], 0, 2)); ?>
                        </div>
                        <input type="file" id="profilePictureInput" name="profile_picture" class="profile-picture-input" accept="image/*">
                        <button type="button" class="profile-picture-btn" onclick="document.getElementById('profilePictureInput').click()">
                            <i class="fas fa-upload"></i> Upload Profile Picture
                        </button>
                        <small style="color: var(--text-muted); display: block; margin-top: 10px;">Optional - JPG, PNG, or GIF (max 5MB)</small>
                    </div>

                    <!-- Basic Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Basic Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title">Professional Title</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" id="title" name="title" class="form-control" placeholder="e.g., Full Stack Developer" value="Developer">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="location">Location</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" id="location" name="location" class="form-control" placeholder="e.g., San Francisco, CA">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio" class="form-control" placeholder="Tell us about yourself, your experience, and what you're passionate about..."></textarea>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-building"></i> Professional Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="company">Company/Organization</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="company" name="company" class="form-control" placeholder="Where do you work?">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="website">Website/Blog</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-globe"></i>
                                    <input type="url" id="website" name="website" class="form-control" placeholder="https://yourwebsite.com">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="form-section">
                        <h3><i class="fas fa-share-alt"></i> Social Links</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="github">GitHub Username</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-github"></i>
                                    <input type="text" id="github" name="github" class="form-control" placeholder="your-github-username">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="twitter">Twitter Handle</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-twitter"></i>
                                    <input type="text" id="twitter" name="twitter" class="form-control" placeholder="@yourusername">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="linkedin">LinkedIn URL</label>
                                <div class="input-with-icon">
                                    <i class="fab fa-linkedin"></i>
                                    <input type="url" id="linkedin" name="linkedin" class="form-control" placeholder="https://linkedin.com/in/yourprofile">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <div class="form-section">
                        <h3><i class="fas fa-code"></i> Skills & Technologies</h3>
                        <div class="form-group">
                            <label for="skills">Your Skills</label>
                            <div class="input-with-icon">
                                <i class="fas fa-tags"></i>
                                <input type="text" id="skills" name="skills" class="form-control" placeholder="JavaScript, React, Node.js, Python (comma separated)">
                            </div>
                            <small style="color: var(--text-muted);">Separate skills with commas</small>
                        </div>
                    </div>

                    <button type="submit" class="form-submit">
                        <i class="fas fa-rocket"></i> Complete Profile & Get Started
                    </button>

                    <a href="dashboard.php?skip_profile=1" class="skip-link">Skip for now, I'll complete this later</a>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profilePictureInput = document.getElementById('profilePictureInput');
            const profilePicturePreview = document.getElementById('profilePicturePreview');

            // Handle profile picture preview
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicturePreview.innerHTML = `<img src="${e.target.result}" alt="Profile Picture Preview">`;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Remove notifications after 5 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            });

            // Form submission loader
            const formsWithLoader = document.querySelectorAll('.loader-form');
            formsWithLoader.forEach(form => {
                form.addEventListener('submit', function() {
                    const loader = document.getElementById('universal-loader');
                    if (loader) {
                        loader.style.display = 'flex';
                        loader.style.opacity = '1';
                    }
                });
            });
        });
    </script>
</body>
</html>