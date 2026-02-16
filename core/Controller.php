<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }
}
