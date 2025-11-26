<?php
// app/Views/errors/404.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #667eea;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1 class="error-code">404</h1>
        <h2 class="mb-3">Página no encontrada</h2>
        <p class="text-muted mb-4">
            La página que buscas no existe o ha sido movida.
        </p>
        <a href="<?= base_url('?r=dashboard/index') ?>" class="btn btn-primary">
            Volver al inicio
        </a>
    </div>
</body>
</html>