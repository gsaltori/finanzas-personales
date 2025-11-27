<?php
// app/Services/DatabaseService.php
namespace App\Services;

use PDO;
use PDOException;

class DatabaseService
{
    private PDO $pdo;

    public function __construct(string $host, string $db, string $user, string $pass, int $port = 3306)
    {
        $dsn = "mysql:host={$host};dbname={$db};port={$port};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Error de conexión a MySQL: ' . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
