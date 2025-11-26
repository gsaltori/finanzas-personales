<div class="row justify-content-center">
  <div class="col-md-5">
    <h3>Iniciar sesión</h3>
    <form method="post" action="<?= base_url('?r=auth/login') ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" required class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <input type="password" name="password" required class="form-control">
      </div>
      <button class="btn btn-primary">Entrar</button>
    </form>
  </div>
</div>
