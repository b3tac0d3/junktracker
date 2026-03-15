<?php

declare(strict_types=1);

namespace Core;

final class View
{
    public static function renderFile(string $template, array $data = []): void
    {
        $viewPath = base_path('app/Views/' . $template . '.php');
        if (!is_file($viewPath)) {
            ErrorHandler::renderHttpError(500, 'View rendering failed', 'The requested view could not be loaded.');
            return;
        }

        extract($data, EXTR_SKIP);
        require $viewPath;
    }
}
