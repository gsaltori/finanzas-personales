<?php $c = $category; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Editar categoría</h4>
  <a href="<?= base_url('?r=category/index') ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= base_url('?r=category/update') ?>" class="needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

      <div class="mb-3 form-floating">
        <input type="text" name="nombre" class="form-control" placeholder="Nombre" value="<?= $this->e($c['nombre']) ?>" required>
        <label>Nombre</label>
        <div class="invalid-feedback">Ingresa un nombre.</div>
      </div>

      <div class="mb-3 form-floating">
        <select name="tipo" class="form-select" required>
          <option value="gasto" <?= $c['tipo'] === 'gasto' ? 'selected' : '' ?>>Gasto</option>
          <option value="ingreso" <?= $c['tipo'] === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
        </select>
        <label>Tipo</label>
      </div>

      <div class="text-end">
        <button class="btn btn-secondary" type="button" onclick="location.href='<?= base_url('?r=category/index') ?>'">Cancelar</button>
        <button class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
