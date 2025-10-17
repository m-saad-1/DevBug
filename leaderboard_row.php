<?php
// Components/leaderboard_row.php
// Renders a single row for the leaderboard.
// Expects $user and $index to be defined.
?>
<div class="leaderboard-row">
    <div class="rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
    <div class="user-info">
        <div class="user-avatar" style="background: <?php echo htmlspecialchars($user['avatar_color']); ?>; overflow: hidden;">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>'s Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
            <?php endif; ?>
        </div>
        <div class="user-details">
            <div class="user-name"><a href="profile.php?id=<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></a></div>
            <div class="user-title"><?php echo htmlspecialchars($user['title'] ?? ''); ?></div>
        </div>
    </div>
    <div class="stats-container">
        <div class="stats-value" data-label="Reputation"><?php echo number_format($user['reputation']); ?></div>
        <div class="stats-value" data-label="Solutions"><?php echo $user['solutions_count']; ?></div>
        <div class="stats-value" data-label="Bugs"><?php echo $user['bugs_count']; ?></div>
    </div>
</div>