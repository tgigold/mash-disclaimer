<?php

declare(strict_types=1);

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Domain\LiveVehicle;
use Lab09\Disclaimer\Domain\SearchQuery;

/**
 * @var string|null $query what was searched (no-JavaScript result page)
 * @var list<LiveVehicle> $results
 * @var LiveVehicle|null $vehicle the one vehicle to show
 * @var string|null $error
 * @var list<string> $suggestions
 * @var \Lab09\Disclaimer\Support\View $view
 */
?>
<section class="hero">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="hero-title"><a class="hero-home" href="/"><?= e(App::NAME) ?></a></h1>
    <p class="hero-lead">Enter a vehicle, copy the mandatory disclaimer.</p>

    <form class="search" role="search" action="/" method="get" data-search-form>
        <label class="visually-hidden" for="search-q">Search vehicles</label>
        <input class="search-input" id="search-q" name="q" type="search"
               value="<?= e($query) ?>"
               maxlength="<?= e(SearchQuery::MAX_LENGTH) ?>"
               placeholder="Model, type code or series – e.g. M3, 31HJ, G80"
               autocomplete="off" autocapitalize="off" spellcheck="false"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-suggestions"
               <?= $query === null && $vehicle === null ? 'autofocus' : '' ?>>
        <button class="search-button" type="submit" aria-label="Search">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>
        <ul class="search-suggestions" id="search-suggestions" role="listbox" aria-label="Suggestions" hidden></ul>
    </form>

<?php if ($suggestions !== []): ?>
    <p class="search-chips" data-search-chips>
        <span>Try:</span>
<?php foreach ($suggestions as $series): ?>
        <a class="chip" href="/?q=<?= e(rawurlencode($series)) ?>" data-search-chip="<?= e($series) ?>"><?= e($series) ?></a>
<?php endforeach ?>
    </p>
<?php endif ?>
</section>

<div class="results" id="search-results" aria-live="polite" data-search-results>
<?php if ($error !== null): ?>
    <div class="result-empty">
        <p class="result-empty-title">That search did not work.</p>
        <p><?= e($error) ?></p>
    </div>
<?php elseif ($vehicle !== null): ?>
    <?= $view->render('partials/vehicle-card', ['vehicle' => $vehicle]) ?>
<?php elseif ($query !== null && $results === []): ?>
    <?= $view->render('partials/no-match', ['query' => $query]) ?>
<?php elseif ($results !== []): ?>
    <?= $view->render('partials/result-list', ['results' => $results, 'query' => $query]) ?>
<?php endif ?>
</div>
