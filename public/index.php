<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$router = require dirname(__DIR__) . '/routes/web.php';
$router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), (string) ($_SERVER['REQUEST_URI'] ?? '/'));
