<?php
// app/Core/Router.php
namespace App\Core;

class Router
{
    private array $routes = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function add(string $method, string $path, string $handler): void
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => $this->normalize($path), 'handler' => $handler];
    }

    public function get(string $path, string $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, string $handler): void { $this->add('POST', $path, $handler); }

    private function normalize(string $path): string
    {
        $p = '/' . trim($path, '/');
        if ($p === '/'){ return '/'; }
        return rtrim($p, '/');
    }

    public function dispatch(string $requestUri, string $requestMethod): void
    {
        $uri = parse_url($requestUri, PHP_URL_PATH);
        $uri = $this->normalize($uri);

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($requestMethod)) continue;
            if ($route['path'] === $uri) {
                $this->invokeHandler($route['handler']);
                return;
            }
        }

        // 404
        http_response_code(404);
        echo "404 Not Found";
    }

    private function invokeHandler(string $handler): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $this->container);
            return;
        }
        // formato Controller@method
        if (strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler, 2);
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller {$class} not found");
            }
            $reflection = new \ReflectionClass($class);
            // intentar inyectar container si constructor requiere container services
            $controller = $reflection->newInstance();
            // Si la clase tiene metodo setContainer, pásalo
            if (method_exists($controller, 'setContainer')) {
                $controller->setContainer($this->container);
            }
            call_user_func([$controller, $method]);
            return;
        }

        throw new \RuntimeException("Invalid route handler: {$handler}");
    }
}
