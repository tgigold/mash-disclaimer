<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Service\PasswordPolicy;

/**
 * @var \Lab09\Disclaimer\Domain\User $user
 * @var array<string, string> $errors
 * @var string|null $openForm
 * @var string $csrfToken
 * @var \Lab09\Disclaimer\Support\View $view
 */
$csrf = '<input type="hidden" name="' . e(Csrf::FIELD) . '" value="' . e($csrfToken) . '">';
?>
<section class="admin-card">
    <dl class="admin-facts mb-0">
        <dt>Username</dt><dd><?= e($user->username) ?></dd>
        <dt>Created</dt><dd><?= e($user->createdAt->format('j F Y')) ?><?= $user->createdByName !== null ? ' by ' . e($user->createdByName) : '' ?></dd>
        <dt>Last sign-in</dt><dd><?= e($user->lastLoginAt?->format('j F Y, H:i') ?? '–') ?> UTC</dd>
    </dl>
</section>

<section class="admin-card">
    <details<?= $openForm === 'password' ? ' open' : '' ?>>
        <summary class="h2-summary">Change password</summary>
        <form method="post" action="/admin/account/password" class="mt-3 admin-form">
            <?= $csrf ?>
            <?= $view->render('admin/partials/confirm-password', ['id' => 'acc-current', 'field' => 'current_password', 'errorKey' => 'current_password', 'errors' => $errors]) ?>
            <div class="mb-3">
                <label class="form-label" for="acc-new">New password</label>
                <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="acc-new" name="password" type="password"
                       autocomplete="new-password" minlength="<?= e(PasswordPolicy::MIN) ?>" required>
<?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback"><?= e($errors['password']) ?></div>
<?php endif ?>
                <div class="form-text">At least <?= e(PasswordPolicy::MIN) ?> characters. Your other sessions end.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="acc-confirm">Repeat new password</label>
                <input class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>" id="acc-confirm" name="password_confirm"
                       type="password" autocomplete="new-password" required>
<?php if (isset($errors['password_confirm'])): ?>
                <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
<?php endif ?>
            </div>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </details>
</section>

<section class="admin-card">
    <h2>Your data</h2>
    <p>Download everything stored about your account, including your entries in the change history.</p>
    <a class="btn btn-outline-secondary" href="/admin/account/export"><i class="bi bi-download" aria-hidden="true"></i> Download as JSON</a>
</section>

<section class="admin-card admin-card-danger">
    <details<?= $openForm === 'delete' ? ' open' : '' ?>>
        <summary class="h2-summary">Delete account</summary>
        <p class="mt-3">Your account is deleted at once. The change history keeps what you did, without your name.
            The last active account cannot be deleted.</p>
        <form method="post" action="/admin/account/delete" class="admin-form">
            <?= $csrf ?>
            <?= $view->render('admin/partials/confirm-password', ['id' => 'acc-delete', 'field' => 'current_password', 'errorKey' => 'delete_password', 'errors' => $errors]) ?>
            <button class="btn btn-outline-danger" type="submit">Delete my account</button>
        </form>
    </details>
</section>
