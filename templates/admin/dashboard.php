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
    <p>Edit names and values, put vehicles live or withdraw them, reset edited values to the factory values.</p>
    <a class="btn btn-primary" href="/admin/vehicles">Open vehicles</a>
    <a class="btn btn-outline-secondary" href="/admin/vehicles/new">New vehicle</a>
</section>
