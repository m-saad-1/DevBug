<?php
// Components/solution_card.php

// Ensure required variables are available
global $pdo, $bug;

if (!function_exists('detectLanguage')) {
    require_once __DIR__ . '/../includes/utils.php';
}
?>
<div class="solution-card <?php echo $solution['is_approved'] ? 'approved' : ''; ?>">
    <!-- User Info -->
    <div class="solution-user-info">
        <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="solution-user-avatar" style="background: <?php echo $solution['avatar_color'] ?? '#6366f1'; ?>; overflow: hidden;">
            <?php if (!empty($solution['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($solution['profile_picture']); ?>" alt="<?php echo htmlspecialchars($solution['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <?php echo strtoupper(substr($solution['user_name'], 0, 2)); ?>
            <?php endif; ?>
        </a>
        <div class="solution-user-details">
            <a href="profile.php?id=<?php echo $solution['user_id']; ?>" class="solution-user-name"><?php echo htmlspecialchars($solution['user_name']); ?></a>
            <div class="solution-meta">
                <span><i class="far fa-clock"></i> <?php echo timeAgo($solution['created_at']); ?></span>
                <span><i class="far fa-eye"></i> <?php echo $solution['views_count'] ?? 0; ?> Views</span>
                <span class="solution-stat">
                    <i class="fas fa-thumbs-up" style="color: var(--success);"></i>
                    <span class="stat-value"><?php echo $solution['upvotes']; ?></span> Upvotes
                </span>
                <span class="solution-stat">
                    <i class="fas fa-bookmark" style="color: var(--accent-primary);"></i>
                    <span class="stat-value"><?php echo $solution['saves_count'] ?? 0; ?></span> Saves
                </span>
            </div>
        </div>
    </div>

    <!-- Solution Description -->
    <div class="solution-description">
        <?php echo nl2br(htmlspecialchars($solution['content'])); ?>
    </div>

    <!-- Code Snippet -->
    <?php if (!empty($solution['code_snippet'])): ?>
    <div class="solution-code" id="solution-code-<?php echo $solution['id']; ?>">
        <pre><code class="language-<?php echo detectLanguage($bug['tags']); ?>"><?php echo htmlspecialchars(substr($solution['code_snippet'], 0, 800)); ?></code></pre>
        <?php if (strlen($solution['code_snippet']) > 800): ?>
            <button class="view-code-toggle" 
                    data-code="<?php echo htmlspecialchars(json_encode($solution['code_snippet']), ENT_QUOTES, 'UTF-8'); ?>"
                    data-lang="<?php echo detectLanguage($bug['tags']); ?>"
                    onclick="openCodeModal(this)">
                <i class="fas fa-expand"></i> View Full Code
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($solution['images']) || !empty($solution['files'])): ?>
    <div class="solution-attachments">
        <?php if (!empty($solution['images'])): ?>
        <div class="solution-images">
            <?php foreach ($solution['images'] as $index => $image_path): ?>
                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Solution screenshot" class="solution-image" 
                     onclick="openSolutionImageModal('<?php echo htmlspecialchars($image_path); ?>', <?php echo $index; ?>, <?php echo htmlspecialchars(json_encode($solution['images'])); ?>)">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($solution['files'])): ?>
        <div class="solution-files">
            <?php foreach ($solution['files'] as $file): ?>
                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="solution-file">
                    <i class="fas fa-file"></i>
                    <span><?php echo htmlspecialchars($file['original_name']); ?></span>
                    <i class="fas fa-download"></i>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Footer with Actions -->
    <div class="solution-footer">
        <div class="solution-actions-full">
            <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="solution-action-btn">
                <i class="far fa-comment"></i> Comment
            </a>
            <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="solution-action-btn">
                <i class="fas fa-external-link-alt"></i> View Full
            </a>
        </div>
        <a href="solution-details.php?id=<?php echo $solution['id']; ?>" class="view-full-link">
            View Details <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <!-- Approve Button (for bug owner) -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $bug['user_id'] && !$solution['is_approved'] && $bug['status'] != 'solved'): ?>
    <div style="margin-top: 15px; text-align: right;">
        <form method="POST" action="post-details.php?id=<?php echo $bug['id']; ?>#solutions" style="display: inline;">
            <input type="hidden" name="solution_id" value="<?php echo $solution['id']; ?>">
            <button type="submit" name="approve_solution" class="btn btn-primary" onclick="return confirm('Are you sure you want to approve this solution?')">
                <i class="fas fa-check"></i> Approve Solution
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>