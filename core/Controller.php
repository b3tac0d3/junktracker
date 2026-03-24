<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    protected function render(string $template, array $data = []): void
    {
        $data['viewFile'] = 'app/Views/' . $template . '.php';
        View::renderFile('layouts/main', $data);
    }

    /** Print-ready HTML (no app chrome). */
    protected function renderDocument(string $template, array $data = []): void
    {
        $data['viewFile'] = base_path('app/Views/' . $template . '.php');
        extract($data, EXTR_SKIP);
        require base_path('app/Views/layouts/document.php');
    }
}
