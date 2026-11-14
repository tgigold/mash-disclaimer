<?php

declare(strict_types=1);

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Support\Assets;

/**
 * @var string $content Rendered page template (already escaped)
 * @var string|null $title Page title; null on the start page
 */

$stylesheets = [
    'vendor/bootstrap-5.3.9/bootstrap.min.css',
    'vendor/bootstrap-icons-1.13.2/bootstrap-icons.min.css',
    'css/app.css',
];
?>
<!doctype html>
<html lang="en-GB" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title === null ? App::NAME : $title . ' · ' . App::NAME) ?></title>
    <?php /* Sets the colour theme before the first paint, so there is no flash (S-4: external file). */ ?>
    <script src="<?= e(Assets::url('js/theme.js')) ?>"></script>
<?php foreach ($stylesheets as $path): ?>
    <link rel="stylesheet" href="<?= e(Assets::url($path)) ?>"<?php if (Assets::integrity($path) !== null): ?> integrity="<?= e(Assets::integrity($path)) ?>"<?php endif ?>>
<?php endforeach ?>
    <link rel="icon" href="<?= e(Assets::url('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
    <button type="button" class="theme-toggle btn btn-link" data-theme-toggle aria-label="Switch colour theme">
        <i class="bi bi-moon-stars theme-icon-light" aria-hidden="true"></i>
        <i class="bi bi-sun theme-icon-dark" aria-hidden="true"></i>
    </button>

    <main class="page">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <nav aria-label="Footer">
            <a href="/admin">Dashboard</a>
            <a href="https://lab09.de/tater">Contact</a>
            <span>Tater v<?= e(App::VERSION) ?></span>
        </nav>
    </footer>
</body>
</html>
