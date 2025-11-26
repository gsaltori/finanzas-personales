<?php
// Variables esperadas: budgets, categories, mes, anio, flash, csrf
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0">Presupuestos</h4>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('?r=budget/index&mes='.$mes.'&anio='.$anio) ?>">Recargar</a>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddBudget">Nuevo presupuesto</button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('?r=budget/index') ?>">
      <div class="col-auto">
        <label class="form-label small">Mes</label>
        <select name="mes" class="form-select form-select-sm">
          <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $m ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small">Año</label>
        <input type="number" name="anio" min="2000" max="2100" value="<?= $anio ?>" class="form-control form-control-sm">
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-primary btn-sm">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($budgets)): ?>
  <div class="card"><div class="card-body"><div class="text-center small text-muted">No hay presupuestos para este mes.</div></div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($budgets as $b): ?>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <h6 class="mb-0"><?= htmlspecialchars($b['categoria_nombre']) ?></h6>
                <small class="text-muted">Límite: <?= number_format($b['monto_maximo'],2,',','.') ?></small>
              </div>
              <div class="text-end">
                <?php
                  $prog = (float)$b['progress'];
                  $cls = $prog >= 100 ? 'badge-danger' : ($prog >= 70 ? 'badge-warning' : 'badge-success');
                ?>
                <span class="badge-soft <?= $cls ?>"><?= round($prog) ?>%</span>
              </div>
            </div>

            <div class="small text-muted mb-2">Gastado: <?= number_format($b['spent'],2,',','.') ?></div>
            <div class="progress mb-3" style="height:12px;">
              <div class="progress-bar <?= $prog >= 100 ? 'bg-danger' : ($prog >= 70 ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= min(100,$prog) ?>%"></div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <form method="post" action="<?= base_url('?r=budget/delete') ?>" onsubmit="return confirm('Eliminar presupuesto?')">
                <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Modal: nuevo presupuesto -->
<div class="modal fade" id="modalAddBudget" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content needs-validation" method="post" action="<?= base_url('?r=budget/create') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="modal-header">
        <h5 class="modal-title">Nuevo presupuesto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12 form-floating">
            <select name="categoria_id" class="form-select" required>
              <option value="">Selecciona</option>
              <?php foreach ($categories as $c): if ($c['tipo'] !== 'gasto') continue; ?>
                <option value="<?= (int)$c['id'] ?>"><?= $this->e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <label>Categoría</label>
            <div class="invalid-feedback">Selecciona una categoría de gasto.</div>
          </div>

          <div class="col-6 form-floating">
            <input type="number" name="monto_maximo" step="0.01" min="0.01" required class="form-control" placeholder="Monto">
            <label>Monto máximo</label>
            <div class="invalid-feedback">Ingresa un monto válido.</div>
          </div>

          <div class="col-3 form-floating">
            <select name="mes" class="form-select">
              <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $m ?></option>
              <?php endfor; ?>
            </select>
            <label>Mes</label>
          </div>

          <div class="col-3 form-floating">
            <input type="number" name="anio" value="<?= $anio ?>" class="form-control">
            <label>Año</label>
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
