<?php
class DashboardController extends BaseController {
  private Transaction $transModel;
  private Category $catModel;
  private Budget $budgetModel;

  public function __construct() {
    parent::__construct();
    $this->transModel = new Transaction($this->db);
    $this->catModel = new Category($this->db);
    $this->budgetModel = new Budget($this->db);
  }

  public function index() {
    $this->requireAuth();
    $userId = (int) $_SESSION['user_id'];

    $mes = (int)($_GET['mes'] ?? date('n'));
    $anio = (int)($_GET['anio'] ?? date('Y'));

    $summary = $this->transModel->summaryForMonth($userId, $mes, $anio);
    $byCategory = $this->transModel->sumByCategoryForMonth($userId, $mes, $anio);
    $categories = $this->catModel->listForUser($userId);

    $budgetsRaw = $this->budgetModel->listForUserMonth($userId, $mes, $anio);
    $budgetAlerts = [];
    foreach ($budgetsRaw as $b) {
      $spent = $this->budgetModel->getSpentForBudget($userId, (int)$b['categoria_id'], $mes, $anio);
      $limit = (float)$b['monto_maximo'];
      $ratio = $limit > 0 ? ($spent / $limit) : 0;
      $progress = $limit > 0 ? min(100.0, $ratio * 100.0) : 0.0;
      $status = 'normal';
      if ($ratio >= 1.0) $status = 'exceeded';
      elseif ($ratio >= 0.7) $status = 'warning';

      $budgetAlerts[] = [
        'id' => $b['id'],
        'categoria' => $b['categoria_nombre'],
        'limit' => $limit,
        'spent' => $spent,
        'progress' => $progress,
        'ratio' => $ratio,
        'status' => $status
      ];
    }

    usort($budgetAlerts, function($a,$b){
      $order = ['exceeded' => 0, 'warning' => 1, 'normal' => 2];
      return ($order[$a['status']] ?? 3) <=> ($order[$b['status']] ?? 3);
    });

    $this->view('dashboard.php', [
      'summary' => $summary,
      'byCategory' => $byCategory,
      'categories' => $categories,
      'budgets' => $budgetAlerts,
      'mes' => $mes,
      'anio' => $anio,
      'flash' => $this->getFlash(),
      'csrf' => $this->csrf_token()
    ]);
  }
}
