<?php
// Variables esperadas: categories, flash, csrf
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0">Categorías</h4>
  <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCat">Nueva categoría</button>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($categories as $c): ?>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold"><?= $this->e($c['nombre']) ?></div>
                <div class="small text-muted"><?= $this->e($c['tipo']) ?> <?= $c['usuario_id'] ? '(Personal)' : '(Global)' ?></div>
              </div>
              <div>
                <!-- Placeholder: editar/eliminar si se implementa -->
                <!-- <button class="btn btn-sm btn-outline-secondary">Editar</button> -->
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Modal crear categoría -->
<div class="modal fade" id="modalCat" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content needs-validation" method="post" action="<?= base_url('?r=category/create') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="modal-header">
        <h5 class="modal-title">Crear categoría</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12 form-floating">
            <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
            <label>Nombre</label>
            <div class="invalid-feedback">Ingresa un nombre.</div>
          </div>
          <div class="col-12 form-floating">
            <select name="tipo" class="form-select" required>
              <option value="gasto">Gasto</option>
              <option value="ingreso">Ingreso</option>
            </select>
            <label>Tipo</label>
            <div class="invalid-feedback">Selecciona un tipo.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm">Crear</button>
      </div>
    </form>
  </div>
</div>
