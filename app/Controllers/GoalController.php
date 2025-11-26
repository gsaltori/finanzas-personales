<?php
class GoalController extends BaseController {
  private Goal $model;

  public function __construct() {
    parent::__construct();
    $this->model = new Goal($this->db);
  }

  public function index() {
    $this->requireAuth();
    $userId = (int) $_SESSION['user_id'];
    $goals = $this->model->listForUser($userId);
    // calcular progreso por cada meta
    foreach ($goals as &$g) {
      $g['progress'] = $g['objetivo'] > 0 ? min(100, ($g['actual'] / $g['objetivo']) * 100) : 0;
    }
    $this->view('goals/index.php', [
      'goals' => $goals,
      'flash' => $this->getFlash(),
      'csrf' => $this->csrf_token()
    ]);
  }

  public function create() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }

    $userId = (int) $_SESSION['user_id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $objetivo = (float)($_POST['objetivo'] ?? 0);
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;

    if ($nombre === '' || $objetivo <= 0) { $this->setFlash('danger','Datos inválidos'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }

    $id = $this->model->create($userId, $nombre, $objetivo, $fecha_limite);
    $this->setFlash($id ? 'success' : 'danger', $id ? 'Meta creada.' : 'Error al crear meta.');
    header('Location: ' . $this->config['base_url'] . '/?r=goal/index');
    exit;
  }

  public function contribute() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }

    $id = (int)($_POST['id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    if ($id <= 0 || $amount <= 0) { $this->setFlash('danger','Datos inválidos'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }

    $ok = $this->model->addContribution($id, $amount);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Contribución agregada.' : 'Error al agregar contribución.');
    header('Location: ' . $this->config['base_url'] . '/?r=goal/index');
    exit;
  }

  public function delete() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $this->setFlash('danger','ID inválido'); header('Location: ' . $this->config['base_url'] . '/?r=goal/index'); exit; }
    $ok = $this->model->delete($id);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Meta eliminada.' : 'Error al eliminar.');
    header('Location: ' . $this->config['base_url'] . '/?r=goal/index');
    exit;
  }
}
