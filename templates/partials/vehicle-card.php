<?php

declare(strict_types=1);

/**
 * One vehicle with its disclaimer. search.js builds the same markup from the
 * API response – keep both in step.
 *
 * @var \Lab09\Disclaimer\Domain\LiveVehicle $vehicle
 */
?>
<article class="result-card" data-vehicle-id="<?= e($vehicle->id) ?>">
    <h2 class="result-title"><?= e($vehicle->name) ?></h2>
    <p class="result-meta">
        <span class="badge-drivetrain badge-<?= e(strtolower($vehicle->drivetrain->value)) ?>"><?= e($vehicle->drivetrain->value) ?></span>
        <span><?= e($vehicle->codeLine()) ?></span>
    </p>
    <div class="disclaimer">
        <p class="disclaimer-text" id="disclaimer-<?= e($vehicle->id) ?>"><?= e($vehicle->disclaimer) ?></p>
        <button class="btn btn-primary copy-button" type="button" data-copy="disclaimer-<?= e($vehicle->id) ?>" hidden>
            <i class="bi bi-copy" aria-hidden="true"></i>
            <span data-copy-label>Copy</span>
        </button>
    </div>
</article>
