<?php

declare(strict_types=1);

/**
 * @var string $baseUrl
 * @var bool $sampleData
 */
?>
<section class="panel">
    <div class="brand-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <h1 class="panel-title">Setup complete</h1>
    <p class="panel-lead">
        The database is ready, the first admin account exists and the configuration has been written.
        The setup page is now locked and the setup code has been deleted.
    </p>
<?php if ($sampleData): ?>
    <p>The invented sample vehicles have been added. Remove them before going live.</p>
<?php endif ?>
    <p><a class="btn btn-primary" href="<?= e($baseUrl) ?>/">Go to the start page</a></p>
</section>
