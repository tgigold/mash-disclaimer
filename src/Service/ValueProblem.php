<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

/**
 * A value that cannot be normalised – a form error, or in the import a record
 * that is shown to the person instead of being taken over automatically.
 */
final class ValueProblem extends \InvalidArgumentException
{
}
