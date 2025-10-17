<?php
/**
 * Component: Renders a single reply card for the bug details page.
 *
 * Expects a $reply variable to be in scope.
 */
if (!function_exists('timeAgo')) {
    require_once __DIR__ . '/../includes/utils.php';
}

// When a new reply is created via AJAX, the data is passed in $new_reply_for_card.
if (isset($new_reply_for_card)) {
    $reply = $new_reply_for_card;
}
?>
<style>
    /* Scoped styles for reply card to prevent conflicts */
    .comment-reply .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center; /* Aligns items vertically */
    }
    .comment-reply .comment-actions {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }
    .comment-reply .comment-action-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.85rem;
        transition: var(--transition);
        padding: 5px;
    }
</style>
<div class="comment-reply" id="reply-<?php echo $reply['id']; ?>">
    <div class="comment-header">
        <div class="user-info">
            <a href="profile.php?id=<?php echo $reply['user_id']; ?>" class="user-avatar" style="background: <?php echo htmlspecialchars($reply['avatar_color'] ?? '#6366f1'); ?>">
                <?php if (!empty($reply['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($reply['profile_picture']); ?>" alt="<?php echo htmlspecialchars($reply['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($reply['user_name'], 0, 2)); ?>
                <?php endif; ?>
            </a>
            <div class="user-details">
                <a href="profile.php?id=<?php echo $reply['user_id']; ?>" class="user-name"><?php echo htmlspecialchars($reply['user_name']); ?></a>
                <span class="post-time"><?php echo timeAgo($reply['created_at']); ?></span>
            </div>
        </div>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $reply['user_id']): ?>
            <div class="comment-actions">
                <button class="comment-action-btn edit-reply-btn" data-reply-id="<?php echo $reply['id']; ?>"><i class="fas fa-edit"></i></button>
                <button class="comment-action-btn delete-reply-btn" data-reply-id="<?php echo $reply['id']; ?>"><i class="fas fa-trash"></i></button>
            </div>
        <?php endif; ?>
    </div>
    <div class="comment-text"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
</div>