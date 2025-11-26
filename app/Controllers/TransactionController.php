<?php
class TransactionController extends BaseController {
  private Transaction $model;
  private Category $catModel;

  public function __construct() {
    parent::__construct();
    $this->model = new Transaction($this->db);
    $this->catModel = new Category($this->db);
  }

  public function index() {
    $this->requireAuth();
    $userId = (int) $_SESSION['user_id'];

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $filters = [];
    if (!empty($_GET['from'])) $filters['from'] = $_GET['from'];
    if (!empty($_GET['to'])) $filters['to'] = $_GET['to'];
    if (!empty($_GET['category'])) $filters['category'] = (int)$_GET['category'];
    if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];

    $total = $this->model->countByUser($userId, $filters);
    $transactions = $this->model->getByUserWithFilters($userId, $filters, $limit, $offset);
    $categories = $this->catModel->listForUser($userId);

    $this->view('transactions/index.php', [
      'transactions' => $transactions,
      'categories' => $categories,
      'page' => $page,
      'pages' => max(1, ceil($total / $limit)),
      'filters' => $filters,
      'flash' => $this->getFlash(),
      'csrf' => $this->csrf_token()
    ]);
  }

  public function create() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('HTTP/1.1 405 Method Not Allowed');
      exit;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) {
      $this->setFlash('danger','Token CSRF inválido');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
      exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $monto = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = ($_POST['tipo'] ?? 'gasto') === 'ingreso' ? 'ingreso' : 'gasto';

    if (!$monto || $monto <= 0) {
      $this->setFlash('danger','Monto inválido.');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
      exit;
    }

    $id = $this->model->create($userId, $monto, $fecha, $categoria_id, $descripcion, $tipo);
    if ($id) {
      $this->setFlash('success','Transacción registrada.');
    } else {
      $this->setFlash('danger','Error al guardar la transacción.');
    }
    header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
    exit;
  }

  public function edit() {
    $this->requireAuth();
    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { $this->setFlash('danger','ID inválido'); header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit; }

    $t = $this->model->findById($id);
    if (!$t || $t['usuario_id'] != $userId) {
      $this->setFlash('danger','Transacción no encontrada o permiso denegado.');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit;
    }

    $categories = $this->catModel->listForUser($userId);
    $this->view('transactions/edit.php', ['transaction'=>$t,'categories'=>$categories,'csrf'=>$this->csrf_token(),'flash'=>$this->getFlash()]);
  }

  public function update() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit; }

    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);
    $monto = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
    $fecha = $_POST['fecha'] ?? null;
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = ($_POST['tipo'] ?? 'gasto') === 'ingreso' ? 'ingreso' : 'gasto';

    if ($id <= 0 || !$monto || $monto <= 0 || !$fecha) {
      $this->setFlash('danger','Datos inválidos para actualización.');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/edit&id=' . $id); exit;
    }

    $t = $this->model->findById($id);
    if (!$t || $t['usuario_id'] != $userId) {
      $this->setFlash('danger','Transacción no encontrada o permiso denegado.');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit;
    }

    $ok = $this->model->update($id, $userId, $monto, $fecha, $categoria_id, $descripcion, $tipo);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Transacción actualizada.' : 'Error al actualizar.');
    header('Location: ' . $this->config['base_url'] . '/?r=transaction/index'); exit;
  }

  public function delete() {
    $this->requireAuth();
    $id = (int)($_POST['id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) {
      $this->setFlash('danger','Token CSRF inválido');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
      exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $t = $this->model->findById($id);
    if (!$t || $t['usuario_id'] != $userId) {
      $this->setFlash('danger','Transacción no encontrada o permiso denegado.');
      header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
      exit;
    }

    $ok = $this->model->delete($id);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Transacción eliminada.' : 'Error al eliminar.');
    header('Location: ' . $this->config['base_url'] . '/?r=transaction/index');
    exit;
  }

  // AJAX: devolver JSON con datos de transacción
  public function ajaxGet() {
    $this->requireAuth();
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID inválido']); exit; }

    $t = $this->model->findById($id);
    $userId = (int)$_SESSION['user_id'];
    if (!$t || $t['usuario_id'] != $userId) { http_response_code(403); echo json_encode(['error' => 'Permiso denegado']); exit; }

    echo json_encode(['data' => $t]);
    exit;
  }

  // AJAX: actualizar via POST form-encoded
  public function ajaxUpdate() {
    $this->requireAuth();
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Método no permitido']); exit; }

    $input = $_POST;
    $token = $input['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { http_response_code(400); echo json_encode(['error' => 'Token CSRF inválido']); exit; }

    $userId = (int)$_SESSION['user_id'];
    $id = (int)($input['id'] ?? 0);
    $monto = filter_var($input['monto'] ?? null, FILTER_VALIDATE_FLOAT);
    $fecha = $input['fecha'] ?? null;
    $categoria_id = !empty($input['categoria_id']) ? (int)$input['categoria_id'] : null;
    $descripcion = trim($input['descripcion'] ?? '');
    $tipo = ($input['tipo'] ?? 'gasto') === 'ingreso' ? 'ingreso' : 'gasto';

    if ($id <= 0 || !$monto || !$fecha) { http_response_code(422); echo json_encode(['error' => 'Datos inválidos']); exit; }

    $t = $this->model->findById($id);
    if (!$t || $t['usuario_id'] != $userId) { http_response_code(403); echo json_encode(['error' => 'Permiso denegado']); exit; }

    $ok = $this->model->update($id, $userId, $monto, $fecha, $categoria_id, $descripcion, $tipo);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'No se pudo actualizar']); exit; }

    $new = $this->model->findById($id);
    echo json_encode(['success' => true, 'data' => $new]);
    exit;
  }
}
