<?php
// public/debug_parser.php
// Script para debuggear el parser del extracto bancario

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Leer el archivo HTML
$htmlFile = __DIR__ . '/cartola.html';
if (!file_exists($htmlFile)) {
    die("No se encuentra cartola.html en public/");
}

$html = file_get_contents($htmlFile);

echo "<h2>Debug Parser - Extracto Bancario</h2>";
echo "<h3>1. Contenido HTML (primeros 2000 caracteres)</h3>";
echo "<pre>" . htmlspecialchars(substr($html, 0, 2000)) . "</pre>";

// Extraer texto plano
$body = $html;
if (preg_match('|<body.*?>(.*)</body>|is', $html, $mBody)) {
    $body = $mBody[1];
}

$body = preg_replace('/<\s*br\s*\/?>/i', "\n", $body);
$text = strip_tags($body);

$lines = array_map('trim', explode("\n", $text));
$lines = array_filter($lines, function($line) {
    return $line !== '';
});
$lines = array_values($lines);

echo "<h3>2. Total de líneas extraídas: " . count($lines) . "</h3>";

echo "<h3>3. Primeras 50 líneas procesadas</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>#</th><th>Contenido</th><th>Tipo</th></tr>";

$parseAmount = function($s) {
    if ($s === null) return null;
    $s = trim((string)$s);
    if ($s === '' || $s === '-') return null;
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return null;
    return (float)$s;
};

for ($i = 0; $i < min(50, count($lines)); $i++) {
    $line = $lines[$i];
    $tipo = 'texto';
    
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $line)) {
        $tipo = '<strong style="color:red">FECHA</strong>';
    } elseif ($parseAmount($line) !== null) {
        $tipo = '<strong style="color:blue">NÚMERO: ' . $parseAmount($line) . '</strong>';
    }
    
    echo "<tr>";
    echo "<td>$i</td>";
    echo "<td>" . htmlspecialchars($line) . "</td>";
    echo "<td>$tipo</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>4. Fechas encontradas en todo el documento</h3>";
$fechas = [];
foreach ($lines as $i => $line) {
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $line)) {
        $fechas[] = ['index' => $i, 'fecha' => $line];
    }
}

echo "<p>Total fechas encontradas: <strong>" . count($fechas) . "</strong></p>";
echo "<ul>";
foreach (array_slice($fechas, 0, 20) as $f) {
    echo "<li>Línea {$f['index']}: {$f['fecha']}</li>";
}
echo "</ul>";

echo "<h3>5. Simulación del parser</h3>";

function testParser($html) {
    $parseAmount = function($s) {
        if ($s === null) return null;
        $s = trim((string)$s);
        if ($s === '' || $s === '-') return null;
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return null;
        return (float)$s;
    };
    
    $normalizeDate = function($d) {
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $d, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return $d;
    };

    $body = $html;
    if (preg_match('|<body.*?>(.*)</body>|is', $html, $mBody)) {
        $body = $mBody[1];
    }
    
    $body = preg_replace('/<\s*br\s*\/?>/i', "\n", $body);
    $text = strip_tags($body);
    
    $lines = array_map('trim', explode("\n", $text));
    $lines = array_filter($lines, function($line) {
        return $line !== '';
    });
    $lines = array_values($lines);
    
    $i = 0;
    $n = count($lines);
    $rows = [];
    
    $isHeaderOrFooter = function($line) {
        return preg_match('/(Infórmate|Informate|©|Banco de|Todos los Derechos|www\.sbif|Saldo Disponible|Saldo Contable|Retenciones|Total Cargos|Total Abonos|Línea de Crédito|Linea de Credito|Sr\(a\)|Rut:|Cuenta N|Moneda :|Saldo al|Movimientos\s*al|^Fecha$|^Descripción|^Canal|^Cargos|^Abonos|^Saldo|Canal o\s+Sucursal|Cargos \(CLP\)|Abonos \(CLP\)|Saldo \(CLP\))/i', $line);
    };
    
    while ($i < $n) {
        $line = $lines[$i];
        
        if ($isHeaderOrFooter($line)) {
            $i++;
            continue;
        }
        
        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})$/', $line, $match)) {
            $fecha = $normalizeDate($match[1]);
            $i++;
            
            while ($i < $n) {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $lines[$i])) {
                    break;
                }
                
                if ($isHeaderOrFooter($lines[$i])) {
                    $i++;
                    continue;
                }
                
                $descripcion = $lines[$i];
                
                if (strlen($descripcion) < 3) {
                    $i++;
                    continue;
                }
                
                $i++;
                
                $canal = null;
                $cargo = null;
                $abono = null;
                $saldo = null;
                $numbersFound = 0;
                $maxLookAhead = 5;
                $linesChecked = 0;
                
                while ($i < $n && $linesChecked < $maxLookAhead) {
                    $nextLine = $lines[$i];
                    
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $nextLine)) {
                        break;
                    }
                    
                    if ($isHeaderOrFooter($nextLine)) {
                        $i++;
                        continue;
                    }
                    
                    $maybeAmount = $parseAmount($nextLine);
                    if ($maybeAmount === null && strlen($nextLine) > 15 && $numbersFound > 0) {
                        break;
                    }
                    
                    if ($maybeAmount !== null) {
                        $numbersFound++;
                        if ($numbersFound == 1) {
                            $cargo = $maybeAmount;
                        } elseif ($numbersFound == 2) {
                            $abono = $maybeAmount;
                        } elseif ($numbersFound == 3) {
                            $saldo = $maybeAmount;
                            $i++;
                            break;
                        } else {
                            break;
                        }
                    } else {
                        if ($canal === null && strlen($nextLine) < 30 && strlen($nextLine) > 2) {
                            if (preg_match('/(Huerfanos|Internet|Oficina|Of\.|Quilin|Paseo|Ser\.)/i', $nextLine)) {
                                $canal = $nextLine;
                            } else {
                                $descripcion .= ' ' . $nextLine;
                            }
                        } else {
                            if (strlen($nextLine) < 50 && $numbersFound == 0) {
                                $descripcion .= ' ' . $nextLine;
                            }
                        }
                    }
                    
                    $i++;
                    $linesChecked++;
                }
                
                $descripcion = trim($descripcion);
                $descripcion = preg_replace('/\s+/', ' ', $descripcion);
                
                if (empty($descripcion) || strlen($descripcion) < 3) {
                    continue;
                }
                
                if ($numbersFound == 0) {
                    continue;
                }
                
                $monto = 0.0;
                $tipo = 'gasto';
                
                if ($numbersFound == 1) {
                    if ($cargo !== null && $cargo != 0) {
                        $monto = -1 * abs($cargo);
                        $tipo = 'gasto';
                    } else {
                        continue;
                    }
                } elseif ($numbersFound >= 2) {
                    if ($abono !== null && $abono > 0 && ($cargo === null || $cargo == 0)) {
                        $monto = $abono;
                        $tipo = 'ingreso';
                    } elseif ($cargo !== null && $cargo > 0) {
                        $monto = -1 * abs($cargo);
                        $tipo = 'gasto';
                    } else {
                        continue;
                    }
                }
                
                $rows[] = [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'canal' => $canal,
                    'monto' => $monto,
                    'tipo' => $tipo,
                    'saldo' => $saldo,
                    'cargo_raw' => $cargo,
                    'abono_raw' => $abono
                ];
            }
            
            continue;
        }
        
        $i++;
    }
    
    return $rows;
}

$resultado = testParser($html);

echo "<p><strong>Total transacciones parseadas: " . count($resultado) . "</strong></p>";

if (count($resultado) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Fecha</th><th>Descripción</th><th>Canal</th><th>Cargo</th><th>Abono</th><th>Monto Final</th><th>Tipo</th><th>Saldo</th></tr>";
    
    foreach (array_slice($resultado, 0, 20) as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['fecha']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['descripcion'], 0, 50)) . "</td>";
        echo "<td>" . htmlspecialchars($row['canal'] ?? '-') . "</td>";
        echo "<td>" . number_format($row['cargo_raw'] ?? 0, 2) . "</td>";
        echo "<td>" . number_format($row['abono_raw'] ?? 0, 2) . "</td>";
        echo "<td><strong>" . number_format($row['monto'], 2) . "</strong></td>";
        echo "<td>" . $row['tipo'] . "</td>";
        echo "<td>" . number_format($row['saldo'] ?? 0, 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red;font-weight:bold;'>⚠️ NO SE ENCONTRARON TRANSACCIONES</p>";
    echo "<p>Esto puede deberse a:</p>";
    echo "<ul>";
    echo "<li>El formato del HTML no coincide con lo esperado</li>";
    echo "<li>Las fechas no están en formato DD/MM/YYYY</li>";
    echo "<li>Los filtros están eliminando todas las transacciones</li>";
    echo "</ul>";
}
?>