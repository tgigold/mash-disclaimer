<?php

declare(strict_types=1);

use Lab09\Disclaimer\App;

/**
 * @var string $content Rendered page template (already escaped)
 * @var string|null $title Page title; null on the start page
 * @var \Lab09\Disclaimer\Support\View $view
 */
?>
<!doctype html>
<html lang="en-GB" data-bs-theme="light">
<?= $view->render('partials/head', ['title' => $title]) ?>
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
