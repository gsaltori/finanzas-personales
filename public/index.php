<?php
// public/index.php
require_once __DIR__ . '/../bootstrap.php';

// Autoloader (Composer)
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Container;

// Crear container mínimo y registrar servicios si lo deseas
$container = new Container();

// Registrar DatabaseService con parámetros de .env
$container->set('db', function() {
    return new App\Services\DatabaseService(
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_NAME'] ?? 'finanzas',
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        (int)($_ENV['DB_PORT'] ?? 3306)
    );
});

// Registrar AuthService
$container->set('auth', function($c) {
    return new App\Services\AuthService($c->get('db'));
});

// Registrar ParserService
$container->set('parser', function() {
    return new App\Services\ParserService();
});

// Rutas simples
$router = new Router($container);
$router->get('/', 'App\Controllers\HomeController@index');
$router->get('/login', 'App\Controllers\AuthController@loginView');
$router->post('/login', 'App\Controllers\AuthController@loginAction');
$router->get('/logout', 'App\Controllers\AuthController@logoutAction');
$router->get('/dashboard', 'App\Controllers\HomeController@dashboard');

// Ejecutar router
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
