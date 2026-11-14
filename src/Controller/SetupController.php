<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Service\Installer;
use Lab09\Disclaimer\Service\SetupException;
use Lab09\Disclaimer\Service\SetupInput;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\Ip;
use Lab09\Disclaimer\Support\View;

/**
 * The guided setup at /setup (G-11). Public by necessity – there is no admin
 * yet – but protected by the setup code, and gone (404) once installed.
 */
final class SetupController extends Controller
{
    public function __construct(
        View $view,
        private readonly Config $config,
        private readonly Installer $installer,
        private readonly SetupCode $setupCode,
        private readonly Csrf $csrf,
        private readonly ErrorLog $errorLog,
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        if ($this->config->isInstalled()) {
            return $this->gone();
        }

        $missing = $this->installer->missingRequirements();
        if ($missing === []) {
            $this->setupCode->ensure();
        }

        return $this->form($missing, defaults: ['db_host' => 'localhost', 'db_port' => '3306', 'sample_data' => '1']);
    }

    public function submit(Request $request): Response
    {
        if ($this->config->isInstalled()) {
            return $this->gone();
        }

        $missing = $this->installer->missingRequirements();
        if ($missing !== []) {
            return $this->form($missing, status: 422);
        }

        $code = $request->body['setup_code'] ?? '';
        if (!is_string($code) || !$this->setupCode->matches($code)) {
            $this->errorLog->security('wrong setup code', Ip::shorten($request->remoteAddress()));

            return $this->form([], $request->body, ['setup_code' => 'This is not the setup code.'], status: 422);
        }

        [$input, $errors] = SetupInput::fromBody($request->body);
        if ($input === null) {
            return $this->form([], $request->body, $errors, status: 422);
        }

        try {
            $this->installer->install($input, Ip::shorten($request->remoteAddress()));
        } catch (SetupException $e) {
            return $this->form([], $request->body, [], $e->getMessage(), 422);
        }

        return new Response($this->view->page('setup-done', [
            'title' => 'Setup complete',
            'baseUrl' => $input->baseUrl,
            'sampleData' => $input->sampleData,
        ]));
    }

    /**
     * @param list<string> $missing
     * @param array<string, mixed> $values previous input; passwords are never sent back
     * @param array<string, string> $errors
     * @param array<string, string> $defaults
     */
    private function form(
        array $missing,
        array $values = [],
        array $errors = [],
        ?string $problem = null,
        int $status = 200,
        array $defaults = [],
    ): Response {
        $values = $values + $defaults;
        $shown = [];
        foreach (['base_url', 'db_host', 'db_port', 'db_name', 'db_user', 'admin_username', 'sample_data'] as $field) {
            $value = $values[$field] ?? '';
            $shown[$field] = is_string($value) ? $value : '';
        }

        return new Response($this->view->page('setup', [
            'title' => 'Setup',
            'missing' => $missing,
            'values' => $shown,
            'errors' => $errors,
            'problem' => $problem,
            'csrfToken' => $this->csrf->token(),
            'passwordMin' => SetupInput::PASSWORD_MIN,
        ]), $status, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function gone(): Response
    {
        return new Response($this->view->page('error', [
            'title' => 'Page not found',
            'message' => 'There is nothing at this address.',
            'status' => 404,
        ]), 404);
    }
}
