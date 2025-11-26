<?php
class BudgetController extends BaseController {
  private Budget $model;
  private Category $categoryModel;

  public function __construct() {
    parent::__construct();
    $this->model = new Budget($this->db);
    $this->categoryModel = new Category($this->db);
  }

  public function index() {
    $this->requireAuth();
    $userId = (int) $_SESSION['user_id'];
    $mes = (int)($_GET['mes'] ?? date('n'));
    $anio = (int)($_GET['anio'] ?? date('Y'));

    $budgets = $this->model->listForUserMonth($userId, $mes, $anio);
    foreach ($budgets as &$b) {
      $spent = $this->model->getSpentForBudget($userId, (int)$b['categoria_id'], $mes, $anio);
      $b['spent'] = $spent;
      $b['progress'] = $b['monto_maximo'] > 0 ? min(100, ($spent / $b['monto_maximo']) * 100) : 0;
    }

    $categories = $this->categoryModel->listForUser($userId);

    $this->view('budgets/index.php', [
      'budgets' => $budgets,
      'categories' => $categories,
      'mes' => $mes,
      'anio' => $anio,
      'flash' => $this->getFlash(),
      'csrf' => $this->csrf_token()
    ]);
  }

  public function create() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }

    $userId = (int) $_SESSION['user_id'];
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $monto = (float)($_POST['monto_maximo'] ?? 0);
    $mes = (int)($_POST['mes'] ?? date('n'));
    $anio = (int)($_POST['anio'] ?? date('Y'));

    if ($categoria_id <= 0 || $monto <= 0) { $this->setFlash('danger','Datos inválidos'); header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }

    try {
      $id = $this->model->create($userId, $categoria_id, $monto, $mes, $anio);
      $this->setFlash('success','Presupuesto creado.');
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'uk_presupuesto_categoria_mes') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $sql = "SELECT id FROM presupuestos WHERE usuario_id = :uid AND categoria_id = :cat AND mes = :mes AND `anio` = :anio LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':cat' => $categoria_id, ':mes' => $mes, ':anio' => $anio]);
        $r = $stmt->fetch();
        if ($r) { $this->model->update((int)$r['id'], $monto); $this->setFlash('success','Presupuesto actualizado.'); }
        else $this->setFlash('danger','Error al crear presupuesto.');
      } else {
        $this->setFlash('danger','Error en la base de datos.');
      }
    }

    header('Location: ' . $this->config['base_url'] . '/?r=budget/index&mes=' . $mes . '&anio=' . $anio);
    exit;
  }

  public function delete() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $this->setFlash('danger','ID inválido'); header('Location: ' . $this->config['base_url'] . '/?r=budget/index'); exit; }
    $ok = $this->model->delete($id);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Presupuesto eliminado.' : 'Error al eliminar.');
    header('Location: ' . $this->config['base_url'] . '/?r=budget/index');
    exit;
  }
}
