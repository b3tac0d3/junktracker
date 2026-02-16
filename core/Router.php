<?php

declare(strict_types=1);

namespace Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = $this->stripBasePath($path);
        $path = $this->normalize($path);

        $handler = $this->routes[$method]['static'][$path] ?? null;
        $params = [];

        if ($handler === null) {
            foreach ($this->routes[$method]['dynamic'] ?? [] as $route) {
                if (preg_match($route['regex'], $path, $matches)) {
                    $handler = $route['handler'];
                    $params = array_intersect_key($matches, array_flip($route['params']));
                    break;
                }
            }
        }

        if ($handler === null) {
            http_response_code(404);
            if (class_exists('App\\Controllers\\ErrorController')) {
                (new \App\Controllers\ErrorController())->notFound();
                return;
            }
            echo '404 Not Found';
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            if (empty($params)) {
                $controller->$action();
            } else {
                $controller->$action($params);
            }
            return;
        }

        if (empty($params)) {
            call_user_func($handler);
        } else {
            call_user_func($handler, $params);
        }
    }

    private function stripBasePath(string $path): string
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return $path === '' ? '/' : $path;
    }

    private function normalize(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $normalized = $this->normalize($path);

        if (str_contains($normalized, '{')) {
            $params = [];
            $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($matches) use (&$params) {
                $params[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $normalized);

            $this->routes[$method]['dynamic'][] = [
                'regex' => '#^' . $regex . '$#',
                'params' => $params,
                'handler' => $handler,
            ];
            return;
        }

        $this->routes[$method]['static'][$normalized] = $handler;
    }
}
