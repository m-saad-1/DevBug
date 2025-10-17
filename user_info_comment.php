<div class="user-info">
    <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="user-avatar" style="background: <?php echo htmlspecialchars($comment['avatar_color'] ?? '#6366f1'); ?>; overflow: hidden;">
        <?php if (!empty($comment['profile_picture'])): ?>
            <img src="<?php echo htmlspecialchars($comment['profile_picture']); ?>" alt="<?php echo htmlspecialchars($comment['user_name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
            <?php echo strtoupper(substr($comment['user_name'], 0, 2)); ?>
        <?php endif; ?>
    </a>
    <div class="user-details">
        <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="user-name"><?php echo htmlspecialchars($comment['user_name']); ?></a>
        <span class="post-time"><?php echo timeAgo($comment['created_at']); ?></span>
    </div>
</div>