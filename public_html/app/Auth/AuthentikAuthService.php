<?php
// /public_html/app/Auth/AuthentikAuthService.php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;

final class AuthentikAuthService implements AuthServiceInterface
{
    private bool $resolved = false;
    private ?array $cachedUser = null;

    public function check(): bool
    {
        return $this->resolveUser() !== null;
    }

    public function user(): array
    {
        $user = $this->resolveUser();

        if ($user === null) {
            throw new \RuntimeException(
                'Authentication required.'
            );
        }

        return $user;
    }

    public function logout(): void
    {
        unset(
            $_SESSION['auth_user_id'],
            $_SESSION['auth_expires_at'],
            $_SESSION['oidc_id_token'],
            $_SESSION['oidc_transaction']
        );

        $this->resolved = true;
        $this->cachedUser = null;

        session_regenerate_id(true);
    }

    private function resolveUser(): ?array
    {
        if ($this->resolved) {
            return $this->cachedUser;
        }

        $this->resolved = true;

        $userId = (int) (
            $_SESSION['auth_user_id']
            ?? 0
        );
        $expiresAt = (int) (
            $_SESSION['auth_expires_at']
            ?? 0
        );

        if (
            $userId < 1
            || $expiresAt < time()
        ) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT
                id,
                external_subject,
                identity_provider,
                email,
                display_name,
                role
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);

        $user = $statement->fetch();

        if (!$user) {
            return null;
        }

        $this->cachedUser = $user;

        return $this->cachedUser;
    }
}
