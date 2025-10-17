<?php
// solution_reply_card.php - Renders a single reply for a solution comment.
if (!function_exists('timeAgo')) {
    require_once __DIR__ . '/includes/utils.php';
}

// When a new reply is created via AJAX, the data is passed in $new_reply_for_card.
// We assign it to $reply to make the component work in both contexts (page load and AJAX).
if (isset($new_reply_for_card)) {
    $reply = $new_reply_for_card;
}
?>
<div class="comment-reply" id="reply-<?php echo $reply['id']; ?>">
    <div class="comment-header">
        <div class="user-info">
            <a href="profile.php?id=<?php echo $reply['user_id']; ?>" class="user-avatar" style="background: <?php echo htmlspecialchars($reply['avatar_color'] ?? '#6366f1'); ?>; width: 40px; height: 40px; font-size: 0.9rem;"><?php echo strtoupper(substr($reply['user_name'], 0, 2)); ?></a>
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