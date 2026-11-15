<?php

declare(strict_types=1);

/**
 * "Your password" field for sensitive actions (S-35).
 *
 * @var string $id unique id prefix
 * @var array<string, string> $errors
 * @var string $field form field name
 * @var string $errorKey key in $errors
 */
$error = $errors[$errorKey] ?? null;
?>
<div class="mb-3">
    <label class="form-label" for="<?= e($id) ?>">Your password, to confirm</label>
    <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($field) ?>"
           type="password" autocomplete="current-password" required>
<?php if ($error !== null): ?>
    <div class="invalid-feedback"><?= e($error) ?></div>
<?php endif ?>
</div>
