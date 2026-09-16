<?php
// /public_html/app/Auth/DevelopmentAuthService.php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;

final class DevelopmentAuthService implements AuthServiceInterface
{
    public function check(): bool
    {
        return true;
    }

    public function user(): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, email, display_name, role FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => 'developer@example.test']);
        $user = $statement->fetch();

        if (!$user) {
            throw new \RuntimeException('Development user missing. Run database migrations.');
        }

        return $user;
    }

    public function logout(): void
    {
        session_regenerate_id(true);
    }
}
