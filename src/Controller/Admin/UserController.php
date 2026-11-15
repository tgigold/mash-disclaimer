<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\UserService;
use Lab09\Disclaimer\Service\ValidationException;
use Lab09\Disclaimer\Support\View;

/**
 * Users: list, create, set a new password, deactivate and reactivate. Every
 * change asks for the own password first (S-35).
 */
final class UserController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly UserRepository $users,
        private readonly UserService $service,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        return $this->list();
    }

    public function create(Request $request): Response
    {
        $username = trim(self::field($request, 'username'));
        $errors = $this->confirm($request);
        if ($errors !== []) {
            return $this->list($errors, ['username' => $username], 'create');
        }

        try {
            $this->service->create($this->actor(), $username, self::field($request, 'password'), self::ip($request));
        } catch (ValidationException $e) {
            return $this->list($e->errors, ['username' => $username], 'create');
        }

        return $this->redirectWith('/admin/users', sprintf('Admin "%s" created.', $username));
    }

    /** @param array<string, string> $params */
    public function password(Request $request, array $params = []): Response
    {
        $target = $this->target($params);
        if ($target === null) {
            return $this->notFound();
        }
        $errors = $this->confirm($request);
        if ($errors !== []) {
            return $this->list($errors, [], 'password-' . $target->id);
        }

        try {
            $this->service->setPassword($this->actor(), $target, self::field($request, 'password'), self::ip($request));
        } catch (ValidationException $e) {
            return $this->list($e->errors, [], 'password-' . $target->id);
        }

        if ($target->id === $this->actor()->id) {
            $this->auth->keepSessionAfterPasswordChange($target);
        }

        return $this->redirectWith('/admin/users', sprintf('New password set for "%s". Their other sessions have ended.', $target->username));
    }

    /** @param array<string, string> $params */
    public function deactivate(Request $request, array $params = []): Response
    {
        $target = $this->target($params);
        if ($target === null) {
            return $this->notFound();
        }
        $errors = $this->confirm($request);
        if ($errors !== []) {
            return $this->list($errors, [], 'status-' . $target->id);
        }

        try {
            $this->service->deactivate($this->actor(), $target, self::ip($request));
        } catch (ValidationException $e) {
            return $this->list($e->errors, [], 'status-' . $target->id);
        }

        if ($target->id === $this->actor()->id) {
            $this->auth->logout($request->remoteAddress());

            return Response::redirect('/login', 303);
        }

        return $this->redirectWith('/admin/users', sprintf('"%s" is deactivated and signed out everywhere.', $target->username));
    }

    /** @param array<string, string> $params */
    public function activate(Request $request, array $params = []): Response
    {
        $target = $this->target($params);
        if ($target === null) {
            return $this->notFound();
        }
        $errors = $this->confirm($request);
        if ($errors !== []) {
            return $this->list($errors, [], 'status-' . $target->id);
        }

        $this->service->activate($this->actor(), $target, self::ip($request));

        return $this->redirectWith('/admin/users', sprintf('"%s" is active again.', $target->username));
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function list(array $errors = [], array $values = [], ?string $openForm = null): Response
    {
        return $this->page('users', 'Users', 'users', [
            'users' => $this->users->all(),
            'errors' => $errors,
            'values' => $values,
            'openForm' => $openForm,
        ], $errors === [] ? 200 : 422);
    }

    /** @return array<string, string> */
    private function confirm(Request $request): array
    {
        return $this->auth->confirmPassword($this->actor(), self::field($request, 'current_password'))
            ? []
            : ['current_password' => 'Your own password is not correct.'];
    }

    /** @param array<string, string> $params */
    private function target(array $params): ?User
    {
        $id = $params['id'] ?? '';

        return preg_match('/^[1-9]\d{0,9}$/', $id) === 1 ? $this->users->find((int) $id) : null;
    }
}
