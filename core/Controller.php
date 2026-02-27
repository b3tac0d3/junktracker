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
}
