<?php
// Espera: transaction, categories, csrf
$t = $transaction;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Editar transacción</h4>
  <a href="<?= base_url('?r=transaction/index') ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= base_url('?r=transaction/update') ?>" class="needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">

      <div class="row g-3">
        <div class="col-md-4 form-floating">
          <input type="number" name="monto" step="0.01" min="0.01" value="<?= $this->e($t['monto']) ?>" required class="form-control" placeholder="Monto">
          <label>Monto</label>
          <div class="invalid-feedback">Ingresa un monto válido.</div>
        </div>

        <div class="col-md-4 form-floating">
          <input type="date" name="fecha" value="<?= $this->e($t['fecha']) ?>" required class="form-control" placeholder="Fecha">
          <label>Fecha</label>
          <div class="invalid-feedback">Ingresa una fecha.</div>
        </div>

        <div class="col-md-4 form-floating">
          <select name="tipo" class="form-select" required>
            <option value="gasto" <?= $t['tipo'] === 'gasto' ? 'selected' : '' ?>>Gasto</option>
            <option value="ingreso" <?= $t['tipo'] === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
          </select>
          <label>Tipo</label>
        </div>

        <div class="col-md-6 form-floating">
          <select name="categoria_id" class="form-select">
            <option value="">Sin categoría</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= isset($t['categoria_id']) && $t['categoria_id']==$c['id'] ? 'selected' : '' ?>><?= $this->e($c['nombre']) ?> (<?= $this->e($c['tipo']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <label>Categoría</label>
        </div>

        <div class="col-6 form-floating">
          <input type="text" name="descripcion" value="<?= $this->e($t['descripcion']) ?>" class="form-control" placeholder="Descripción">
          <label>Descripción</label>
        </div>
      </div>

      <div class="mt-3 text-end">
        <button class="btn btn-secondary" type="button" onclick="location.href='<?= base_url('?r=transaction/index') ?>'">Cancelar</button>
        <button class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>
