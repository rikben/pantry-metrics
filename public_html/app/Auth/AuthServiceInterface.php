<?php
// /public_html/app/Auth/AuthServiceInterface.php

declare(strict_types=1);

namespace App\Auth;

interface AuthServiceInterface
{
    public function check(): bool;

    public function user(): array;

    public function logout(): void;
}
