<?php
// solution_comment_card.php - Renders a single comment for a solution.
if (!function_exists('timeAgo')) {
    require_once __DIR__ . '/includes/utils.php';
}

$solution_id = $solution['id'] ?? $_GET['id']; // Ensure solution_id is available
?>
<style>
    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .comment-actions {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }
    .comment-action-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .comment-action-btn:hover {
        color: var(--accent-primary);
    }
</style>
<?php if (isset($comment) && is_array($comment)): ?>
<div class="comment" id="comment-<?php echo $comment['id']; ?>">
    <div class="comment-header">
        <?php include __DIR__ . '/user_info_comment.php'; ?>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']): ?>
        <div class="comment-actions">
            <button class="comment-action-btn edit-comment-btn" data-comment-id="<?php echo $comment['id']; ?>"><i class="fas fa-edit"></i></button>
            <button class="comment-action-btn delete-comment-btn" data-comment-id="<?php echo $comment['id']; ?>"><i class="fas fa-trash"></i></button>
        </div>
        <?php endif; ?>
    </div>
    <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <button class="reply-btn" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)"><i class="fas fa-reply"></i> Reply</button>
    <div class="reply-form" id="reply-form-<?php echo $comment['id']; ?>">
        <form class="comment-submit-form" method="POST" action="solution-details.php?id=<?php echo $solution_id; ?>#comments">
            <input type="hidden" name="parent_comment_id" value="<?php echo $comment['id']; ?>">
            <div class="form-group">
                <textarea name="comment_text" class="form-control" placeholder="Write your reply..." required></textarea>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
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
            <i class="fas fa-chevron-down"></i> <span id="reply-count-<?php echo $comment['id']; ?>">View <?php echo count($comment['replies']); ?> replies</span>
        </button>
        <div class="replies-list" id="replies-list-<?php echo $comment['id']; ?>" style="display: none;">
            <div id="comment-<?php echo $comment['id']; ?>-replies-container">
                <?php foreach ($comment['replies'] as $reply): include __DIR__ . '/solution_reply_card.php'; endforeach; ?>
            </div>
        </div>
        <?php else: // This block is for when there are no initial replies ?>
            <div id="comment-<?php echo $comment['id']; ?>-replies-container" class="replies-list" style="display: none;">
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>