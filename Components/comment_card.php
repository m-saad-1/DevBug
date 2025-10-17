<?php
// Ensure utils.php is included for timeAgo function
if (!function_exists('timeAgo')) {
    require_once __DIR__ . '/../utils.php';
}

// When a new comment is created via AJAX, the data is passed in $new_comment_for_card.
if (isset($new_comment_for_card)) {
    $comment = $new_comment_for_card;
}

$bug_id = $bug['id'] ?? $_GET['id']; // Ensure bug_id is available
?>
<style>
    /* Scoped styles for comment card to prevent conflicts */
    .comment .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center; /* Aligns items vertically */
    }
    .comment .comment-actions {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }
    .comment .comment-action-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.85rem;
        transition: var(--transition);
        padding: 5px;
    }
</style>
<div class="comment" id="comment-<?php echo $comment['id']; ?>">
    <div class="comment-header">
        <div class="user-info">
            <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="user-avatar" style="background: <?php echo htmlspecialchars($comment['avatar_color'] ?? '#6366f1'); ?>">
                <?php if (!empty($comment['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['profile_picture']); ?>" alt="<?php echo htmlspecialchars($comment['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($comment['user_name'], 0, 2)); ?>
                <?php endif; ?>
            </a>
            <div class="user-details">
                <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="user-name"><?php echo htmlspecialchars($comment['user_name']); ?></a>
                <span class="post-time"><?php echo !empty($comment['created_at']) ? timeAgo($comment['created_at']) : 'Recently'; ?></span>
            </div>
        </div>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']): ?>
            <div class="comment-actions">
                <button class="comment-action-btn edit-comment-btn" data-comment-id="<?php echo $comment['id']; ?>"><i class="fas fa-edit"></i></button>
                <button class="comment-action-btn delete-comment-btn" data-comment-id="<?php echo $comment['id']; ?>"><i class="fas fa-trash"></i></button>
            </div>
        <?php endif; ?>
    </div>
    <div class="comment-text">
        <?php 
        // Handle different field names for comment content ('comment_text' vs 'content')
        $comment_content = $comment['comment_text'] ?? $comment['content'] ?? '';
        echo nl2br(htmlspecialchars($comment_content)); 
        ?>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <button class="reply-btn" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)"><i class="fas fa-reply"></i> Reply</button>
    <div class="reply-form" id="reply-form-<?php echo $comment['id']; ?>">
        <?php
        // Determine the correct action URL based on the current page
        $current_page = basename($_SERVER['PHP_SELF']);
        $action_id = ($current_page === 'solution-details.php' && isset($solution)) ? $solution['id'] : $bug_id;
        $action_url = "{$current_page}?id={$action_id}#comments";
        ?>
        <form class="comment-submit-form" method="POST" action="<?php echo $action_url; ?>">
            <input type="hidden" name="parent_comment_id" value="<?php echo $comment['id']; ?>">
            <div class="form-group">
                <textarea name="comment_text" class="form-control" placeholder="Write your reply..." required></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="submit_comment" class="btn btn-primary">Post Reply</button>
                <input type="hidden" name="submit_comment_ajax" value="1">
                <button type="button" class="btn btn-secondary" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">Cancel</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="replies-container">
        <?php if (!empty($comment['replies'])): ?>
            <button class="view-replies-btn" onclick="toggleReplies(<?php echo $comment['id']; ?>)">
                <i class="fas fa-chevron-down"></i> View <?php echo count($comment['replies']); ?> replies
            </button>
            <div class="replies-list" id="replies-list-<?php echo $comment['id']; ?>" style="display: none;">
                <?php 
                foreach ($comment['replies'] as $reply): include __DIR__ . '/reply_card.php'; endforeach; 
                unset($reply); // Unset reply to prevent scope bleed in AJAX calls
                ?>
            </div>
        <?php endif; ?>
        <!-- This container is for dynamically added replies via AJAX -->
        <div id="comment-<?php echo $comment['id']; ?>-replies-container" class="replies-list" style="<?php if (!empty($comment['replies'])) echo 'display: none;'; else echo 'display: block;'; ?>">
        </div>
    </div>
</div>