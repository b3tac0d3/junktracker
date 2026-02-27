<?php

declare(strict_types=1);

namespace Core;

final class View
{
    public static function renderFile(string $template, array $data = []): void
    {
        $viewPath = base_path('app/Views/' . $template . '.php');
        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            return;
        }

        extract($data, EXTR_SKIP);
        require $viewPath;
    }
}
