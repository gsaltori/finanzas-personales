<?php
// app/Views/import/jobs.php
// Asegurarse de tener valores por defecto si controlador no los envió
$jobs = $jobs ?? [];
$csrf = $csrf ?? '';
$page = isset($page) ? (int)$page : 1;
$pages = isset($pages) ? (int)$pages : 1;
$limit = isset($limit) ? (int)$limit : 20;
$total = isset($total) ? (int)$total : count($jobs);
$filters = $filters ?? ['estado'=>'all','from'=>'','to'=>''];
$f = $filters;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Import Jobs</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('?r=import/index') ?>">Nuevo import</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= base_url('?r=import/jobs') ?>">Refrescar</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?= base_url('?r=import/jobs') ?>" class="row g-2 align-items-end">
      <input type="hidden" name="r" value="import/jobs">
      <div class="col-auto">
        <label class="form-label small">Estado</label>
        <select name="estado" class="form-select form-select-sm">
          <option value="all" <?= $f['estado'] === 'all' ? 'selected' : '' ?>>Todos</option>
          <option value="pending" <?= $f['estado'] === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="done" <?= $f['estado'] === 'done' ? 'selected' : '' ?>>Done</option>
          <option value="error" <?= $f['estado'] === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
      </div>

      <div class="col-auto">
        <label class="form-label small">Desde</label>
        <input type="date" name="from" value="<?= $this->e($f['from']) ?>" class="form-control form-control-sm">
      </div>

      <div class="col-auto">
        <label class="form-label small">Hasta</label>
        <input type="date" name="to" value="<?= $this->e($f['to']) ?>" class="form-control form-control-sm">
      </div>

      <div class="col-auto">
        <label class="form-label small">Por página</label>
        <select name="limit" class="form-select form-select-sm">
          <?php foreach ([10,20,50,100] as $L): ?>
            <option value="<?= $L ?>" <?= (int)$limit === $L ? 'selected' : '' ?>><?= $L ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead class="table-light"><tr>
          <th>ID</th><th>Origen</th><th>Usuario</th><th>Filas</th><th>Importadas</th><th>Estado</th><th>Creado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
          <?php if (empty($jobs)): ?>
            <tr><td colspan="8" class="text-center py-4 small text-muted">No hay jobs de import</td></tr>
          <?php else: foreach ($jobs as $j): ?>
            <tr>
              <td><?= (int)$j['id'] ?></td>
              <td><?= htmlspecialchars($j['origen'] ?? '') ?></td>
              <td><?= (int)$j['usuario_id'] ?></td>
              <td><?= (int)$j['filas_total'] ?></td>
              <td><?= (int)$j['filas_importadas'] ?></td>
              <td><?= $this->e($j['estado']) ?></td>
              <td><?= $this->e($j['creado_en']) ?></td>
              <td>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('?r=import/view&job_id=' . (int)$j['id']) ?>">Ver</a>
                  <form method="post" action="<?= base_url('?r=import/runJob') ?>" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                    <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                    <button class="btn btn-sm btn-success" onclick="return confirm('Reprocesar job?')">Reprocesar</button>
                  </form>
                  <form method="post" action="<?= base_url('?r=import/deleteJob') ?>" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                    <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar job y filas?')">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
// paginación simple (asegurar query base incluye filtros)
$queryBase = '?r=import/jobs';
if (!empty($f['estado'])) $queryBase .= '&estado=' . urlencode($f['estado']);
if (!empty($f['from'])) $queryBase .= '&from=' . urlencode($f['from']);
if (!empty($f['to'])) $queryBase .= '&to=' . urlencode($f['to']);
$queryBase .= '&limit=' . (int)$limit . '&page=';
?>
<nav class="d-flex justify-content-between align-items-center">
  <div class="small text-muted">Mostrando página <?= (int)$page ?> de <?= (int)$pages ?> — <?= (int)$total ?> jobs</div>
  <ul class="pagination pagination-sm mb-0">
    <?php
      $start = max(1, $page - 3);
      $end = min($pages, $page + 3);
      if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= base_url($queryBase . ($page-1)) ?>">&laquo;</a></li>
    <?php endif;
      for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === (int)$page ? 'active' : '' ?>"><a class="page-link" href="<?= base_url($queryBase . $p) ?>"><?= $p ?></a></li>
    <?php endfor;
      if ($page < $pages): ?>
        <li class="page-item"><a class="page-link" href="<?= base_url($queryBase . ($page+1)) ?>">&raquo;</a></li>
    <?php endif; ?>
  </ul>
</nav>
