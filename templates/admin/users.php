<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Service\PasswordPolicy;

/**
 * @var list<\Lab09\Disclaimer\Domain\User> $users
 * @var array<string, string> $errors
 * @var array<string, string> $values
 * @var string|null $openForm which form to show open after an error
 * @var string $csrfToken
 * @var \Lab09\Disclaimer\Domain\User $actor
 * @var \Lab09\Disclaimer\Support\View $view
 */

$csrf = static fn (): string => '<input type="hidden" name="' . e(Csrf::FIELD) . '" value="' . e($csrfToken) . '">';
$date = static fn (?\DateTimeImmutable $d): string => $d === null ? '–' : $d->format('j F Y, H:i') . ' UTC';
?>
<section class="admin-card">
    <table class="table admin-table align-middle mb-0">
        <thead>
            <tr><th scope="col">Username</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col">Last sign-in</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
<?php foreach ($users as $user): $open = $openForm === 'password-' . $user->id || $openForm === 'status-' . $user->id; ?>
            <tr>
                <td>
                    <strong><?= e($user->username) ?></strong><?= $user->id === $actor->id ? ' <span class="text-body-secondary">(you)</span>' : '' ?>
<?php if ($user->createdByName !== null): ?>
                    <div class="small text-body-secondary">added by <?= e($user->createdByName) ?></div>
<?php endif ?>
                </td>
                <td><span class="status <?= $user->isActive ? 'status-on' : 'status-off' ?>"><?= $user->isActive ? 'Active' : 'Deactivated' ?></span></td>
                <td><?= e($date($user->createdAt)) ?></td>
                <td><?= e($date($user->lastLoginAt)) ?></td>
                <td class="text-end">
                    <details class="row-actions"<?= $open ? ' open' : '' ?>>
                        <summary class="btn btn-sm btn-outline-secondary">Manage</summary>
                        <div class="row-actions-body">
                            <form method="post" action="/admin/users/<?= e($user->id) ?>/password">
                                <?= $csrf() ?>
                                <h3>New password</h3>
                                <div class="mb-3">
                                    <label class="form-label" for="pw-<?= e($user->id) ?>">New password for <?= e($user->username) ?></label>
                                    <input class="form-control<?= $openForm === 'password-' . $user->id && isset($errors['password']) ? ' is-invalid' : '' ?>"
                                           id="pw-<?= e($user->id) ?>" name="password" type="password" autocomplete="new-password" minlength="<?= e(PasswordPolicy::MIN) ?>" required>
<?php if ($openForm === 'password-' . $user->id && isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['password']) ?></div>
<?php endif ?>
                                    <div class="form-text">At least <?= e(PasswordPolicy::MIN) ?> characters. Ends all their sessions.</div>
                                </div>
                                <?= $view->render('admin/partials/confirm-password', ['id' => 'confirm-pw-' . $user->id, 'field' => 'current_password', 'errorKey' => 'current_password', 'errors' => $openForm === 'password-' . $user->id ? $errors : []]) ?>
                                <button class="btn btn-primary btn-sm" type="submit">Set password</button>
                            </form>
                            <hr>
                            <form method="post" action="/admin/users/<?= e($user->id) ?>/<?= $user->isActive ? 'deactivate' : 'activate' ?>">
                                <?= $csrf() ?>
                                <h3><?= $user->isActive ? 'Deactivate' : 'Reactivate' ?></h3>
<?php if ($openForm === 'status-' . $user->id && isset($errors['user'])): ?>
                                <div class="alert alert-danger py-2"><?= e($errors['user']) ?></div>
<?php endif ?>
                                <?= $view->render('admin/partials/confirm-password', ['id' => 'confirm-st-' . $user->id, 'field' => 'current_password', 'errorKey' => 'current_password', 'errors' => $openForm === 'status-' . $user->id ? $errors : []]) ?>
                                <button class="btn btn-sm <?= $user->isActive ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit">
                                    <?= $user->isActive ? 'Deactivate and sign out everywhere' : 'Reactivate' ?>
                                </button>
                            </form>
                        </div>
                    </details>
                </td>
            </tr>
<?php endforeach ?>
        </tbody>
    </table>
</section>

<section class="admin-card">
    <details<?= $openForm === 'create' ? ' open' : '' ?>>
        <summary class="h2-summary">Add an admin</summary>
        <form method="post" action="/admin/users" class="mt-3 admin-form">
            <?= $csrf() ?>
            <div class="mb-3">
                <label class="form-label" for="new-username">Username</label>
                <input class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="new-username" name="username"
                       value="<?= e($values['username'] ?? '') ?>" autocomplete="off" autocapitalize="off" spellcheck="false" required>
<?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?= e($errors['username']) ?></div>
<?php endif ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="new-password">Password</label>
                <input class="form-control<?= $openForm === 'create' && isset($errors['password']) ? ' is-invalid' : '' ?>" id="new-password" name="password"
                       type="password" autocomplete="new-password" minlength="<?= e(PasswordPolicy::MIN) ?>" required>
<?php if ($openForm === 'create' && isset($errors['password'])): ?>
                <div class="invalid-feedback"><?= e($errors['password']) ?></div>
<?php endif ?>
                <div class="form-text">At least <?= e(PasswordPolicy::MIN) ?> characters. Tell it to them in person or via a password manager.</div>
            </div>
            <?= $view->render('admin/partials/confirm-password', ['id' => 'confirm-create', 'field' => 'current_password', 'errorKey' => 'current_password', 'errors' => $openForm === 'create' ? $errors : []]) ?>
            <button class="btn btn-primary" type="submit">Add admin</button>
        </form>
    </details>
</section>
