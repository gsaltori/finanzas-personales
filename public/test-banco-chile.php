<?php
/**
 * Test final del parser con el HTML real del Banco de Chile
 * Guardar como: public/test-final-banco-chile.php
 * Acceder: http://localhost/finanzas-personales/public/test-final-banco-chile.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Helpers/BankStatementParser.php';
require_once __DIR__ . '/../app/Helpers/BankStatementStrategies.php';
require_once __DIR__ . '/../app/Helpers/BancoChileStrategy.php';

$htmlContent = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['html'])) {
    $htmlContent = $_POST['html'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name'])) {
    $htmlContent = file_get_contents($_FILES['file']['tmp_name']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Test Final - Banco de Chile Parser</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 1200px; }
        .form-group { margin: 20px 0; }
        textarea { width: 100%; min-height: 200px; font-family: monospace; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
        .ingreso { color: green; font-weight: bold; }
        .gasto { color: red; font-weight: bold; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #f0f0f0; padding: 15px; border-radius: 5px; flex: 1; }
    </style>
</head>
<body>
    <h1>🏦 Test Final - Parser Banco de Chile</h1>
    
    <form method="post">
        <div class="form-group">
            <label><strong>Pegar HTML (de pdftohtml -stdout):</strong></label>
            <textarea name="html" placeholder="Pega aquí el contenido del archivo cartola.html"></textarea>
        </div>
        <button type="submit" class="btn">Analizar</button>
    </form>
    
    <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
        <div class="form-group">
            <label><strong>O subir archivo HTML:</strong></label>
            <input type="file" name="file" accept=".html,.htm">
            <button type="submit" class="btn">Subir y Analizar</button>
        </div>
    </form>

<?php
if (!empty($htmlContent)) {
    echo "<hr>";
    echo "<h2>📊 Resultados</h2>";
    
    $parser = new BankStatementParser();
    $transactions = $parser->parse($htmlContent);
    $diagnostics = $parser->getDiagnostics();
    
    echo "<h3>Diagnósticos:</h3>";
    echo "<ul>";
    foreach ($diagnostics as $diag) {
        echo "<li>" . htmlspecialchars($diag) . "</li>";
    }
    echo "</ul>";
    
    if (empty($transactions)) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px;'>";
        echo "<strong>❌ No se pudieron extraer transacciones</strong>";
        echo "<p>El parser no pudo procesar el contenido.</p>";
        echo "</div>";
        
        // Debug: mostrar estructura
        echo "<h3>Debug - Primeros 3000 caracteres del HTML:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px;'>";
        echo htmlspecialchars(substr($htmlContent, 0, 3000));
        echo "</pre>";
    } else {
        // Calcular estadísticas
        $totalIngresos = 0;
        $totalGastos = 0;
        $countIngresos = 0;
        $countGastos = 0;
        
        foreach ($transactions as $t) {
            if ($t['tipo'] === 'ingreso') {
                $totalIngresos += abs($t['monto']);
                $countIngresos++;
            } else {
                $totalGastos += abs($t['monto']);
                $countGastos++;
            }
        }
        
        $balance = $totalIngresos - $totalGastos;
        
        echo "<div class='stats'>";
        echo "<div class='stat-card'>";
        echo "<h4 style='margin:0;color:green'>Total Ingresos</h4>";
        echo "<div style='font-size:24px;font-weight:bold'>$" . number_format($totalIngresos, 0, ',', '.') . "</div>";
        echo "<div>{$countIngresos} transacciones</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h4 style='margin:0;color:red'>Total Gastos</h4>";
        echo "<div style='font-size:24px;font-weight:bold'>$" . number_format($totalGastos, 0, ',', '.') . "</div>";
        echo "<div>{$countGastos} transacciones</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<h4 style='margin:0'>Balance</h4>";
        $balanceColor = $balance >= 0 ? 'green' : 'red';
        echo "<div style='font-size:24px;font-weight:bold;color:{$balanceColor}'>$" . number_format($balance, 0, ',', '.') . "</div>";
        echo "<div>" . count($transactions) . " movimientos</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<h3>📋 Transacciones Detectadas (" . count($transactions) . ")</h3>";
        echo "<table>";
        echo "<thead><tr>";
        echo "<th style='width:40px'>#</th>";
        echo "<th>Fecha</th>";
        echo "<th>Descripción</th>";
        echo "<th>Canal</th>";
        echo "<th>Tipo</th>";
        echo "<th style='text-align:right'>Monto</th>";
        echo "<th style='text-align:right'>Saldo</th>";
        echo "</tr></thead>";
        echo "<tbody>";
        
        foreach ($transactions as $idx => $t) {
            $tipoClass = $t['tipo'] === 'ingreso' ? 'ingreso' : 'gasto';
            echo "<tr>";
            echo "<td>" . ($idx + 1) . "</td>";
            echo "<td>{$t['fecha']}</td>";
            echo "<td>" . htmlspecialchars($t['descripcion']) . "</td>";
            echo "<td>" . htmlspecialchars($t['canal'] ?? '-') . "</td>";
            echo "<td class='{$tipoClass}'>" . ucfirst($t['tipo']) . "</td>";
            echo "<td class='{$tipoClass}' style='text-align:right'>$" . number_format(abs($t['monto']), 0, ',', '.') . "</td>";
            $saldoStr = isset($t['saldo']) ? '$' . number_format($t['saldo'], 0, ',', '.') : '-';
            echo "<td style='text-align:right'>{$saldoStr}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        
        // Botón para ver JSON
        echo "<div style='margin-top: 20px;'>";
        echo "<details>";
        echo "<summary style='cursor:pointer;background:#007bff;color:white;padding:10px;border-radius:5px;display:inline-block'>Ver datos en JSON (click para expandir)</summary>";
        echo "<pre style='background:#f5f5f5;padding:15px;margin-top:10px;overflow:auto;max-height:500px'>";
        echo htmlspecialchars(json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
        echo "</details>";
        echo "</div>";
        
        // Resumen para importar
        echo "<div style='background:#e8f5e9;padding:15px;border-radius:5px;margin-top:20px'>";
        echo "<h3 style='margin-top:0'>✅ Parser funcionando correctamente</h3>";
        echo "<p><strong>Próximo paso:</strong> Ve a la sección de Import en el sistema principal:</p>";
        echo "<p><a href='?r=import/index' style='background:#4caf50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block'>Ir a Importar →</a></p>";
        echo "</div>";
    }
}
?>

    <hr>
    <h3>📝 Instrucciones</h3>
    <ol>
        <li>Convertir PDF: <code>pdftohtml -stdout cartola.pdf &gt; cartola.html</code></li>
        <li>Abrir <code>cartola.html</code> con un editor de texto</li>
        <li>Copiar TODO el contenido (Ctrl+A, Ctrl+C)</li>
        <li>Pegar en el textarea arriba</li>
        <li>Click en "Analizar"</li>
    </ol>
    
    <p><strong>Formato esperado del HTML:</strong></p>
    <pre style="background:#f5f5f5;padding:10px;border-radius:5px">&lt;b&gt;Fecha&lt;/b&gt;&lt;br/&gt;
06/11/2025&lt;br/&gt;
05/11/2025&lt;br/&gt;
...
&lt;b&gt;Descripción&lt;/b&gt;&lt;br/&gt;
Pago:copec App&lt;br/&gt;
Pago:de Sueldos 0970040005&lt;br/&gt;
...
&lt;b&gt;Cargos (CLP)&lt;/b&gt;51.205&lt;br/&gt;
1.970&lt;br/&gt;
...
&lt;b&gt;Abonos (CLP)&lt;/b&gt;781.264&lt;br/&gt;
...</pre>
</body>
</html>