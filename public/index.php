<?php

declare(strict_types=1);

/*
 * Front controller: every request that is not a static file lands here
 * (see .htaccess).
 */

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Kernel;

// S-50: never show error details; the kernel logs them to data/logs/error.log.
// Warnings and notices that are no exceptions go to the same file via PHP.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/data/logs/error.log');
error_reporting(E_ALL);

require dirname(__DIR__) . '/vendor/autoload.php';

Kernel::boot()->handle(Request::fromGlobals())->send();
