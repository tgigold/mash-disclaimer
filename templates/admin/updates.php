<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;

/**
 * @var list<string> $applied
 * @var list<string> $pending
 * @var string|null $error
 * @var string $csrfToken
 */
?>
<?php if ($error !== null): ?>
<div class="alert alert-danger" role="alert">
    <p class="fw-semibold mb-1">The update stopped.</p>
    <p class="mb-1"><?= e($error) ?></p>
    <p class="mb-0">A backup was written to <code>data/backups/</code> before it started.</p>
</div>
<?php endif ?>

<section class="admin-card">
    <h2>Database</h2>
<?php if ($pending === []): ?>
    <p class="mb-0"><i class="bi bi-check-circle" aria-hidden="true"></i> Up to date – <?= e(count($applied)) ?> updates applied.</p>
<?php else: ?>
    <p>These updates are waiting:</p>
    <ul>
<?php foreach ($pending as $version): ?>
        <li><code><?= e($version) ?></code></li>
<?php endforeach ?>
    </ul>
    <form method="post" action="/admin/updates">
        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
        <button class="btn btn-primary" type="submit">Back up and apply</button>
    </form>
<?php endif ?>
</section>

<section class="admin-card">
    <h2>Disclaimer texts</h2>
    <p>After a change to <code>config/disclaimer-templates.php</code>, rebuild every live text from its name, values and the new template. Values and the “edited” marks stay as they are.</p>
    <form method="post" action="/admin/updates/regenerate">
        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Regenerate texts</button>
    </form>
</section>

<?php if ($applied !== []): ?>
<section class="admin-card">
    <h2>Applied</h2>
    <ul class="mb-0">
<?php foreach ($applied as $version): ?>
        <li><code><?= e($version) ?></code></li>
<?php endforeach ?>
    </ul>
</section>
<?php endif ?>
