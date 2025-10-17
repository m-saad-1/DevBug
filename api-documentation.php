<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - DevBug</title>
    <link rel="stylesheet" href="/devbug/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/devbug/css/page-header.css">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
        /* Reusing styles from Documentation.php for consistency */
        .docs-content { padding: 80px 40px; max-width: 1400px; margin: 0 auto; }
        .docs-layout { display: grid; grid-template-columns: 300px 1fr; gap: 60px; align-items: flex-start; }
        .docs-nav { background: var(--bg-card); border-radius: 16px; padding: 30px; border: 1px solid var(--border); }
        .docs-nav h3 { font-size: 1.2rem; margin-bottom: 20px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .nav-section { margin-bottom: 25px; }
        .nav-section h4 { font-size: 1rem; margin-bottom: 15px; color: var(--accent-primary); font-weight: 600; }
        .nav-links { list-style: none; padding: 0; margin: 0; }
        .nav-links li { margin-bottom: 8px; }
        .nav-links a { color: var(--text-secondary); text-decoration: none; padding: 8px 12px; border-radius: 6px; display: block; transition: var(--transition); font-size: 0.95rem; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-primary); background: rgba(99, 102, 241, 0.1); }
        .docs-sidebar::-webkit-scrollbar {
            display: none; /* for Chrome, Safari, and Opera */
        }
        .docs-sidebar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .docs-sidebar { position: sticky; top: 100px; height: calc(100vh - 120px); overflow-y: auto; }
        .docs-section { margin-bottom: 60px; scroll-margin-top: 100px; }
        .docs-section h2 { font-size: 2.2rem; margin-bottom: 25px; color: var(--text-primary); padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        .docs-section h3 { font-size: 1.5rem; margin: 35px 0 20px; color: var(--text-primary); }
        .docs-section p { color: var(--text-secondary); line-height: 1.8; margin-bottom: 20px; font-size: 1.05rem; }
        .code-block { background: rgba(0, 0, 0, 0.3); border-radius: 8px; padding: 25px; margin: 25px 0; overflow-x: auto; border: 1px solid var(--border); font-family: 'Fira Code', monospace; font-size: 0.9rem; line-height: 1.5; }
        .code-block pre { margin: 0; background: transparent !important; }
        .code-block code { background: transparent !important; padding: 0 !important; }
        .info-box { background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--accent-primary); padding: 25px; border-radius: 8px; margin: 30px 0; }
        .info-box h4 { color: var(--accent-primary); margin-bottom: 10px; font-size: 1.2rem; }
        .info-box p { margin-bottom: 0; color: var(--text-secondary); }
        .endpoint { background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border); margin-bottom: 25px; }
        .endpoint-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .endpoint-method { padding: 6px 12px; border-radius: 6px; font-weight: 700; font-family: 'Fira Code', monospace; }
        .method-get { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .method-post { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .method-put { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .method-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .endpoint-path { font-family: 'Fira Code', monospace; font-size: 1.1rem; color: var(--text-primary); }
        .param-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .param-table th, .param-table td { border: 1px solid var(--border); padding: 12px; text-align: left; }
        .param-table th { background: var(--bg-secondary); color: var(--text-primary); }
        .param-table td { color: var(--text-secondary); }
        .param-table code { background: var(--bg-secondary); padding: 3px 6px; border-radius: 4px; font-family: 'Fira Code', monospace; }
        @media (max-width: 768px) {
            .docs-section h2 { font-size: 1.8rem; }
            .docs-section h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/Components/header.php'); ?>

    <?php 
    $pageTitle = "API Documentation";
    $pageSubtitle = "Integrate with DevBug using our powerful and easy-to-use REST API.";
    include(__DIR__ . '/Components/page_header.php'); 
    ?>

    <!-- Main Content -->
    <main class="container">
        <div class="docs-content docs-layout">
                <!-- Sidebar Navigation -->
                <aside class="docs-sidebar">
                    <div class="docs-nav">
                        <h3>API Reference</h3>
                        
                        <div class="nav-section">
                            <h4>Getting Started</h4>
                            <ul class="nav-links">
                                <li><a href="#introduction" class="active">Introduction</a></li>
                                <li><a href="#authentication">Authentication</a></li>
                                <li><a href="#rate-limiting">Rate Limiting</a></li>
                            </ul>
                        </div>

                        <div class="nav-section">
                            <h4>Endpoints</h4>
                            <ul class="nav-links">
                                <li><a href="#bugs">Bugs</a></li>
                                <li><a href="#solutions">Solutions</a></li>
                                <li><a href="#users">Users</a></li>
                            </ul>
                        </div>
                    </div>
                </aside>

                <!-- Main Documentation Content -->
                <div class="docs-main">
                    <!-- Introduction Section -->
                    <section id="introduction" class="docs-section">
                        <h2>Introduction</h2>
                        <p>Welcome to the DevBug API! Our API is designed to provide programmatic access to the core features of our platform, allowing you to build integrations, automate workflows, and extend the functionality of DevBug.</p>
                        <p>The API is organized around REST principles, uses JSON for requests and responses, and standard HTTP response codes for errors.</p>
                        <div class="info-box">
                            <h4>Base URL</h4>
                            <p>All API endpoints are relative to the following base URL: <code>https://api.devbug.com/v1</code></p>
                        </div>
                    </section>

                    <!-- Authentication Section -->
                    <section id="authentication" class="docs-section">
                        <h2>Authentication</h2>
                        <p>To use the DevBug API, you need an API key. You can generate and manage your API keys from your user dashboard under the "API Settings" tab.</p>
                        <p>Include your API key in the <code>Authorization</code> header of your requests as a Bearer token.</p>
                        <div class="code-block">
                            <pre><code class="language-bash">Authorization: Bearer YOUR_API_KEY</code></pre>
                        </div>
                    </section>

                    <!-- Rate Limiting Section -->
                    <section id="rate-limiting" class="docs-section">
                        <h2>Rate Limiting</h2>
                        <p>Our API is rate-limited to ensure fair usage and stability. The current rate limit is <strong>1000 requests per hour</strong> per API key. If you exceed this limit, you will receive a <code>429 Too Many Requests</code> response.</p>
                        <p>The following headers are included in every API response to help you track your usage:</p>
                        <ul>
                            <li><code>X-RateLimit-Limit</code>: The total number of requests allowed in the current window.</li>
                            <li><code>X-RateLimit-Remaining</code>: The number of requests remaining in the current window.</li>
                            <li><code>X-RateLimit-Reset</code>: The time (in UTC epoch seconds) when the current window resets.</li>
                        </ul>
                    </section>

                    <!-- Bugs Section -->
                    <section id="bugs" class="docs-section">
                        <h2>Bugs</h2>
                        
                        <h3>Get a list of bugs</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-get">GET</span>
                                <span class="endpoint-path">/bugs</span>
                            </div>
                            <p>Retrieves a paginated list of bug reports. You can filter the results using query parameters.</p>
                            <h4>Query Parameters</h4>
                            <table class="param-table">
                                <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                                <tbody>
                                    <tr><td><code>page</code></td><td>integer</td><td>The page number to retrieve. Default: 1.</td></tr>
                                    <tr><td><code>per_page</code></td><td>integer</td><td>The number of items per page. Default: 20, Max: 100.</td></tr>
                                    <tr><td><code>status</code></td><td>string</td><td>Filter by status (e.g., 'open', 'solved').</td></tr>
                                    <tr><td><code>tags</code></td><td>string</td><td>Comma-separated list of tags to filter by.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h3>Get a single bug</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-get">GET</span>
                                <span class="endpoint-path">/bugs/{id}</span>
                            </div>
                            <p>Retrieves the details of a specific bug report by its ID.</p>
                        </div>

                        <h3>Create a new bug</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-post">POST</span>
                                <span class="endpoint-path">/bugs</span>
                            </div>
                            <p>Creates a new bug report. The request body must be a JSON object with the required fields.</p>
                            <h4>Request Body</h4>
                            <div class="code-block">
                                <pre><code class="language-json">{
    "title": "string (required)",
    "description": "string (required)",
    "code_snippet": "string (optional)",
    "tags": "string (comma-separated, optional)",
    "priority": "string (low, medium, high, critical)"
}</code></pre>
                            </div>
                        </div>
                    </section>

                    <!-- Solutions Section -->
                    <section id="solutions" class="docs-section">
                        <h2>Solutions</h2>
                        
                        <h3>Get solutions for a bug</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-get">GET</span>
                                <span class="endpoint-path">/bugs/{id}/solutions</span>
                            </div>
                            <p>Retrieves a list of solutions for a specific bug report.</p>
                        </div>

                        <h3>Submit a solution</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-post">POST</span>
                                <span class="endpoint-path">/bugs/{id}/solutions</span>
                            </div>
                            <p>Submits a new solution for a specific bug report.</p>
                            <h4>Request Body</h4>
                            <div class="code-block">
                                <pre><code class="language-json">{
    "content": "string (required)",
    "code_snippet": "string (optional)"
}</code></pre>
                            </div>
                        </div>
                    </section>

                    <!-- Users Section -->
                    <section id="users" class="docs-section">
                        <h2>Users</h2>
                        
                        <h3>Get a user profile</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="endpoint-method method-get">GET</span>
                                <span class="endpoint-path">/users/{id}</span>
                            </div>
                            <p>Retrieves the public profile information for a specific user.</p>
                        </div>
                    </section>
                </div>
        </div>
    </main>

    <?php include(__DIR__ . '/footer.html'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize syntax highlighting
            hljs.highlightAll();

            // Smooth scrolling for documentation navigation
            const navLinks = document.querySelectorAll('.nav-links a');
            
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
            const docSections = document.querySelectorAll('.docs-section');
            
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
            
            docSections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>