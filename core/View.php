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

        if ($template === 'layouts/main') {
            $data = self::mergeNavNotifications($data);
        }

        extract($data, EXTR_SKIP);
        require $viewPath;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function mergeNavNotifications(array $data): array
    {
        if (!empty($data['publicPage'])) {
            return $data;
        }
        if (!array_key_exists('navNotifications', $data)) {
            $data['navNotifications'] = \App\Models\NavNotifications::summary();
        }

        return $data;
    }
}
