<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Who may call a route. Admin is the default, public routes are marked explicitly (A-3).
 */
enum Access
{
    case Admin;
    case Public;
}
