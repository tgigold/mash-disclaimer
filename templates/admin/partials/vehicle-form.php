<?php

declare(strict_types=1);

use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Security\Csrf;

/**
 * Sidebar form for a new (detail = null) or an existing vehicle, with live
 * preview (admin-vehicles.js) and the actions go live, withdraw, reset, delete.
 *
 * @var \Lab09\Disclaimer\Domain\VehicleDetail|null $detail
 * @var string|null $previewText
 * @var array<string, string> $errors
 * @var array<string, string> $submitted
 * @var string $csrfToken
 * @var string $filterQuery
 */

$vehicle = $detail?->vehicle;
$isNew = $detail === null;
$metaEditable = $isNew || $detail->isManual();
$valuesEditable = $isNew || $detail->valuesEditable();
$hidden = '<input type="hidden" name="' . e(Csrf::FIELD) . '" value="' . e($csrfToken) . '">'
    . '<input type="hidden" name="return" value="' . e($filterQuery) . '">';

$value = static function (string $field, ?string $current) use ($submitted): string {
    return $submitted[$field] ?? ($current ?? '');
};
$error = static fn (string $field): ?string => $errors[$field] ?? null;
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

$currentDrivetrain = $submitted['drivetrain'] ?? $vehicle?->drivetrain->value ?? '';
// Which value fields to render: all (shown/hidden by type in JS) when the type can change,
// otherwise only those of the vehicle's type.
$fields = $detail === null || $detail->isManual() ? VehicleField::cases() : $detail->vehicle->drivetrain->requiredFields();
$usedBy = static function (VehicleField $field): string {
    $types = array_filter(Drivetrain::cases(), static fn (Drivetrain $d): bool => in_array($field, $d->requiredFields(), true));

    return implode(' ', array_map(static fn (Drivetrain $d): string => $d->value, $types));
};
$action = $detail === null ? '/admin/vehicles' : '/admin/vehicles/' . $detail->vehicle->id;
?>
<?php if ($detail !== null): ?>
<div class="vehicle-panel-head">
    <h2><?= e($detail->vehicle->name) ?></h2>
    <p class="mb-0">
        <span class="status <?= $detail->isLive() ? 'status-on' : 'status-off' ?>"><?= $detail->isLive() ? 'Live' : 'Draft' ?></span>
        <span class="tag"><?= $detail->isManual() ? 'manual' : 'import' ?></span>
<?php if ($detail->isEdited): ?>
        <span class="tag">edited</span>
<?php endif ?>
    </p>
<?php if ($detail->editedAt !== null): ?>
    <p class="small text-body-secondary mb-0 mt-1">Last edited <?= e($detail->editedAt->format('j M Y, H:i')) ?> UTC<?= $detail->editedBy !== null ? ' by ' . e($detail->editedBy) : '' ?></p>
<?php endif ?>
</div>
<?php endif ?>

<?php if (isset($errors['values'])): ?>
<div class="alert alert-danger py-2" role="alert"><?= e($errors['values']) ?></div>
<?php elseif ($errors !== []): ?>
<div class="alert alert-danger py-2" role="alert">Please check the highlighted fields.</div>
<?php endif ?>

<form method="post" action="<?= e($action) ?>" class="vehicle-form" data-vehicle-form novalidate>
    <?= $hidden ?>
<?php if ($detail !== null): ?>
    <input type="hidden" name="id" value="<?= e($detail->vehicle->id) ?>">
<?php endif ?>

    <div class="mb-3">
        <label class="form-label" for="v-name">Model name</label>
        <input class="form-control<?= $invalid('name') ?>" id="v-name" name="name" value="<?= e($value('name', $vehicle?->name)) ?>" maxlength="255" required autocomplete="off">
<?php if ($error('name') !== null): ?><div class="invalid-feedback"><?= e($error('name')) ?></div><?php endif ?>
<?php if ($detail !== null && !$detail->isManual()): ?>
        <div class="form-text">Your name stays when the next import arrives.</div>
<?php endif ?>
    </div>

<?php if ($detail === null || $detail->isManual()): ?>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label" for="v-brand">Brand</label>
            <select class="form-select<?= $invalid('brand') ?>" id="v-brand" name="brand">
<?php foreach (Brand::cases() as $brand): ?>
                <option value="<?= e($brand->value) ?>"<?= $value('brand', $vehicle?->brand->value ?? 'BMW') === $brand->value ? ' selected' : '' ?>><?= e($brand->value) ?></option>
<?php endforeach ?>
            </select>
<?php if ($error('brand') !== null): ?><div class="invalid-feedback"><?= e($error('brand')) ?></div><?php endif ?>
        </div>
        <div class="col-6">
            <label class="form-label" for="v-drivetrain">Type</label>
            <select class="form-select<?= $invalid('drivetrain') ?>" id="v-drivetrain" name="drivetrain" data-drivetrain>
<?php if ($isNew): ?>
                <option value=""<?= $currentDrivetrain === '' ? ' selected' : '' ?>>Choose …</option>
<?php endif ?>
<?php foreach (Drivetrain::cases() as $type): ?>
                <option value="<?= e($type->value) ?>"<?= $currentDrivetrain === $type->value ? ' selected' : '' ?>><?= e($type->value) ?></option>
<?php endforeach ?>
            </select>
<?php if ($error('drivetrain') !== null): ?><div class="invalid-feedback"><?= e($error('drivetrain')) ?></div><?php endif ?>
        </div>
        <div class="col-6">
            <label class="form-label" for="v-type-code">Type code <span class="text-body-secondary">(optional)</span></label>
            <input class="form-control<?= $invalid('type_code') ?>" id="v-type-code" name="type_code" value="<?= e($value('type_code', $vehicle?->typeCode)) ?>" maxlength="5" autocomplete="off" autocapitalize="characters">
<?php if ($error('type_code') !== null): ?><div class="invalid-feedback"><?= e($error('type_code')) ?></div><?php endif ?>
        </div>
        <div class="col-6">
            <label class="form-label" for="v-series">Series <span class="text-body-secondary">(optional)</span></label>
            <input class="form-control<?= $invalid('series') ?>" id="v-series" name="series" value="<?= e($value('series', $vehicle?->series)) ?>" maxlength="16" autocomplete="off" autocapitalize="characters">
<?php if ($error('series') !== null): ?><div class="invalid-feedback"><?= e($error('series')) ?></div><?php endif ?>
        </div>
    </div>
<?php else: ?>
    <dl class="vehicle-meta">
        <dt>Type</dt><dd><span class="badge-drivetrain badge-<?= e(strtolower($detail->vehicle->drivetrain->value)) ?>"><?= e($detail->vehicle->drivetrain->value) ?></span></dd>
        <dt>Type code</dt><dd><?= e($detail->vehicle->typeCode ?? '—') ?></dd>
        <dt>Series</dt><dd><?= e($detail->vehicle->series ?? '—') ?></dd>
        <dt>Brand</dt><dd><?= e($detail->vehicle->brand->value) ?></dd>
    </dl>
<?php endif ?>

    <fieldset class="vehicle-values">
        <legend>Values</legend>
<?php if (!$valuesEditable): ?>
        <p class="form-text mt-0">Factory values from the import. Put the vehicle live to adjust them; the factory values stay available for a reset.</p>
<?php elseif ($metaEditable): ?>
        <p class="form-text mt-0 vehicle-values-hint" data-values-hint>Only the fields of the chosen type are used.</p>
<?php endif ?>
<?php foreach ($fields as $field):
    $name = 'value_' . $field->value;
    $current = $detail?->currentValue($field);
    $factory = $vehicle?->value($field);
?>
        <div class="vehicle-value" data-used-by="<?= e($usedBy($field)) ?>">
            <label class="form-label" for="v-<?= e($field->value) ?>"><?= e($field->label()) ?><?= $field->unit() !== '' ? ' <span class="text-body-secondary">(' . e($field->unit()) . ')</span>' : '' ?></label>
            <input class="form-control<?= $invalid($name) ?>" id="v-<?= e($field->value) ?>" name="<?= e($name) ?>" value="<?= e($value($name, $current)) ?>"
                   maxlength="100" autocomplete="off" inputmode="decimal"<?= $valuesEditable ? '' : ' readonly' ?>>
<?php if ($error($name) !== null): ?><div class="invalid-feedback"><?= e($error($name)) ?></div><?php endif ?>
<?php if ($detail !== null && !$detail->isManual() && $detail->isLive() && $factory !== $current): ?>
            <div class="form-text">Factory: <?= e($factory ?? '–') ?></div>
<?php endif ?>
        </div>
<?php endforeach ?>
    </fieldset>

    <div class="vehicle-preview">
        <p class="form-label mb-1">Preview</p>
        <p class="vehicle-preview-text" data-preview aria-live="polite"><?= $previewText !== null ? e($previewText) : '<span class="text-body-secondary">The text appears once name and values are complete.</span>' ?></p>
    </div>

    <div class="vehicle-form-actions">
        <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create draft' : 'Save' ?></button>
<?php if ($detail !== null): ?>
        <a class="btn btn-outline-secondary" href="/admin/vehicles/<?= e($detail->vehicle->id) ?><?= e($filterQuery) ?>">Discard changes</a>
<?php else: ?>
        <a class="btn btn-outline-secondary" href="/admin/vehicles<?= e($filterQuery) ?>">Cancel</a>
<?php endif ?>
    </div>
</form>

<?php if ($detail !== null): ?>
<div class="vehicle-actions">
<?php if ($detail->isLive()): ?>
    <form method="post" action="/admin/vehicles/<?= e($detail->vehicle->id) ?>/withdraw"><?= $hidden ?>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-eye-slash" aria-hidden="true"></i> Withdraw from search</button>
    </form>
<?php else: ?>
    <form method="post" action="/admin/vehicles/<?= e($detail->vehicle->id) ?>/live"><?= $hidden ?>
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-broadcast" aria-hidden="true"></i> Put live</button>
    </form>
<?php endif ?>
<?php if ($detail->canReset()): ?>
    <form method="post" action="/admin/vehicles/<?= e($detail->vehicle->id) ?>/reset"><?= $hidden ?>
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset to factory values</button>
    </form>
<?php endif ?>
    <details class="vehicle-delete">
        <summary class="btn btn-link btn-sm text-danger">Delete</summary>
        <form method="post" action="/admin/vehicles/<?= e($detail->vehicle->id) ?>/delete"><?= $hidden ?>
            <p class="small mb-2"><?= $detail->isManual()
                ? 'Deletes the vehicle for good.'
                : 'Deletes the vehicle. The next import will offer it again as new.' ?></p>
            <button class="btn btn-outline-danger btn-sm" type="submit">Delete for good</button>
        </form>
    </details>
</div>
<?php endif ?>
