<!doctype html>
<html lang="es" data-theme="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Finanzas Personales</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell container-fluid py-4">
  <aside class="app-sidebar">
    <div class="sidebar-brand">
      <i class="fa-solid fa-piggy-bank fa-lg"></i>
      <span>Finanzas</span>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="mb-3 text-white-50 small">Hola, <?= $this->e($_SESSION['user_name'] ?? '') ?></div>
    <ul class="nav-links">
      <li><a href="<?= base_url('?r=dashboard/index') ?>" class="<?= ($_GET['r'] ?? '') === 'dashboard/index' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
      <li><a href="<?= base_url('?r=transaction/index') ?>"><i class="fa-solid fa-exchange-alt"></i> Transacciones</a></li>
      <li><a href="<?= base_url('?r=budget/index') ?>"><i class="fa-solid fa-wallet"></i> Presupuestos</a></li>
      <li><a href="<?= base_url('?r=goal/index') ?>"><i class="fa-solid fa-bullseye"></i> Metas</a></li>
      <li><a href="<?= base_url('?r=category/index') ?>"><i class="fa-solid fa-tags"></i> Categorías</a></li>
      <li><a href="<?= base_url('?r=auth/logout') ?>"><i class="fa-solid fa-sign-out-alt"></i> Salir</a></li>
    </ul>

    <div class="mt-4">
      <button id="themeToggle" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-moon"></i> Modo</button>
    </div>
    <?php else: ?>
      <div class="mt-3">
        <a href="<?= base_url('?r=auth/login') ?>" class="btn btn-light btn-sm w-100 mb-2">Ingresar</a>
        <a href="<?= base_url('?r=auth/register') ?>" class="btn btn-outline-light btn-sm w-100">Crear cuenta</a>
      </div>
    <?php endif; ?>
  </aside>

  <main class="app-main">
    <?php
      $flash = $flash ?? ($_SESSION['flash'] ?? null);
      if ($flash) {
        $cls = $flash['type'] === 'success' ? 'alert-success' : ($flash['type']==='danger'?'alert-danger':'alert-info');
        echo "<div class='alert {$cls} fade-in' role='alert'>" . htmlspecialchars($flash['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>";
        unset($_SESSION['flash']);
      }
    ?>
