<?php
// bootstrap.php
// Cargar variables desde .env simple (INI) para WAMP y arrancar sesión

if (file_exists(__DIR__ . '/.env')) {
    $vars = parse_ini_file(__DIR__ . '/.env');
    if ($vars !== false) {
        foreach ($vars as $k => $v) {
            // No sobrescribir variables ya presentes en entorno
            if (!isset($_ENV[$k])) {
                $_ENV[$k] = $v;
            }
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
