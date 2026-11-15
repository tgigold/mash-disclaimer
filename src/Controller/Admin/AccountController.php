<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\UserService;
use Lab09\Disclaimer\Service\ValidationException;
use Lab09\Disclaimer\Support\View;

/**
 * The own account: change password, download own data, delete account (D-8).
 */
final class AccountController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly UserService $service,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        return $this->show();
    }

    public function password(Request $request): Response
    {
        if (!$this->auth->confirmPassword($this->actor(), self::field($request, 'current_password'))) {
            return $this->show(['current_password' => 'Your current password is not correct.'], 'password');
        }
        $new = self::field($request, 'password');
        if (!hash_equals($new, self::field($request, 'password_confirm'))) {
            return $this->show(['password_confirm' => 'Does not match the new password.'], 'password');
        }

        try {
            $this->service->setPassword($this->actor(), $this->actor(), $new, self::ip($request));
        } catch (ValidationException $e) {
            return $this->show($e->errors, 'password');
        }
        $this->auth->keepSessionAfterPasswordChange($this->actor());

        return $this->redirectWith('/admin/account', 'Password changed. All your other sessions have ended.');
    }

    public function export(Request $request): Response
    {
        $data = $this->service->export($this->actor());

        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="disclaimer-tool-account-' . $this->actor()->id . '.json"',
            ],
        );
    }

    public function delete(Request $request): Response
    {
        if (!$this->auth->confirmPassword($this->actor(), self::field($request, 'current_password'))) {
            return $this->show(['delete_password' => 'Your password is not correct.'], 'delete');
        }

        try {
            $this->service->deleteOwnAccount($this->actor(), self::ip($request));
        } catch (ValidationException $e) {
            return $this->show(['delete_password' => $e->errors['user'] ?? 'Not possible.'], 'delete');
        }
        $this->auth->logout($request->remoteAddress());

        return Response::redirect('/login?deleted=1', 303);
    }

    /** @param array<string, string> $errors */
    private function show(array $errors = [], ?string $openForm = null): Response
    {
        return $this->page('account', 'Your account', 'account', [
            'user' => $this->actor(),
            'errors' => $errors,
            'openForm' => $openForm,
        ], $errors === [] ? 200 : 422);
    }
}
