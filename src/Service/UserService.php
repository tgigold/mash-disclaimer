<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use PDO;

/**
 * Managing admin accounts. One role: every admin may create, deactivate and
 * reactivate others and set a new password for them (no e-mail reset, see the
 * deviation from S-29 in the README). Every change lands in the audit log (S-39).
 * The last active account can never be deactivated or deleted.
 */
final readonly class UserService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private AuditLogRepository $audit,
    ) {
    }

    public function create(User $actor, string $username, #[\SensitiveParameter] string $password, ?string $ip): User
    {
        $errors = [];
        if (preg_match(PasswordPolicy::USERNAME_PATTERN, $username) !== 1) {
            $errors['username'] = '3 to 64 letters, digits, dots, dashes or underscores.';
        } elseif ($this->users->findByUsername($username) !== null) {
            $errors['username'] = 'This username is taken.';
        }
        $problem = PasswordPolicy::problem($password);
        if ($problem !== null) {
            $errors['password'] = $problem;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = $this->users->create($username, PasswordPolicy::hash($password), $actor->id);
        $this->audit->add($actor->id, 'user.created', 'user', (string) $id, ['username' => $username], $ip);

        return $this->users->find($id) ?? throw new \LogicException('User vanished after creation.');
    }

    public function setPassword(User $actor, User $target, #[\SensitiveParameter] string $password, ?string $ip): void
    {
        $problem = PasswordPolicy::problem($password);
        if ($problem !== null) {
            throw new ValidationException(['password' => $problem]);
        }

        $this->users->setPassword($target->id, PasswordPolicy::hash($password));
        $this->audit->add($actor->id, 'user.password_set', 'user', (string) $target->id, ['username' => $target->username], $ip);
    }

    public function deactivate(User $actor, User $target, ?string $ip): void
    {
        $this->inTransaction(function () use ($actor, $target, $ip): void {
            // Lock the rows so two admins cannot deactivate the last two at once.
            $this->pdo->query('SELECT id FROM v4_users WHERE is_active = 1 FOR UPDATE');
            if ($target->isActive && $this->users->countActive() <= 1) {
                throw new ValidationException(['user' => 'The last active account cannot be deactivated.']);
            }
            $this->users->setActive($target->id, false);
            $this->audit->add($actor->id, 'user.deactivated', 'user', (string) $target->id, ['username' => $target->username], $ip);
        });
    }

    public function activate(User $actor, User $target, ?string $ip): void
    {
        $this->users->setActive($target->id, true);
        $this->audit->add($actor->id, 'user.activated', 'user', (string) $target->id, ['username' => $target->username], $ip);
    }

    /** D-8: an admin deletes their own account; the audit log keeps the actions without the name. */
    public function deleteOwnAccount(User $actor, ?string $ip): void
    {
        $this->inTransaction(function () use ($actor, $ip): void {
            $this->pdo->query('SELECT id FROM v4_users WHERE is_active = 1 FOR UPDATE');
            if ($this->users->countActive() <= 1) {
                throw new ValidationException(['user' => 'The last active account cannot be deleted.']);
            }
            $this->audit->add(null, 'user.deleted', 'user', (string) $actor->id, [], $ip);
            $this->users->delete($actor->id);
        });
    }

    /**
     * D-8: everything stored about this admin, as plain data for a download.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exported_at' => gmdate('c'),
            'account' => [
                'id' => $user->id,
                'username' => $user->username,
                'active' => $user->isActive,
                'created_at' => $user->createdAt->format('c'),
                'created_by' => $user->createdByName,
                'last_login_at' => $user->lastLoginAt?->format('c'),
            ],
            'audit_log' => $this->audit->page(1, 100_000, $user->id),
        ];
    }

    private function inTransaction(\Closure $work): void
    {
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
