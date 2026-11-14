<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * Brands of the BMW Group the tool knows. MINI and BMW ALPINA are in the model
 * from the start, even though their files arrive later.
 */
enum Brand: string
{
    case BMW = 'BMW';
    case MINI = 'MINI';
    case BMW_ALPINA = 'BMW ALPINA';
}
