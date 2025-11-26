<div class="row justify-content-center">
  <div class="col-md-6">
    <h3>Crear cuenta</h3>
    <form method="post" action="<?= base_url('?r=auth/register') ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" required class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" required class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <input type="password" name="password" required class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Repetir Contraseña</label>
        <input type="password" name="password2" required class="form-control">
      </div>
      <button class="btn btn-success">Crear cuenta</button>
    </form>
  </div>
</div>
