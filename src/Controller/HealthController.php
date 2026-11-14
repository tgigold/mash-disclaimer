<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;

/**
 * Health check for the deploy workflow (H-14): 200 means the app boots. It
 * deliberately says nothing else about the installation.
 */
final class HealthController extends Controller
{
    public function check(Request $request): Response
    {
        return Response::json(['status' => 'ok']);
    }
}
