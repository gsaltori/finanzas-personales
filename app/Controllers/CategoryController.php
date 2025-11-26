<?php
class CategoryController extends BaseController {
  private Category $model;

  public function __construct() {
    parent::__construct();
    $this->model = new Category($this->db);
  }

  public function index() {
    $this->requireAuth();
    $userId = (int) $_SESSION['user_id'];
    $cats = $this->model->listForUser($userId);
    $this->view('categories/index.php', [
      'categories' => $cats,
      'flash' => $this->getFlash(),
      'csrf' => $this->csrf_token()
    ]);
  }

  public function create() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) {
      $this->setFlash('danger','Token CSRF inválido');
      header('Location: ' . $this->config['base_url'] . '/?r=category/index');
      exit;
    }
    $name = trim($_POST['nombre'] ?? '');
    $tipo = ($_POST['tipo'] ?? 'gasto') === 'ingreso' ? 'ingreso' : 'gasto';
    $userId = (int) $_SESSION['user_id'];
    if ($name === '') {
      $this->setFlash('danger','Nombre inválido');
      header('Location: ' . $this->config['base_url'] . '/?r=category/index');
      exit;
    }
    $id = $this->model->create($name, $tipo, $userId);
    $this->setFlash($id ? 'success' : 'danger', $id ? 'Categoría creada' : 'Error al crear categoría');
    header('Location: ' . $this->config['base_url'] . '/?r=category/index');
    exit;
  }

  public function edit() {
    $this->requireAuth();
    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { $this->setFlash('danger','ID inválido'); header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }

    $c = $this->model->findById($id);
    if (!$c) { $this->setFlash('danger','Categoría no encontrada.'); header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }
    if ($c['usuario_id'] && $c['usuario_id'] != $userId) {
      $this->setFlash('danger','Permiso denegado.');
      header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit;
    }

    $this->view('categories/edit.php', ['category'=>$c,'csrf'=>$this->csrf_token(),'flash'=>$this->getFlash()]);
  }

  public function update() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = ($_POST['tipo'] ?? 'gasto') === 'ingreso' ? 'ingreso' : 'gasto';
    if ($id <= 0 || $nombre === '') { $this->setFlash('danger','Datos inválidos'); header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }

    $c = $this->model->findById($id);
    if (!$c) { $this->setFlash('danger','Categoría no encontrada'); header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit; }

    $ok = $this->model->update($id, $nombre, $tipo);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Categoría actualizada.' : 'Error al actualizar.');
    header('Location: ' . $this->config['base_url'] . '/?r=category/index'); exit;
  }
}
