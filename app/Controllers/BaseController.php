<?php
class BaseController {
  protected PDO $db;
  protected array $config;

  public function __construct() {
    $this->db = Database::getConnection();
    $this->config = require __DIR__ . '/../../config/config.php';
  }

  protected function view(string $path, array $data = []) {
    extract($data, EXTR_SKIP);
    // Header
    require __DIR__ . '/../Views/layouts/header.php';
    require __DIR__ . '/../Views/' . $path;
    require __DIR__ . '/../Views/layouts/footer.php';
    exit;
  }

  protected function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  protected function verify_csrf($token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
  }

  protected function isAuthenticated(): bool {
    return !empty($_SESSION['user_id']);
  }

  protected function requireAuth() {
    if (!$this->isAuthenticated()) {
      header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
      exit;
    }
  }

  protected function setFlash(string $type, string $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
  }

  protected function getFlash() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
  }

  protected function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
