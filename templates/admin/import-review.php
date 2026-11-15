<?php

declare(strict_types=1);

use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Security\Csrf;

/**
 * The review step of an import, and afterwards its summary.
 *
 * @var array{id: int, original_name: string, username: string|null, uploaded_at: string, status: string, live_mode: string|null, counts: array<string, int>} $import
 * @var array{new: list<array<string, mixed>>, changed: list<array<string, mixed>>, unchanged: list<array<string, mixed>>, problem: list<array<string, mixed>>} $items
 * @var list<\Lab09\Disclaimer\Domain\VehicleDetail> $missing
 * @var list<\Lab09\Disclaimer\Domain\VehicleDetail> $manual
 * @var array<string, string> $errors
 * @var string $csrfToken
 */

$editable = $import['status'] === 'parsed';
$csrf = '<input type="hidden" name="' . e(Csrf::FIELD) . '" value="' . e($csrfToken) . '">';
$label = static fn (string $field): string => VehicleField::from($field)->label();
$liveBox = static function (array $item) use ($editable): string {
    if (!$editable) {
        return '';
    }

    return '<input class="form-check-input" type="checkbox" name="live[]" value="' . e($item['id']) . '" aria-label="Put live: ' . e($item['source_name']) . '" data-live-box>';
};
$statusText = [
    'parsed' => 'In review – nothing has been changed yet.',
    'applied' => 'Applied. The search stays paused until it is released.',
    'released' => 'Applied and released.',
    'aborted' => 'Aborted – nothing was changed.',
][$import['status']] ?? $import['status'];
?>
<section class="admin-card">
    <div class="import-head">
        <div>
            <h2 class="mb-1"><?= e($import['original_name']) ?></h2>
            <p class="text-body-secondary mb-0">Uploaded <?= e((new \DateTimeImmutable($import['uploaded_at']))->format('j M Y, H:i')) ?> UTC<?= $import['username'] !== null ? ' by ' . e($import['username']) : '' ?> · <a href="/admin/import/<?= e($import['id']) ?>/file">Download original</a></p>
        </div>
        <p class="mb-0"><strong><?= e($statusText) ?></strong></p>
    </div>
    <div class="stat-grid mt-3 mb-0">
        <div class="stat"><span class="stat-label">New</span><span class="stat-value"><?= e(count($items['new'])) ?></span></div>
        <div class="stat"><span class="stat-label">Changed</span><span class="stat-value"><?= e(count($items['changed'])) ?></span></div>
        <div class="stat"><span class="stat-label">Unchanged</span><span class="stat-value"><?= e(count($items['unchanged'])) ?></span></div>
        <div class="stat"><span class="stat-label">Problems</span><span class="stat-value"><?= e(count($items['problem'])) ?></span></div>
<?php if ($editable): ?>
        <div class="stat"><span class="stat-label">Missing</span><span class="stat-value"><?= e(count($missing)) ?></span></div>
<?php endif ?>
    </div>
</section>

<?php if ($errors !== []): ?>
<div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div>
<?php endif ?>

<?php if ($editable): ?>
<form method="post" action="/admin/import/<?= e($import['id']) ?>/apply" class="import-form">
    <?= $csrf ?>

    <section class="admin-card">
        <h2>What goes live?</h2>
        <p class="text-body-secondary">The work table always receives all accepted factory values. This decides which vehicles also get them in the public search. Edited live values are replaced; own names stay.</p>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="live_mode" value="active" id="mode-active" checked>
            <label class="form-check-label" for="mode-active"><strong>Only vehicles that are live now</strong> – they get the new values, new vehicles stay drafts</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="live_mode" value="all" id="mode-all">
            <label class="form-check-label" for="mode-all"><strong>All vehicles in the file</strong> (except problems)</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="live_mode" value="selected" id="mode-selected">
            <label class="form-check-label" for="mode-selected"><strong>Only the vehicles ticked under “Live”</strong> below; other live vehicles keep their current values</label>
        </div>
    </section>

    <section class="admin-card">
        <h2>New (<?= e(count($items['new'])) ?>)</h2>
<?php if ($items['new'] === []): ?>
        <p class="mb-0 text-body-secondary">No new type codes.</p>
<?php else: ?>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th scope="col">Import</th><th scope="col">Live</th><th scope="col">Type code</th><th scope="col">Name in the file</th><th scope="col">Type</th><th scope="col">Values</th></tr></thead>
            <tbody>
<?php foreach ($items['new'] as $item): ?>
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="import_new[]" value="<?= e($item['id']) ?>" checked aria-label="Import <?= e($item['source_name']) ?>"></td>
                    <td><?= $liveBox($item) ?></td>
                    <td><?= e($item['type_code']) ?></td>
                    <td><?= e($item['source_name']) ?></td>
                    <td><span class="badge-drivetrain badge-<?= e(strtolower((string) $item['drivetrain'])) ?>"><?= e($item['drivetrain']) ?></span></td>
                    <td class="small"><?= e(implode(' · ', array_map(static fn ($f, $v) => $label((string) $f) . ' ' . $v, array_keys($item['values']), $item['values']))) ?></td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <section class="admin-card">
        <h2>Changed (<?= e(count($items['changed'])) ?>)</h2>
<?php if ($items['changed'] === []): ?>
        <p class="mb-0 text-body-secondary">No changed values.</p>
<?php else: ?>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th scope="col">Live</th><th scope="col">Type code</th><th scope="col">Your name</th><th scope="col">Changes</th></tr></thead>
            <tbody>
<?php foreach ($items['changed'] as $item): $existing = $item['existing']; ?>
                <tr>
                    <td><?= $liveBox($item) ?></td>
                    <td><?= e($item['type_code']) ?></td>
                    <td>
                        <?= e($existing?->vehicle->name ?? $item['source_name']) ?>
<?php if ($existing !== null && $existing->isLive()): ?><span class="status status-on ms-1">Live</span><?php endif ?>
<?php if ($existing !== null && $existing->isEdited): ?><span class="tag" title="Edited live values will be replaced">edited</span><?php endif ?>
<?php if ($existing !== null && $existing->isManual()): ?><span class="tag">manual → import</span><?php endif ?>
                    </td>
                    <td class="small">
<?php foreach ($item['diff'] as $field => $change): ?>
                        <div><?= e($label($field)) ?>: <del><?= e($change['old'] ?? '–') ?></del> → <ins><?= e($change['new'] ?? '–') ?></ins></div>
<?php endforeach ?>
<?php if ($item['diff'] === []): ?><div>Type changed to <?= e($item['drivetrain']) ?></div><?php endif ?>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <section class="admin-card">
        <details>
            <summary class="h2-summary">Unchanged (<?= e(count($items['unchanged'])) ?>)</summary>
            <ul class="import-plain-list mt-3 mb-0">
<?php foreach ($items['unchanged'] as $item): ?>
                <li><?= $liveBox($item) ?> <?= e($item['type_code']) ?> · <?= e($item['existing']?->vehicle->name ?? $item['source_name']) ?></li>
<?php endforeach ?>
            </ul>
        </details>
    </section>

    <section class="admin-card">
        <h2>Missing (<?= e(count($missing)) ?>)</h2>
        <p class="text-body-secondary">In your vehicles, but not in this file (only brands that appear in the file are checked). Nothing is deleted unless you choose it.</p>
<?php if ($missing === []): ?>
        <p class="mb-0 text-body-secondary">None.</p>
<?php else: ?>
        <table class="table admin-table align-middle mb-0">
            <tbody>
<?php foreach ($missing as $detail): $id = $detail->vehicle->id; ?>
                <tr>
                    <td><?= e($detail->vehicle->typeCode ?? '—') ?></td>
                    <td><?= e($detail->vehicle->name) ?><?= $detail->isLive() ? ' <span class="status status-on">Live</span>' : '' ?></td>
                    <td class="text-end text-nowrap">
                        <label class="me-3"><input class="form-check-input" type="radio" name="missing[<?= e($id) ?>]" value="keep" checked> Keep</label>
                        <label><input class="form-check-input" type="radio" name="missing[<?= e($id) ?>]" value="delete"> Delete</label>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <section class="admin-card">
        <h2>Problems (<?= e(count($items['problem'])) ?>)</h2>
        <p class="text-body-secondary">Never taken over automatically. A new vehicle can be taken over as a draft to fix it by hand; an existing one keeps its current values.</p>
<?php if ($items['problem'] === []): ?>
        <p class="mb-0 text-body-secondary">None.</p>
<?php else: ?>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th scope="col">Draft</th><th scope="col">Where</th><th scope="col">Type code</th><th scope="col">Name in the file</th><th scope="col">Problem</th></tr></thead>
            <tbody>
<?php foreach ($items['problem'] as $item): $canDraft = $item['vehicle_id'] === null && $item['drivetrain'] !== null && $item['type_code'] !== null && $item['brand'] !== null; ?>
                <tr>
                    <td><?php if ($canDraft): ?><input class="form-check-input" type="checkbox" name="problem_draft[]" value="<?= e($item['id']) ?>" aria-label="Take over as draft"><?php else: ?>–<?php endif ?></td>
                    <td class="text-nowrap"><?= e(trim($item['sheet'])) ?> · <?= e($item['column_ref']) ?></td>
                    <td><?= e($item['type_code'] ?? '?') ?></td>
                    <td><?= e($item['source_name']) ?></td>
                    <td class="small"><?= e(implode(' ', $item['problems'])) ?></td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <section class="admin-card">
        <h2>Vehicles created by hand (<?= e(count($manual)) ?>)</h2>
        <p class="text-body-secondary">The import cannot match these. Check for duplicates: keep, merge with a new type code from this file (the vehicle keeps its name and gets the factory values) or delete.</p>
<?php if ($manual === []): ?>
        <p class="mb-0 text-body-secondary">None.</p>
<?php else: ?>
        <table class="table admin-table align-middle mb-0">
            <tbody>
<?php foreach ($manual as $detail): $id = $detail->vehicle->id; ?>
                <tr>
                    <td><?= e($detail->vehicle->name) ?> <span class="badge-drivetrain badge-<?= e(strtolower($detail->vehicle->drivetrain->value)) ?>"><?= e($detail->vehicle->drivetrain->value) ?></span><?= $detail->vehicle->typeCode !== null ? ' · ' . e($detail->vehicle->typeCode) : '' ?></td>
                    <td class="text-end">
                        <label class="visually-hidden" for="manual-<?= e($id) ?>">Decision for <?= e($detail->vehicle->name) ?></label>
                        <select class="form-select form-select-sm" id="manual-<?= e($id) ?>" name="manual[<?= e($id) ?>]">
                            <option value="keep">Keep</option>
<?php foreach ($items['new'] as $item): ?>
                            <option value="merge:<?= e($item['id']) ?>">Merge with <?= e($item['type_code']) ?> – <?= e($item['source_name']) ?></option>
<?php endforeach ?>
                            <option value="delete">Delete</option>
                        </select>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <div class="import-actions">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Back up and apply</button>
    </div>
</form>

<form method="post" action="/admin/import/<?= e($import['id']) ?>/abort" class="import-abort">
    <?= $csrf ?>
    <button class="btn btn-outline-secondary" type="submit">Abort import</button>
    <span class="text-body-secondary small">Nothing is changed. The search stays paused until you release it.</span>
</form>
<?php elseif ($import['status'] === 'applied' || $import['status'] === 'released'): ?>
<section class="admin-card">
    <h2>Result</h2>
    <ul class="mb-0">
<?php foreach (['imported' => 'imported or updated', 'live' => 'live with new values', 'deleted' => 'deleted', 'merged' => 'merged with manual vehicles', 'skipped' => 'skipped'] as $key => $text): ?>
        <li><?= e($import['counts'][$key] ?? 0) ?> <?= e($text) ?></li>
<?php endforeach ?>
    </ul>
<?php if ($import['status'] === 'applied'): ?>
    <p class="mt-3 mb-0">Check the vehicles, then use <strong>Release search</strong> at the top.</p>
<?php endif ?>
</section>
<?php endif ?>
