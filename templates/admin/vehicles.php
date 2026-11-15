<?php

declare(strict_types=1);

use Lab09\Disclaimer\Domain\Drivetrain;

/**
 * @var list<array{id: string, name: string, type_code: string|null, series: string|null, drivetrain: string, origin: string, is_live: bool, is_edited: bool}> $rows
 * @var array{total: int, live: int, ICE: int, MHEV: int, PHEV: int, BEV: int} $counts
 * @var array{type: string, status: string, origin: string, q: string} $filters
 * @var string $filterQuery
 * @var \Lab09\Disclaimer\Domain\VehicleDetail|null $detail
 * @var string|null $previewText
 * @var string|null $panel
 * @var array<string, string> $errors
 * @var array<string, string> $submitted
 * @var string $csrfToken
 * @var \Lab09\Disclaimer\Support\View $view
 */

$typeLink = static function (string $type) use ($filters): string {
    $query = http_build_query(array_filter(['type' => $type] + $filters, static fn (string $v): bool => $v !== ''));

    return '/admin/vehicles' . ($query === '' ? '' : '?' . $query);
};
$selectedId = $detail?->vehicle->id;
?>
<div class="admin-toolbar">
    <p class="text-body-secondary mb-0">Maintain models and their mandatory Pkw-EnVKV disclaimers.</p>
    <a class="btn btn-primary" href="/admin/vehicles/new<?= e($filterQuery) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> New vehicle</a>
</div>

<div class="stat-grid">
    <div class="stat"><span class="stat-label">Total</span><span class="stat-value"><?= e($counts['total']) ?></span></div>
    <div class="stat"><span class="stat-label">Live</span><span class="stat-value"><?= e($counts['live']) ?></span></div>
<?php foreach (Drivetrain::cases() as $type): ?>
    <div class="stat"><span class="stat-label"><span class="dot dot-<?= e(strtolower($type->value)) ?>" aria-hidden="true"></span><?= e($type->value) ?></span><span class="stat-value"><?= e($counts[$type->value]) ?></span></div>
<?php endforeach ?>
</div>

<div class="vehicle-layout">
    <section class="admin-card vehicle-list">
        <div class="vehicle-filters">
            <nav class="chip-row" aria-label="Filter by type">
                <a class="chip<?= $filters['type'] === '' ? ' is-active' : '' ?>" href="<?= e($typeLink('')) ?>"<?= $filters['type'] === '' ? ' aria-current="true"' : '' ?>>All</a>
<?php foreach (Drivetrain::cases() as $type): ?>
                <a class="chip<?= $filters['type'] === $type->value ? ' is-active' : '' ?>" href="<?= e($typeLink($type->value)) ?>"<?= $filters['type'] === $type->value ? ' aria-current="true"' : '' ?>><?= e($type->value) ?></a>
<?php endforeach ?>
            </nav>
            <form class="vehicle-filter-form" method="get" action="/admin/vehicles" data-autosubmit>
<?php if ($filters['type'] !== ''): ?>
                <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
<?php endif ?>
                <label class="visually-hidden" for="filter-status">Status</label>
                <select class="form-select form-select-sm" id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <option value="live"<?= $filters['status'] === 'live' ? ' selected' : '' ?>>Live</option>
                    <option value="draft"<?= $filters['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                </select>
                <label class="visually-hidden" for="filter-origin">Origin</label>
                <select class="form-select form-select-sm" id="filter-origin" name="origin">
                    <option value="">All origins</option>
                    <option value="import"<?= $filters['origin'] === 'import' ? ' selected' : '' ?>>Import</option>
                    <option value="manual"<?= $filters['origin'] === 'manual' ? ' selected' : '' ?>>Manual</option>
                </select>
                <label class="visually-hidden" for="filter-q">Search</label>
                <input class="form-control form-control-sm" id="filter-q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Search …" maxlength="100">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
            </form>
        </div>

<?php if ($rows === []): ?>
        <p class="text-body-secondary mb-0 mt-3">No vehicles match these filters.</p>
<?php else: ?>
        <table class="table admin-table vehicle-table mb-0">
            <thead>
                <tr><th scope="col">Model</th><th scope="col">Type</th><th scope="col">Type code</th><th scope="col">Series</th><th scope="col">Status</th></tr>
            </thead>
            <tbody>
<?php foreach ($rows as $row): ?>
                <tr<?= $row['id'] === $selectedId ? ' class="is-selected"' : '' ?>>
                    <td class="vehicle-name">
                        <a href="/admin/vehicles/<?= e($row['id']) ?><?= e($filterQuery) ?>"<?= $row['id'] === $selectedId ? ' aria-current="true"' : '' ?>><?= e($row['name']) ?></a>
<?php if ($row['origin'] === 'manual'): ?>
                        <span class="tag">manual</span>
<?php endif ?>
                    </td>
                    <td><span class="badge-drivetrain badge-<?= e(strtolower($row['drivetrain'])) ?>"><?= e($row['drivetrain']) ?></span></td>
                    <td><?= e($row['type_code'] ?? '—') ?></td>
                    <td><?= e($row['series'] ?? '—') ?></td>
                    <td>
                        <span class="status <?= $row['is_live'] ? 'status-on' : 'status-off' ?>"><?= $row['is_live'] ? 'Live' : 'Draft' ?></span>
<?php if ($row['is_edited']): ?>
                        <span class="tag" title="Live values differ from the factory values">edited</span>
<?php endif ?>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
<?php endif ?>
    </section>

    <aside class="admin-card vehicle-panel" aria-label="Vehicle">
<?php if ($panel === 'new'): ?>
        <h2>New vehicle</h2>
        <p class="text-body-secondary small">For vehicles that are not in a manufacturer file yet. Values can be pasted from Excel, e.g. <code>19.5 / 17.2</code>.</p>
        <?= $view->render('admin/partials/vehicle-form', ['detail' => null, 'previewText' => null, 'errors' => $errors, 'submitted' => $submitted, 'csrfToken' => $csrfToken, 'filterQuery' => $filterQuery]) ?>
<?php elseif ($panel === 'edit' && $detail !== null): ?>
        <?= $view->render('admin/partials/vehicle-form', ['detail' => $detail, 'previewText' => $previewText, 'errors' => $errors, 'submitted' => $submitted, 'csrfToken' => $csrfToken, 'filterQuery' => $filterQuery]) ?>
<?php else: ?>
        <div class="vehicle-panel-empty">
            <i class="bi bi-car-front" aria-hidden="true"></i>
            <p>Choose a vehicle on the left to edit it, or create a new one.</p>
        </div>
<?php endif ?>
    </aside>
</div>
