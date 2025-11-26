<?php
/**
 * Database connection handler
 * Usa Singleton pattern para mantener una única conexión
 */

// Solo declarar la clase si no existe
if (!class_exists('Database')) {
    class Database {
        private static ?PDO $pdo = null;

        public static function getConnection(): PDO {
            if (self::$pdo === null) {
                // Obtener configuración
                // Si ya existe en GLOBALS, usar esa
                if (isset($GLOBALS['config'])) {
                    $c = $GLOBALS['config']['db'];
                } else {
                    // Si no, cargar config
                    $configFile = __DIR__ . '/config.php';
                    if (file_exists($configFile)) {
                        $config = require $configFile;
                        $c = $config['db'];
                    } else {
                        throw new Exception('No se pudo cargar la configuración de la base de datos');
                    }
                }
                
                $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                try {
                    self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $options);
                } catch (PDOException $e) {
                    // Log el error pero no exponer credenciales
                    error_log("Database connection failed: " . $e->getMessage());
                    throw new Exception('No se pudo conectar a la base de datos');
                }
            }
            return self::$pdo;
        }
    }
}