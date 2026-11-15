<?php

declare(strict_types=1);

/**
 * Several matches to choose from. search.js builds the same markup.
 *
 * @var list<\Lab09\Disclaimer\Domain\LiveVehicle> $results
 * @var string|null $query
 */
?>
<section class="result-list">
    <h2 class="result-list-title"><?= e(count($results)) ?> matches – choose a vehicle</h2>
    <ul>
<?php foreach ($results as $item): ?>
        <li>
            <a href="<?= e($item->path()) ?>" data-vehicle-link="<?= e($item->id) ?>">
                <span class="result-list-name"><?= e($item->name) ?></span>
                <span class="result-list-meta">
                    <span class="badge-drivetrain badge-<?= e(strtolower($item->drivetrain->value)) ?>"><?= e($item->drivetrain->value) ?></span>
                    <?= e($item->codeLine()) ?>
                </span>
            </a>
        </li>
<?php endforeach ?>
    </ul>
</section>
