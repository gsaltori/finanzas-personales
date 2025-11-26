<?php
/**
 * config-fallback.php
 * 
 * Configuración de emergencia SIN dependencia de .env
 * 
 * INSTRUCCIONES DE USO:
 * 1. Copiar este archivo como config.php
 * 2. Editar las credenciales directamente abajo
 * 3. Una vez funcionando, migrar a sistema .env
 */

// ⚠️ EDITAR ESTAS CREDENCIALES ⚠️
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_DATABASE = 'finanzas';
$DB_USERNAME = 'finanzas_user';
$DB_PASSWORD = 'z:6F6ZNCtujJv_U';  // ⬅️ CAMBIAR ESTO

$BASE_URL = '/finanzas-personales/public';  // ⬅️ CAMBIAR SI ES NECESARIO

// Configuración básica
return [
    'env' => 'development',
    'debug' => true,
    'base_url' => $BASE_URL,
    
    'db' => [
        'host' => $DB_HOST,
        'port' => $DB_PORT,
        'dbname' => $DB_DATABASE,
        'user' => $DB_USERNAME,
        'pass' => $DB_PASSWORD,
    ],
    
    'session' => [
        'name' => 'finanzas_sess',
        'secure' => false,  // ⬅️ false para localhost
        'httponly' => true,
        'samesite' => 'Lax',
        'lifetime' => 7200,
    ],
    
    'security' => [
        'csrf_token_length' => 32,
    ],
    
    'rate_limit' => [
        'login_attempts' => 5,
        'login_window' => 900,
        'import_attempts' => 10,
        'import_window' => 3600,
    ],
    
    'upload' => [
        'max_size' => 10485760,
        'allowed_extensions' => ['pdf', 'html', 'htm', 'txt'],
    ],
    
    'poppler_bin' => null,
];