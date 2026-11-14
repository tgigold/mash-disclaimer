<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

/**
 * A problem during setup the person installing can fix (wrong credentials, not
 * an empty database …). Its message is shown on the setup page.
 */
final class SetupException extends \RuntimeException
{
}
