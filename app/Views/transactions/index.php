<?php
// Variables esperadas: transactions, categories, page, pages, filters, flash, csrf
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0">Transacciones</h4>
  <div class="d-flex gap-2">
    <a href="<?= base_url('?r=transaction/index') ?>" class="btn btn-outline-secondary btn-sm">Refrescar</a>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">Nueva</button>
    <a href="<?= base_url('?r=import/index') ?>" class="btn btn-outline-primary btn-sm">Importar extracto</a>
    <a href="<?= base_url('?r=import/jobs') ?>" class="btn btn-sm btn-outline-info">Ver Jobs</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('?r=transaction/index') ?>">
      <input type="hidden" name="r" value="transaction/index">
      <div class="col-sm-3">
        <label class="form-label small">Desde</label>
        <input type="date" name="from" value="<?= $this->e($filters['from'] ?? '') ?>" class="form-control form-control-sm">
      </div>
      <div class="col-sm-3">
        <label class="form-label small">Hasta</label>
        <input type="date" name="to" value="<?= $this->e($filters['to'] ?? '') ?>" class="form-control form-control-sm">
      </div>
      <div class="col-sm-3">
        <label class="form-label small">Categoría</label>
        <select name="category" class="form-select form-select-sm">
          <option value="">Todas</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= isset($filters['category']) && $filters['category']==$c['id'] ? 'selected' : '' ?>><?= $this->e($c['nombre']) ?> (<?= $this->e($c['tipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small">Tipo</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="ingreso" <?= (isset($filters['type']) && $filters['type']=='ingreso') ? 'selected' : '' ?>>Ingreso</option>
          <option value="gasto" <?= (isset($filters['type']) && $filters['type']=='gasto') ? 'selected' : '' ?>>Gasto</option>
        </select>
      </div>
      <div class="col-sm-1">
        <button class="btn btn-outline-primary btn-sm w-100">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<div class="mb-2 d-flex justify-content-end">
  <a href="<?= base_url('?r=import/index') ?>" class="btn btn-sm btn-primary">
    <i class="fa-solid fa-file-import"></i> Importar extracto
  </a>
</div>

<div class="card mb-3">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:110px">Fecha</th>
            <th>Descripción</th>
            <th style="width:170px">Categoría</th>
            <th style="width:90px">Tipo</th>
            <th style="width:140px" class="text-end">Monto</th>
            <th style="width:120px" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transactions)): ?>
            <tr><td colspan="6" class="text-center py-4 small text-muted">No hay transacciones</td></tr>
          <?php else: ?>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><?= $this->e($t['fecha']) ?></td>
                <td class="text-truncate" style="max-width:420px"><?= $this->e($t['descripcion'] ?: '—') ?></td>
                <td><?= $this->e($t['categoria_nombre'] ?? 'Sin categoría') ?></td>
                <td>
                  <?php if ($t['tipo'] === 'ingreso'): ?>
                    <span class="badge badge-success"><?= $this->e($t['tipo']) ?></span>
                  <?php else: ?>
                    <span class="badge badge-danger"><?= $this->e($t['tipo']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= number_format($t['monto'],2,',','.') ?></td>
                <td class="text-end">
                  <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-secondary" data-edit-id="<?= (int)$t['id'] ?>">Editar</button>
                    <form method="post" action="<?= base_url('?r=transaction/delete') ?>" onsubmit="return confirm('Eliminar transacción?')">
                      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<nav class="d-flex justify-content-center">
  <ul class="pagination pagination-sm">
    <?php for ($p=1;$p<=$pages;$p++): ?>
      <li class="page-item <?= $p==$page ? 'active' : '' ?>">
        <a class="page-link" href="<?= base_url('?r=transaction/index&page='.$p) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<!-- Modal: nueva transacción -->
<div class="modal fade" id="modalAdd" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content needs-validation" method="post" action="<?= base_url('?r=transaction/create') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="modal-header">
        <h5 class="modal-title">Nueva transacción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6 form-floating">
            <input type="number" name="monto" step="0.01" min="0.01" required class="form-control" placeholder="Monto">
            <label>Monto</label>
            <div class="invalid-feedback">Ingresa un monto válido.</div>
          </div>
          <div class="col-6 form-floating">
            <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required class="form-control" placeholder="Fecha">
            <label>Fecha</label>
            <div class="invalid-feedback">Ingresa una fecha.</div>
          </div>

          <div class="col-6 form-floating">
            <select name="tipo" class="form-select" required>
              <option value="gasto" selected>Gasto</option>
              <option value="ingreso">Ingreso</option>
            </select>
            <label>Tipo</label>
          </div>

          <div class="col-6 form-floating">
            <select name="categoria_id" class="form-select">
              <option value="">Sin categoría</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= $this->e($c['nombre']) ?> (<?= $this->e($c['tipo']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <label>Categoría</label>
          </div>

          <div class="col-12 form-floating">
            <input type="text" name="descripcion" class="form-control" placeholder="Descripción">
            <label>Descripción</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm">Guardar</button>
      </div>
    </form>
  </div>
</div>
