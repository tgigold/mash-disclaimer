<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Security;

use Lab09\Disclaimer\Http\Request;

/**
 * CSRF protection (S-12 to S-14): one random token per session, read only from
 * the POST body or the X-CSRF-Token header, compared in constant time; plus a
 * check that the request does not come from a foreign site.
 */
final readonly class Csrf
{
    public const string FIELD = '_csrf';

    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(Request $request): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);
        $given = $request->body[self::FIELD] ?? $request->header('X-CSRF-Token');

        return is_string($expected) && $expected !== ''
            && is_string($given)
            && hash_equals($expected, $given);
    }

    /**
     * S-14: rejects requests that the browser marks as cross-site. Without the
     * headers (old browsers, CLI clients) the token alone decides.
     */
    public static function isSameOrigin(Request $request, ?string $baseUrl): bool
    {
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }

        $origin = $request->header('Origin');
        if ($origin === null || $origin === '') {
            return true;
        }

        // Before the setup there is no base URL yet; then the origin has to match
        // the host the request was sent to.
        // The Host header may carry a port ("localhost:8080"); only the name counts.
        $expectedHost = $baseUrl !== null
            ? (string) parse_url($baseUrl, PHP_URL_HOST)
            : (string) preg_replace('/:\d+$/', '', $request->host());
        $originHost = parse_url($origin, PHP_URL_HOST);

        return is_string($originHost) && strcasecmp($originHost, $expectedHost) === 0;
    }
}
