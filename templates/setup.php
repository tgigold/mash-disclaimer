<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;

/**
 * @var list<string> $missing
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var string|null $problem
 * @var string $csrfToken
 * @var int $passwordMin
 */

$field = static function (
    string $name,
    string $label,
    string $type = 'text',
    string $help = '',
    string $autocomplete = 'off',
) use ($values, $errors): void {
    $id = 'setup-' . str_replace('_', '-', $name);
    $error = $errors[$name] ?? null;
    $value = $type === 'password' ? '' : ($values[$name] ?? '');
    $describedBy = trim(($help !== '' ? $id . '-help ' : '') . ($error !== null ? $id . '-error' : ''));
    ?>
    <div class="mb-3">
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
               type="<?= e($type) ?>" value="<?= e($value) ?>" autocomplete="<?= e($autocomplete) ?>"
               <?php if ($describedBy !== ''): ?>aria-describedby="<?= e($describedBy) ?>"<?php endif ?>
               <?php if ($error !== null): ?>aria-invalid="true"<?php endif ?>>
        <?php if ($help !== ''): ?><div class="form-text" id="<?= e($id) ?>-help"><?= e($help) ?></div><?php endif ?>
        <?php if ($error !== null): ?><div class="invalid-feedback" id="<?= e($id) ?>-error"><?= e($error) ?></div><?php endif ?>
    </div>
    <?php
};
?>
<section class="panel">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="panel-title">Set up the Disclaimer Tool</h1>

<?php if ($missing !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">This server is missing something the tool needs:</p>
        <ul class="mb-0">
<?php foreach ($missing as $item): ?>
            <li><?= e($item) ?></li>
<?php endforeach ?>
        </ul>
    </div>
<?php else: ?>
    <p class="panel-lead">
        The setup creates the database tables and the first admin account and writes the configuration.
        It locks itself once it has run.
    </p>

<?php if ($problem !== null): ?>
    <div class="alert alert-danger" role="alert"><?= e($problem) ?></div>
<?php elseif ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">Please check the highlighted fields.</div>
<?php endif ?>

    <form method="post" action="/setup" novalidate>
        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">

        <fieldset class="mb-4">
            <legend>Setup code</legend>
            <?php $field('setup_code', 'Setup code', help: 'Read it from data/setup-code.txt on the server via SFTP.') ?>
        </fieldset>

        <fieldset class="mb-4">
            <legend>Address</legend>
            <?php $field('base_url', 'Address of this installation', 'url', 'With https://, without a path, e.g. https://tater.lab09.de') ?>
        </fieldset>

        <fieldset class="mb-4">
            <legend>Database</legend>
            <p class="form-text mt-0">Use a database user that belongs to this tool only.</p>
            <div class="row g-3">
                <div class="col-sm-8"><?php $field('db_host', 'Host') ?></div>
                <div class="col-sm-4"><?php $field('db_port', 'Port') ?></div>
            </div>
            <?php $field('db_name', 'Database name') ?>
            <?php $field('db_user', 'User') ?>
            <?php $field('db_password', 'Password', 'password', autocomplete: 'new-password') ?>
        </fieldset>

        <fieldset class="mb-4">
            <legend>First admin</legend>
            <?php $field('admin_username', 'Username', autocomplete: 'username') ?>
            <?php $field('admin_password', 'Password', 'password', sprintf('At least %d characters.', $passwordMin), 'new-password') ?>
            <?php $field('admin_password_confirm', 'Repeat password', 'password', autocomplete: 'new-password') ?>
        </fieldset>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="sample_data" value="1" id="setup-sample-data"
                   <?= ($values['sample_data'] ?? '') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="setup-sample-data">
                Add a few invented sample vehicles for testing
            </label>
        </div>

        <button class="btn btn-primary btn-lg" type="submit">Run setup</button>
    </form>
<?php endif ?>
</section>
