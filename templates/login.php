<?php

declare(strict_types=1);

use Lab09\Disclaimer\Security\Csrf;

/**
 * @var string $next
 * @var string $username
 * @var string|null $error
 * @var string $csrfToken
 */
?>
<section class="panel panel-narrow">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="panel-title">Sign in</h1>
    <p class="panel-lead">The admin area of the Disclaimer Tool.</p>

<?php if ($error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif ?>

    <form method="post" action="/login">
        <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="mb-3">
            <label class="form-label" for="login-username">Username</label>
            <input class="form-control" id="login-username" name="username" type="text" value="<?= e($username) ?>"
                   autocomplete="username" autocapitalize="off" spellcheck="false" required <?= $username === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="mb-4">
            <label class="form-label" for="login-password">Password</label>
            <input class="form-control" id="login-password" name="password" type="password"
                   autocomplete="current-password" required <?= $username !== '' ? 'autofocus' : '' ?>>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
    </form>
    <p class="panel-foot">Forgot your password? Another admin can set a new one for you.</p>
</section>
