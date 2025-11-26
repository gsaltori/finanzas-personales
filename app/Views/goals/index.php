<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Metas de Ahorro</h4>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddGoal">Nueva meta</button>
</div>

<?php if (empty($goals)): ?>
  <div class="alert alert-info">Aún no tienes metas de ahorro.</div>
<?php else: ?>
  <div class="row">
    <?php foreach ($goals as $g): ?>
      <div class="col-md-6 mb-3">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($g['nombre']) ?></h5>
            <p><strong>Objetivo:</strong> <?= number_format($g['objetivo'],2,',','.') ?></p>
            <p><strong>Actual:</strong> <?= number_format($g['actual'],2,',','.') ?></p>
            <?php $prog = round($g['progress']); ?>
            <div class="progress mb-2" style="height:18px;">
              <div class="progress-bar" role="progressbar" style="width: <?= $prog ?>%;"><?= $prog ?>%</div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalContrib<?= (int)$g['id'] ?>">Aportar</button>
              <form method="post" action="<?= base_url('?r=goal/delete') ?>" onsubmit="return confirm('Eliminar meta?')">
                <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
            </div>

            <!-- Modal aporte -->
            <div class="modal fade" id="modalContrib<?= (int)$g['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form class="modal-content" method="post" action="<?= base_url('?r=goal/contribute') ?>">
                  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                  <div class="modal-header"><h5 class="modal-title">Aportar a <?= htmlspecialchars($g['nombre']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <div class="mb-2">
                      <label class="form-label">Monto</label>
                      <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                    </div>
                  </div>
                  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Aportar</button></div>
                </form>
              </div>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Modal crear meta -->
<div class="modal fade" id="modalAddGoal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= base_url('?r=goal/create') ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="modal-header"><h5 class="modal-title">Nueva meta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Objetivo</label>
          <input type="number" name="objetivo" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Fecha límite (opcional)</label>
          <input type="date" name="fecha_limite" class="form-control">
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear</button></div>
    </form>
  </div>
</div>
