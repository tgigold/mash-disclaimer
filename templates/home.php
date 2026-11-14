<?php

declare(strict_types=1);

use Lab09\Disclaimer\App;

?>
<section class="hero">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="hero-title"><?= e(App::NAME) ?></h1>
    <p class="hero-lead">Enter a vehicle, copy the mandatory disclaimer.</p>

    <form class="search" role="search" action="/" method="get">
        <label class="visually-hidden" for="search-q">Search vehicles</label>
        <input class="search-input" id="search-q" name="q" type="search"
               placeholder="Model, type code or series – e.g. M3, 31HJ, G80"
               autocomplete="off" disabled aria-describedby="search-note">
        <button class="search-button" type="submit" disabled aria-label="Search">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>
    </form>
    <p class="search-note" id="search-note">The search is being built and will be available soon.</p>
</section>
