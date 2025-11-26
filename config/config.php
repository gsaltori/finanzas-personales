<?php
/**
 * Configuration loader con soporte para .env
 * Lee variables de entorno y provee valores por defecto seguros
 */

// Cargar variables de entorno desde .env si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comentarios
        if (strpos(trim($line), '#') === 0) continue;
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover quotes si existen
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Solo establecer si no existe ya en $_ENV o $_SERVER
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

/**
 * Helper para obtener variable de entorno con fallback
 * IMPORTANTE: Solo declarar si no existe
 */
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }
        
        return $value;
    }
}

// Configuración de la aplicación
return [
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'base_url' => env('APP_BASE_URL', '/finanzas-personales/public'),
    
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'dbname' => env('DB_DATABASE', 'finanzas'),
        'user' => env('DB_USERNAME', 'finanzas_user'),
        'pass' => env('DB_PASSWORD', ''),
    ],
    
    'session' => [
        'name' => env('SESSION_NAME', 'finanzas_sess'),
        'secure' => env('SESSION_SECURE', true),
        'httponly' => env('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Strict'),
        'lifetime' => env('SESSION_LIFETIME', 7200),
    ],
    
    'security' => [
        'csrf_token_length' => env('CSRF_TOKEN_LENGTH', 32),
    ],
    
    'rate_limit' => [
        'login_attempts' => env('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
        'login_window' => env('RATE_LIMIT_LOGIN_WINDOW', 900), // 15 minutos
        'import_attempts' => env('RATE_LIMIT_IMPORT_ATTEMPTS', 10),
        'import_window' => env('RATE_LIMIT_IMPORT_WINDOW', 3600), // 1 hora
    ],
    
    'upload' => [
        'max_size' => env('MAX_UPLOAD_SIZE', 10485760), // 10MB
        'allowed_extensions' => explode(',', env('ALLOWED_UPLOAD_EXTENSIONS', 'pdf,html,htm,txt')),
    ],
    
    'poppler_bin' => env('POPPLER_BIN_PATH', null),
];