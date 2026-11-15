<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Controller\Controller;
use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Support\Ip;
use Lab09\Disclaimer\Support\View;

/**
 * Base for the admin area. The kernel has already made sure someone is signed
 * in; these helpers render the admin layout, keep one-time messages (flash)
 * across the redirect after a POST, and look up the signed-in admin.
 */
abstract class AdminController extends Controller
{
    private const string FLASH = 'flash';

    /** @var array{since: \DateTimeImmutable, by: string|null, import_id: int|null}|null */
    private ?array $lock = null;

    public function __construct(
        View $view,
        protected readonly AuthService $auth,
        protected readonly Csrf $csrf,
        protected readonly Session $session,
    ) {
        parent::__construct($view);
    }

    /** @param array{since: \DateTimeImmutable, by: string|null, import_id: int|null}|null $lock */
    public function showLock(?array $lock): void
    {
        $this->lock = $lock;
    }

    protected function actor(): User
    {
        return $this->auth->user() ?? throw new \LogicException('Admin route reached without a signed-in user.');
    }

    /** @param array<string, mixed> $data */
    protected function page(string $template, string $title, string $section, array $data = [], int $status = 200): Response
    {
        $flash = $this->session->get(self::FLASH);
        $this->session->remove(self::FLASH);

        $content = $this->view->render('admin/' . $template, $data + ['csrfToken' => $this->csrf->token(), 'actor' => $this->actor()]);

        return new Response($this->view->render('admin/layout', [
            'title' => $title,
            'section' => $section,
            'content' => $content,
            'actor' => $this->actor(),
            'csrfToken' => $this->csrf->token(),
            'flash' => is_string($flash) ? $flash : null,
            'lock' => $this->lock,
        ]), $status);
    }

    /** Post/redirect/get with a one-time message. Targets are fixed paths of this app (S-26). */
    protected function redirectWith(string $path, string $message): Response
    {
        $this->session->set(self::FLASH, $message);

        return Response::redirect($path, 303);
    }

    protected function notFound(): Response
    {
        return new Response($this->view->page('error', [
            'title' => 'Page not found',
            'message' => 'There is nothing at this address.',
            'status' => 404,
        ]), 404);
    }

    protected static function ip(Request $request): string
    {
        return Ip::shorten($request->remoteAddress());
    }

    protected static function field(Request $request, string $name): string
    {
        $value = $request->body[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
