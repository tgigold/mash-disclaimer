<?php

declare(strict_types=1);

/**
 * Public page while an import locks the search. It says since when, never who
 * (docs/fachlogik.md, section 4).
 *
 * @var \DateTimeImmutable $since
 */
?>
<section class="hero">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="hero-title">Updating the data</h1>
    <p class="hero-lead">New consumption values are being imported. The search is paused so that no outdated disclaimer is copied.</p>
    <p class="text-body-secondary">Paused since <?= e($since->format('j F Y, H:i')) ?> UTC. Please try again in a few minutes.</p>
</section>
