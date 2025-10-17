<?php
/**
 * Reusable Page Header Component
 * @var string $pageTitle The main title for the header.
 * @var string $pageSubtitle The subtitle text for the header.
 */
?>
<section class="page-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p><?php echo htmlspecialchars($pageSubtitle); ?></p>
    </div>
</section>