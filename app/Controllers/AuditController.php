<?php
class AuditController extends BaseController {
  private Audit $model;
  public function __construct() { parent::__construct(); $this->model = new Audit($this->db); }

  public function index() {
    $this->requireAuth();
    $rows = $this->model->listRecent(100);
    $this->view('audit/index.php', ['rows'=>$rows, 'flash'=>$this->getFlash()]);
  }
}
