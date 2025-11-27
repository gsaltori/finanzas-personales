<?php
// views/layouts/main.php
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($title ?? 'App') ?></title>
  <style>
    body{font-family: Arial, Helvetica, sans-serif; margin:40px;}
    .container{max-width:900px;margin:0 auto;}
    .error{color:#b00;}
  </style>
</head>
<body>
  <div class="container">
    <?php include $viewFile; ?>
  </div>
</body>
</html>
