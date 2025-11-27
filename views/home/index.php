<?php
// views/home/index.php
?>
<h1><?= htmlspecialchars($title ?? 'Inicio') ?></h1>
<p>Esta es la página pública de ejemplo.</p>

<?php if (!empty($_SESSION['user_id'])): ?>
  <p>Usuario logueado: ID <?= (int)$_SESSION['user_id'] ?> — <a href="/logout">Salir</a></p>
<?php else: ?>
  <p><a href="/login">Iniciar sesión</a></p>
<?php endif; ?>
