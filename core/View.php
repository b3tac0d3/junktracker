<?php

declare(strict_types=1);

namespace Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile = VIEW_PATH . '/' . ltrim($view, '/') . '.php';
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$viewFile}");
        }
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layoutFile}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;
    }
}
