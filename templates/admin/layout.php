<?php

declare(strict_types=1);

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Security\Csrf;

/**
 * Frame of every admin page.
 *
 * @var string $title
 * @var string $section current navigation entry
 * @var string $content rendered page (already escaped)
 * @var \Lab09\Disclaimer\Domain\User $actor
 * @var string $csrfToken
 * @var string|null $flash
 * @var \Lab09\Disclaimer\Support\View $view
 */

$nav = [
    'dashboard' => ['/admin', 'Dashboard', 'bi-grid'],
    'vehicles' => ['/admin/vehicles', 'Vehicles', 'bi-car-front'],
    'users' => ['/admin/users', 'Users', 'bi-people'],
    'history' => ['/admin/history', 'Change history', 'bi-clock-history'],
    'updates' => ['/admin/updates', 'Updates', 'bi-arrow-repeat'],
];
?>
<!doctype html>
<html lang="en-GB" data-bs-theme="light">
<?= $view->render('partials/head', ['title' => $title]) ?>
<body class="admin">
    <header class="admin-header">
        <a class="admin-brand" href="/" title="Go to the search">
            <span class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></span>
            <?= e(App::NAME) ?>
        </a>
        <nav class="admin-nav" aria-label="Admin">
<?php foreach ($nav as $key => [$href, $label, $icon]): ?>
            <a href="<?= e($href) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?>
            </a>
<?php endforeach ?>
        </nav>
        <div class="admin-user">
            <a href="/admin/account"<?= $section === 'account' ? ' aria-current="page"' : '' ?>>
                <i class="bi bi-person-circle" aria-hidden="true"></i><?= e($actor->username) ?>
            </a>
            <form method="post" action="/logout">
                <input type="hidden" name="<?= e(Csrf::FIELD) ?>" value="<?= e($csrfToken) ?>">
                <button class="btn btn-link" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Sign out</button>
            </form>
            <button type="button" class="theme-toggle-inline btn btn-link" data-theme-toggle aria-label="Switch colour theme">
                <i class="bi bi-moon-stars theme-icon-light" aria-hidden="true"></i>
                <i class="bi bi-sun theme-icon-dark" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <main class="admin-main">
        <h1 class="admin-title"><?= e($title) ?></h1>
<?php if ($flash !== null): ?>
        <div class="alert alert-success" role="status"><?= e($flash) ?></div>
<?php endif ?>
        <?= $content ?>
    </main>

    <script src="<?= e(\Lab09\Disclaimer\Support\Assets::url('js/admin-vehicles.js')) ?>" defer></script>
    <footer class="site-footer">
        <nav aria-label="Footer">
            <a href="/">Search</a>
            <a href="https://lab09.de/tater">Contact</a>
            <span>Tater v<?= e(App::VERSION) ?></span>
        </nav>
    </footer>
</body>
</html>
