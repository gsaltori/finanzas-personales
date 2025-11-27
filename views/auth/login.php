<?php
// views/auth/login.php
?>
<h1><?= htmlspecialchars($title ?? 'Login') ?></h1>

<?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/login">
  <div>
    <label>Email</label><br/>
    <input type="email" name="email" required />
  </div>
  <div>
    <label>Contraseña</label><br/>
    <input type="password" name="password" required />
  </div>
  <div style="margin-top:12px;">
    <button type="submit">Entrar</button>
  </div>
</form>
