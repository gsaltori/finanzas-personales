<?php
/**
 * index-minimal.php - Versión simplificada para debugging
 * 
 * INSTRUCCIONES:
 * 1. Renombrar index.php actual a index-backup.php
 * 2. Renombrar este archivo a index.php
 * 3. Acceder a la URL
 * 4. Ver qué línea específica falla
 */

declare(strict_types=1);

// Habilitar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug</title></head><body>";
echo "<h1>Iniciando debug...</h1>";

// Paso 1: Verificar config
echo "<p><strong>1. Cargando config...</strong></p>";
try {
    $config_path = __DIR__ . '/../config/config.php';
    
    if (!file_exists($config_path)) {
        die("<p style='color:red'>ERROR: config.php no existe en: {$config_path}</p>");
    }
    
    $config = require $config_path;
    
    if (!is_array($config)) {
        die("<p style='color:red'>ERROR: config.php no retorna un array</p>");
    }
    
    echo "<p style='color:green'>✓ Config cargado</p>";
    
} catch (Throwable $e) {
    die("<p style='color:red'>ERROR en config: " . htmlspecialchars($e->getMessage()) . "</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
}

// Paso 2: Verificar .env
echo "<p><strong>2. Verificando .env...</strong></p>";
$env_path = __DIR__ . '/../.env';

if (!file_exists($env_path)) {
    echo "<p style='color:orange'>WARNING: .env no existe. Usando valores por defecto.</p>";
} else {
    echo "<p style='color:green'>✓ .env existe</p>";
}

// Paso 3: Configurar sesión
echo "<p><strong>3. Iniciando sesión...</strong></p>";
try {
    $sessionConfig = [
        'name' => $config['session']['name'] ?? 'finanzas_sess',
        'cookie_httponly' => $config['session']['httponly'] ?? true,
        'cookie_samesite' => $config['session']['samesite'] ?? 'Lax',
        'cookie_lifetime' => $config['session']['lifetime'] ?? 7200,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ];
    
    // NO agregar secure en localhost
    if (($config['env'] ?? 'production') === 'production' && ($config['session']['secure'] ?? false)) {
        $sessionConfig['cookie_secure'] = true;
    }
    
    session_start($sessionConfig);
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    echo "<p style='color:green'>✓ Sesión iniciada</p>";
    
} catch (Throwable $e) {
    die("<p style='color:red'>ERROR en sesión: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// Paso 4: Cargar database.php
echo "<p><strong>4. Cargando database.php...</strong></p>";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "<p style='color:green'>✓ Database cargado</p>";
    
} catch (Throwable $e) {
    die("<p style='color:red'>ERROR en database.php: " . htmlspecialchars($e->getMessage()) . "</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
}

// Paso 5: Probar conexión
echo "<p><strong>5. Probando conexión a DB...</strong></p>";
try {
    $db = Database::getConnection();
    echo "<p style='color:green'>✓ Conexión exitosa</p>";
    
} catch (Throwable $e) {
    die("<p style='color:red'>ERROR de conexión: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// Paso 6: Autoloader
echo "<p><strong>6. Registrando autoloader...</strong></p>";
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

echo "<p style='color:green'>✓ Autoloader registrado</p>";

// Paso 7: Helpers
echo "<p><strong>7. Definiendo helpers...</strong></p>";

function base_url($path = '') {
    global $config;
    $b = rtrim($config['base_url'], '/');
    return $b . ($path ? '/' . ltrim($path, '/') : '');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function config($key = null, $default = null) {
    global $config;
    if ($key === null) return $config;
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) return $default;
        $value = $value[$k];
    }
    
    return $value;
}

$GLOBALS['config'] = $config;

echo "<p style='color:green'>✓ Helpers definidos</p>";

// Paso 8: Routing
echo "<p><strong>8. Procesando routing...</strong></p>";

$route = $_GET['r'] ?? 'dashboard/index';
echo "<p>Ruta solicitada: <code>{$route}</code></p>";

if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $route)) {
    die("<p style='color:red'>ERROR: Ruta inválida</p>");
}

list($controllerName, $action) = explode('/', $route) + [null, null];
$controllerName = $controllerName ?: 'dashboard';
$action = $action ?: 'index';

echo "<p>Controller: <code>{$controllerName}</code>, Action: <code>{$action}</code></p>";

$controllerClass = ucfirst($controllerName) . 'Controller';

echo "<p>Clase a cargar: <code>{$controllerClass}</code></p>";

// Paso 9: Verificar clase existe
echo "<p><strong>9. Verificando controller...</strong></p>";

if (!class_exists($controllerClass)) {
    die("<p style='color:red'>ERROR: Controller no existe: {$controllerClass}</p>");
}

echo "<p style='color:green'>✓ Controller existe</p>";

// Paso 10: Instanciar
echo "<p><strong>10. Instanciando controller...</strong></p>";

try {
    $controller = new $controllerClass();
    echo "<p style='color:green'>✓ Controller instanciado</p>";
    
} catch (Throwable $e) {
    die("<p style='color:red'>ERROR al instanciar: " . htmlspecialchars($e->getMessage()) . "</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
}

// Paso 11: Verificar método
echo "<p><strong>11. Verificando método...</strong></p>";

if (!method_exists($controller, $action)) {
    die("<p style='color:red'>ERROR: Método no existe: {$action}</p>");
}

echo "<p style='color:green'>✓ Método existe</p>";

// Paso 12: Ejecutar
echo "<p><strong>12. Ejecutando acción...</strong></p>";

try {
    $controller->$action();
    echo "<p style='color:green'>✓ Acción ejecutada correctamente</p>";
    
} catch (Throwable $e) {
    echo "<p style='color:red'>ERROR en ejecución: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    
    // Log
    $logDir = __DIR__ . '/../storage/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
        $logMessage = sprintf(
            "[%s] %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        echo "<p>Error guardado en: {$logFile}</p>";
    }
}

echo "</body></html>";