<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;

/**
 * The public start page. The search arrives in step 3; for now the page shows
 * the design with the search field disabled.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return new Response($this->view->page('home', ['title' => null]));
    }
}
