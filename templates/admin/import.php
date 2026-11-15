<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;

/**
 * @var list<array{id: int, original_name: string, username: string|null, uploaded_at: string, status: string, counts: array<string, int>, size_bytes: int}> $imports
 * @var array<string, string> $errors
 * @var float|int $maxMegabytes
 * @var string $csrfToken
 */

$statusLabels = [
    'uploaded' => 'Uploaded',
    'parsed' => 'In review',
    'applied' => 'Applied',
    'released' => 'Applied, search released',
    'aborted' => 'Aborted',
];
?>
<?php if (isset($errors['file'])): ?>
<div class="alert alert-danger" role="alert"><?= e($errors['file']) ?></div>
<?php endif ?>

<section class="admin-card">
    <h2>Import a manufacturer file</h2>
    <ol class="import-steps">
        <li><strong>Upload.</strong> The public search pauses at once, so nobody copies a disclaimer that is about to change.</li>
        <li><strong>Review.</strong> New, changed, missing and problem vehicles – nothing is changed yet.</li>
        <li><strong>Apply.</strong> A backup first, then everything in one go. Your own names are always kept; edited live values are replaced by the new factory values.</li>
        <li><strong>Release.</strong> When you have checked the result, release the search by hand.</li>
    </ol>
    <form method="post" action="/admin/import" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
        <div class="mb-3">
            <label class="form-label" for="import-file">XLSX file</label>
            <input class="form-control" id="import-file" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <div class="form-text">At most <?= e($maxMegabytes) ?> MB. The file is kept on the server for reference.</div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Upload and start</button>
    </form>
</section>

<?php if ($imports !== []): ?>
<section class="admin-card">
    <h2>Earlier imports</h2>
    <table class="table admin-table align-middle mb-0">
        <thead>
            <tr><th scope="col">File</th><th scope="col">Uploaded (UTC)</th><th scope="col">By</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
<?php foreach ($imports as $import): ?>
            <tr>
                <td><a href="/admin/import/<?= e($import['id']) ?>/file"><?= e($import['original_name']) ?></a></td>
                <td class="text-nowrap"><?= e((new \DateTimeImmutable($import['uploaded_at']))->format('j M Y, H:i')) ?></td>
                <td><?= e($import['username'] ?? '–') ?></td>
                <td><a href="/admin/import/<?= e($import['id']) ?>"><?= e($statusLabels[$import['status']] ?? $import['status']) ?></a></td>
                <td class="text-end">
                    <form method="post" action="/admin/import/reuse">
                        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="import_id" value="<?= e($import['id']) ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Import again</button>
                    </form>
                </td>
            </tr>
<?php endforeach ?>
        </tbody>
    </table>
</section>
<?php endif ?>
