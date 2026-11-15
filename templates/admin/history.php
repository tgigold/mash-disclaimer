<?php

declare(strict_types=1);

/**
 * @var list<array{created_at: string, username: string|null, action: string, object_type: string|null, object_id: string|null, details: array<string, mixed>}> $entries
 * @var int $page
 * @var int $pages
 */

// Readable labels for the stored action keys; unknown keys are shown as they are.
$labels = [
    'setup.completed' => 'Completed the setup',
    'user.created' => 'Added admin',
    'user.login' => 'Signed in',
    'user.logout' => 'Signed out',
    'user.password_set' => 'Set new password for',
    'user.deactivated' => 'Deactivated',
    'user.activated' => 'Reactivated',
    'user.deleted' => 'Deleted own account',
    'sample_data.loaded' => 'Loaded sample vehicles',
    'sample_data.removed' => 'Removed sample vehicles',
    'migrations.applied' => 'Applied database updates',
    'vehicle.created' => 'Created vehicle',
    'vehicle.updated' => 'Edited vehicle',
    'vehicle.live' => 'Put live',
    'vehicle.withdrawn' => 'Withdrew',
    'vehicle.reset' => 'Reset to factory values',
    'vehicle.deleted' => 'Deleted vehicle',
    'texts.regenerated' => 'Regenerated live texts',
    'import.uploaded' => 'Uploaded import file',
    'import.started' => 'Started import',
    'import.applied' => 'Applied import',
    'import.aborted' => 'Aborted import',
    'frontend.locked' => 'Paused the public search',
    'frontend.released' => 'Released the public search',
];

$describe = static function (array $details): string {
    $parts = [];
    foreach ($details as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $text = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            $parts[] = $key === 'username' ? $text : $key . ': ' . $text;
        }
    }

    return implode(' · ', $parts);
};
?>
<section class="admin-card">
    <div class="history-head">
        <p class="text-body-secondary mb-0">Entries are kept for 24 months; shortened IP addresses for 90 days.</p>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/updates"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Updates</a>
    </div>
<?php if ($entries === []): ?>
    <p class="mb-0">Nothing has happened yet.</p>
<?php else: ?>
    <table class="table admin-table mb-0">
        <thead>
            <tr><th scope="col">When (UTC)</th><th scope="col">Who</th><th scope="col">What</th></tr>
        </thead>
        <tbody>
<?php foreach ($entries as $entry): ?>
            <tr>
                <td class="text-nowrap"><?= e((new \DateTimeImmutable($entry['created_at']))->format('j M Y, H:i')) ?></td>
                <td><?= $entry['username'] !== null ? e($entry['username']) : '<span class="text-body-secondary">system or deleted account</span>' ?></td>
                <td>
                    <?= e($labels[$entry['action']] ?? $entry['action']) ?>
<?php $detail = $describe($entry['details']); if ($detail !== ''): ?>
                    <span class="text-body-secondary"><?= e($detail) ?></span>
<?php endif ?>
                </td>
            </tr>
<?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</section>

<?php if ($pages > 1): ?>
<nav class="admin-pager" aria-label="Pages">
<?php if ($page > 1): ?>
    <a class="btn btn-outline-secondary btn-sm" href="/admin/history?page=<?= e($page - 1) ?>">Newer</a>
<?php endif ?>
    <span>Page <?= e($page) ?> of <?= e($pages) ?></span>
<?php if ($page < $pages): ?>
    <a class="btn btn-outline-secondary btn-sm" href="/admin/history?page=<?= e($page + 1) ?>">Older</a>
<?php endif ?>
</nav>
<?php endif ?>
