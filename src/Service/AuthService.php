<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\LoginProtectionRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\Ip;

/**
 * Login, logout and "who is signed in". Implements the login rules of the
 * coding rules: Argon2id with rehash (S-27), no hint whether an account exists
 * (S-30), lock after failures with an exemption for known devices (S-31),
 * session epoch (S-10), idle and absolute timeout (S-11), new session id on
 * login (S-9).
 */
final class AuthService
{
    public const int IP_LIMIT = 5;

    public const int GLOBAL_LIMIT = 25;

    public const int LOCK_SECONDS = 900;

    public const string DEVICE_COOKIE = '__Host-tater-device';

    public const int DEVICE_LIFETIME_SECONDS = 365 * 86400;

    private const string SESSION_KEY = 'auth';

    private ?User $current = null;

    private bool $resolved = false;

    /** @param \Closure(): int|null $clock */
    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly LoginProtectionRepository $protection,
        private readonly AuditLogRepository $audit,
        private readonly ErrorLog $errorLog,
        private readonly Config $config,
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function attempt(
        string $username,
        #[\SensitiveParameter] string $password,
        string $ip,
        ?string $deviceToken,
    ): LoginResult {
        $this->protection->pruneAttempts(self::LOCK_SECONDS);

        if ($this->protection->failuresForIp($ip, self::LOCK_SECONDS) >= self::IP_LIMIT) {
            $this->errorLog->security('login refused, IP locked', Ip::shorten($ip));

            return LoginResult::locked($this->protection->secondsUntilIpUnlocked($ip, self::LOCK_SECONDS));
        }

        $knownDevice = $deviceToken !== null && $this->protection->isKnownDevice(self::hashToken($deviceToken));
        if (!$knownDevice && $this->protection->failuresTotal(self::LOCK_SECONDS) >= self::GLOBAL_LIMIT) {
            $this->errorLog->security('login refused, global lock', Ip::shorten($ip));

            return LoginResult::locked(self::LOCK_SECONDS);
        }

        $row = $this->users->findByUsername($username);
        // S-30: verify against a dummy hash for unknown users, so the answer
        // takes about as long as for an existing account.
        $valid = password_verify($password, $row['password_hash'] ?? self::dummyHash());

        if ($row === null || !$valid || !$row['is_active']) {
            $this->protection->recordFailure($ip);
            $this->errorLog->security('failed login', Ip::shorten($ip));

            return LoginResult::failed();
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_ARGON2ID)) {
            $this->users->rehash($row['id'], PasswordPolicy::hash($password));
        }

        $user = $this->users->find($row['id']);
        if ($user === null) {
            return LoginResult::failed();
        }

        $this->session->regenerate();
        $now = $this->now();
        $this->session->set(self::SESSION_KEY, [
            'user_id' => $user->id,
            'epoch' => $user->sessionEpoch,
            'login_at' => $now,
            'seen_at' => $now,
        ]);
        $this->users->touchLogin($user->id);
        $this->audit->add($user->id, 'user.login', 'user', (string) $user->id, shortenedIp: Ip::shorten($ip));

        $token = $knownDevice ? (string) $deviceToken : bin2hex(random_bytes(32));
        $this->protection->rememberDevice(self::hashToken($token), $user->id);

        $this->current = $user;
        $this->resolved = true;

        return LoginResult::success($user, $token);
    }

    /** The signed-in admin, or null. Ends sessions that are no longer valid. */
    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->current;
        }
        $this->resolved = true;

        $data = $this->session->get(self::SESSION_KEY);
        if (!is_array($data) || !is_int($data['user_id'] ?? null) || !is_int($data['epoch'] ?? null)
            || !is_int($data['login_at'] ?? null) || !is_int($data['seen_at'] ?? null)) {
            return null;
        }

        $now = $this->now();
        $expired = $now - $data['seen_at'] > $this->config->sessionIdleSeconds()
            || $now - $data['login_at'] > $this->config->sessionAbsoluteSeconds();

        $user = $expired ? null : $this->users->find($data['user_id']);
        if ($user === null || !$user->isActive || $user->sessionEpoch !== $data['epoch']) {
            $this->session->remove(self::SESSION_KEY);

            return null;
        }

        $data['seen_at'] = $now;
        $this->session->set(self::SESSION_KEY, $data);

        return $this->current = $user;
    }

    public function logout(string $ip): void
    {
        $user = $this->user();
        if ($user !== null) {
            $this->audit->add($user->id, 'user.logout', 'user', (string) $user->id, shortenedIp: Ip::shorten($ip));
        }
        $this->session->destroy();
        $this->current = null;
    }

    /** S-35: asks for the own password again before sensitive actions. */
    public function confirmPassword(User $user, #[\SensitiveParameter] string $password): bool
    {
        $hash = $this->users->passwordHash($user->id);

        return $hash !== null && password_verify($password, $hash);
    }

    /**
     * After the own password changed, the epoch went up and would end this
     * session too; keep the current one alive with a fresh id (S-9).
     */
    public function keepSessionAfterPasswordChange(User $user): void
    {
        $fresh = $this->users->find($user->id);
        $data = $this->session->get(self::SESSION_KEY);
        if ($fresh === null || !is_array($data)) {
            return;
        }
        $data['epoch'] = $fresh->sessionEpoch;
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $data);
        $this->current = $fresh;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= password_hash('timing-equaliser', PASSWORD_ARGON2ID);
    }
}
