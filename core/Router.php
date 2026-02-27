<?php

declare(strict_types=1);

namespace Core;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, handler:array{0:class-string,1:string}}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
            if ($path === '') {
                $path = '/';
            }
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->{$action}($params);
            return;
        }

        http_response_code(404);
        View::renderFile('errors/404', ['pageTitle' => 'Not Found']);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => [$handler[0], $handler[1]],
        ];
    }
}
