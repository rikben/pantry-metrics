<?php
// /public_html/app/Core/Container.php

declare(strict_types=1);

namespace App\Core;

final class Container
{
    private static ?self $instance = null;
    private array $bindings = [];
    private array $resolved = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (isset($this->bindings[$id])) {
            return $this->resolved[$id] = ($this->bindings[$id])();
        }

        return $this->resolved[$id] = new $id();
    }
}
