<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Support\View;

/**
 * Base for all controllers: they read the request and return a response (A-1).
 */
abstract class Controller
{
    public function __construct(protected readonly View $view)
    {
    }
}
