<?php
declare(strict_types=1);

// Cargar configuración
$config = require __DIR__ . '/../config/config.php';

// Configuración de errores según entorno
if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Configuración de sesión segura
$sessionConfig = [
    'name' => $config['session']['name'],
    'cookie_httponly' => $config['session']['httponly'],
    'cookie_samesite' => $config['session']['samesite'],
    'cookie_lifetime' => $config['session']['lifetime'],
    'use_strict_mode' => true,
    'use_only_cookies' => true,
];

// Solo agregar secure si no estamos en localhost
if ($config['env'] === 'production' && $config['session']['secure']) {
    $sessionConfig['cookie_secure'] = true;
}

session_start($sessionConfig);

// Inicializar rate_limit en sesión si no existe
if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
}

require_once __DIR__ . '/../config/database.php';

// Autoloader simple PSR-4-like
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/Controllers/' . $class . '.php',
        __DIR__ . '/../app/Models/' . $class . '.php',
        __DIR__ . '/../app/Helpers/' . $class . '.php'
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helpers globales - SOLO declarar si no existen
if (!function_exists('base_url')) {
    function base_url($path = '') {
        global $config;
        $b = rtrim($config['base_url'], '/');
        return $b . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('redirect')) {
    function redirect($path) {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null) {
        global $config;
        if ($key === null) {
            return $config;
        }
        
        // Soporta dot notation: config('db.host')
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

// Hacer config accesible globalmente
$GLOBALS['config'] = $config;

// Simple router: ?r=controller/action
$route = $_GET['r'] ?? 'dashboard/index';

// Validar formato de ruta (solo alfanuméricos, guiones y slash)
if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $route)) {
    http_response_code(400);
    echo "Ruta inválida";
    exit;
}

list($controllerName, $action) = explode('/', $route) + [null, null];
$controllerName = $controllerName ?: 'dashboard';
$action = $action ?: 'index';

$controllerClass = ucfirst($controllerName) . 'Controller';

if (!class_exists($controllerClass)) {
    http_response_code(404);
    if ($config['debug']) {
        echo "Controller not found: $controllerClass";
    } else {
        $errorPage = __DIR__ . '/../app/Views/errors/404.php';
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "404 - Page not found";
        }
    }
    exit;
}

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    http_response_code(404);
    if ($config['debug']) {
        echo "Action not found: $action in $controllerClass";
    } else {
        $errorPage = __DIR__ . '/../app/Views/errors/404.php';
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "404 - Page not found";
        }
    }
    exit;
}

try {
    $controller->$action();
} catch (Throwable $e) {
    // Logging de errores
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
    $logMessage = sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Respuesta al usuario
    http_response_code(500);
    if ($config['debug']) {
        echo "<h1>Error</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        $errorPage = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "500 - Internal Server Error";
        }
    }
}