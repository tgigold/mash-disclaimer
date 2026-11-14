<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var string $message
 * @var int $status
 */
?>
<section class="hero">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <p class="error-status"><?= e($status) ?></p>
    <h1 class="hero-title"><?= e($title) ?></h1>
    <p class="hero-lead"><?= e($message) ?></p>
    <p><a href="/">Back to the search</a></p>
</section>
