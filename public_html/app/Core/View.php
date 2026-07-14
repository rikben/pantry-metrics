<?php
// /public_html/app/Core/View.php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $templatePath = VIEW_PATH . '/' . $template . '.php';

        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = ob_get_clean();

        require VIEW_PATH . '/layouts/app.php';
    }
}
