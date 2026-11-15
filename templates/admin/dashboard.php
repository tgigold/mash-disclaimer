<?php

declare(strict_types=1);

/**
 * @var array{vehicles: int, live: int} $counts
 * @var int $activeUsers
 * @var list<string> $pending
 */
?>
<?php if ($pending !== []): ?>
<div class="alert alert-warning" role="alert">
    <?= e(count($pending)) ?> database update<?= count($pending) === 1 ? '' : 's' ?> waiting.
    <a href="/admin/updates">Review and apply</a>
</div>
<?php endif ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?= e($counts['vehicles']) ?></span>
        <span class="stat-label">Vehicles</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= e($counts['live']) ?></span>
        <span class="stat-label">Live</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= e($counts['vehicles'] - $counts['live']) ?></span>
        <span class="stat-label">Draft</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= e($activeUsers) ?></span>
        <span class="stat-label">Active admins</span>
    </div>
</div>

<section class="admin-card">
    <h2>Vehicles</h2>
    <p class="text-body-secondary mb-0">The vehicle list with editing, going live and resetting comes in the next update.</p>
</section>
