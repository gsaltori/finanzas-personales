<?php
// app/Views/errors/500.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Error del servidor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1 class="error-code">500</h1>
        <h2 class="mb-3">Error del servidor</h2>
        <p class="text-muted mb-4">
            Ha ocurrido un error inesperado. Por favor intenta nuevamente más tarde.
        </p>
        <a href="<?= base_url('?r=dashboard/index') ?>" class="btn btn-danger">
            Volver al inicio
        </a>
    </div>
</body>
</html>