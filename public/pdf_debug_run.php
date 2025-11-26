<?php
// public/parse_debug_from_html.php
// Uso: http://localhost/parse_debug_from_html.php?path=C:\path\to\converted.html
ini_set('display_errors',1);
error_reporting(E_ALL);

if (empty($_GET['path']) || !is_file($_GET['path'])) {
  echo "Uso: ?path=C:\\ruta\\al\\archivo.html"; exit;
}
$path = $_GET['path'];
$html = file_get_contents($path);

/**
 * Parser robusto: prioriza tablas con encabezado Fecha/Descripción/Cargos/Abonos/Saldo,
 * si no las encuentra usa el fallback por bloques en <b>...<b>.
 */
function parse_bank_html(string $html): array {
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
  $xpath = new DOMXPath($dom);

  $normalizeDate = function($d) {
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $d, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    $ts = strtotime($d); return $ts ? date('Y-m-d',$ts) : null;
  };
  $parseAmount = function($s) {
    if ($s === null) return null;
    $s = trim((string)$s);
    if ($s === '' || $s === '-') return null;
    $s2 = preg_replace('/[^\d\-\.,]/u','',$s);
    if (strpos($s2, ',') !== false && strpos($s2, '.') !== false) {
      $s2 = str_replace('.', '', $s2);
      $s2 = str_replace(',', '.', $s2);
    } else {
      $s2 = str_replace('.', '', $s2);
      $s2 = str_replace(',', '.', $s2);
    }
    if (!preg_match('/^-?\d+(\.\d+)?$/', $s2)) return null;
    return (float)$s2;
  };

  $rows = [];

  // 1) tablas con header
  $tables = $xpath->query('//table');
  foreach ($tables as $table) {
    $thNodes = $xpath->query('.//th', $table);
    $headers = [];
    if ($thNodes->length > 0) {
      foreach ($thNodes as $th) $headers[] = trim(preg_replace('/\s+/', ' ', $th->textContent));
    } else {
      // fallback: primera fila asume header si contiene textos como "Fecha","Descripción"
      $firstTds = $xpath->query('.//tr[1]//td', $table);
      if ($firstTds->length > 0) foreach ($firstTds as $td) $headers[] = trim(preg_replace('/\s+/', ' ', $td->textContent));
    }
    $hasFecha = false;
    foreach ($headers as $h) if (stripos($h,'Fecha') !== false) { $hasFecha = true; break; }
    if (!$hasFecha) continue;

    // map header index -> semantic
    $colMap = [];
    foreach ($headers as $i => $h) {
      $hl = mb_strtolower($h);
      if (mb_stripos($hl,'fecha') !== false) $colMap[$i] = 'fecha';
      elseif (mb_stripos($hl,'descrip') !== false || mb_stripos($hl,'detalle') !== false) $colMap[$i] = 'descripcion';
      elseif (mb_stripos($hl,'canal') !== false || mb_stripos($hl,'sucursal') !== false) $colMap[$i] = 'canal';
      elseif (mb_stripos($hl,'cargo') !== false) $colMap[$i] = 'cargo';
      elseif (mb_stripos($hl,'abono') !== false) $colMap[$i] = 'abono';
      elseif (mb_stripos($hl,'saldo') !== false) $colMap[$i] = 'saldo';
      else $colMap[$i] = 'unknown';
    }

    $trNodes = $xpath->query('.//tr', $table);
    foreach ($trNodes as $tr) {
      $tds = $xpath->query('.//td', $tr);
      if ($tds->length === 0) continue;
      $cells = [];
      foreach ($tds as $td) $cells[] = trim(preg_replace('/\s+/', ' ', $td->textContent));
      // skip rows with no date
      $hasDate = false;
      foreach ($cells as $c) if (preg_match('/\d{2}\/\d{2}\/\d{4}/',$c)) { $hasDate = true; break; }
      if (!$hasDate) continue;

      $mapped = ['fecha'=>null,'descripcion'=>null,'canal'=>null,'monto'=>null,'tipo'=>null,'saldo'=>null];
      foreach ($cells as $idx => $txt) {
        $key = $colMap[$idx] ?? 'unknown';
        if ($key === 'fecha') $mapped['fecha'] = $normalizeDate($txt);
        elseif ($key === 'descripcion') $mapped['descripcion'] = $txt;
        elseif ($key === 'canal') $mapped['canal'] = $txt;
        elseif ($key === 'cargo') {
          $v = $parseAmount($txt); if ($v !== null) { $mapped['monto'] = -1*abs($v); $mapped['tipo']='gasto'; }
        } elseif ($key === 'abono') {
          $v = $parseAmount($txt); if ($v !== null) { $mapped['monto'] = $v; $mapped['tipo']='ingreso'; }
        } elseif ($key === 'saldo') {
          $mapped['saldo'] = $parseAmount($txt);
        } else {
          // try infer
          if (empty($mapped['descripcion']) && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/',$txt) && !preg_match('/^\d+$/',$txt)) $mapped['descripcion'] = $txt;
          else {
            $maybe = $parseAmount($txt);
            if ($maybe !== null) {
              if ($mapped['saldo'] === null) $mapped['saldo'] = $maybe;
              elseif ($mapped['monto'] === null) { $mapped['monto'] = -1*abs($maybe); $mapped['tipo']='gasto'; }
            }
          }
        }
      }
      if (empty($mapped['fecha'])) continue;
      if ($mapped['monto'] === null) { $mapped['monto'] = 0.0; $mapped['tipo'] = $mapped['tipo'] ?? 'gasto'; }
      $rows[] = $mapped;
    }
    if (!empty($rows)) return $rows;
  }

  // 2) fallback by bold blocks (pdftohtml column blocks)
  $htmlNorm = preg_replace('/\r\n|\r/','\n',$html);
  if (preg_match('|<body.*?>(.*)</body>|is',$htmlNorm,$m)) $body = $m[1]; else $body = $htmlNorm;
  $body = preg_replace('/<\s*br\s*\/?>/i', "\n", $body);
  $body = preg_replace('/<\s*b[^>]*>(.*?)<\s*\/\s*b\s*>/is', "[H]\\1[H]", $body);
  $text = strip_tags($body);
  $text = preg_replace('/\s*

\[H\]

\s*/', "\n[H]", $text);
  $text = preg_replace('/\n{2,}/', "\n", $text);
  $lines = array_map('trim', array_values(array_filter(array_map('trim', explode("\n",$text)))));
  $sections = []; $current = null;
  foreach ($lines as $ln) {
    if (preg_match('/^

\[H\]

(.+?)

\[H\]

$/',$ln,$m)) { $current = trim($m[1]); if (!isset($sections[$current])) $sections[$current]=[]; continue; }
    if ($current !== null) { if ($ln === '' || preg_match('/Movimientos\s*al/i',$ln)) continue; $sections[$current][] = $ln; }
  }
  $map = [];
  foreach ($sections as $k=>$v) {
    $kl = mb_strtolower($k);
    if (mb_stripos($kl,'fecha')!==false) $map['fecha'] = $v;
    elseif (mb_stripos($kl,'descrip')!==false) $map['descripcion'] = $v;
    elseif (mb_stripos($kl,'cargo')!==false) $map['cargos'] = $v;
    elseif (mb_stripos($kl,'abono')!==false) $map['abonos'] = $v;
    elseif (mb_stripos($kl,'saldo')!==false) $map['saldos'] = $v;
    else $map[$k] = $v;
  }
  if (!empty($map['fecha']) && !empty($map['descripcion'])) {
    $fechas = array_values(array_filter($map['fecha'], function($l){ return preg_match('/\d{2}\/\d{2}\/\d{4}/',$l); }));
    $descs = array_values($map['descripcion']);
    $cargos = $map['cargos'] ?? [];
    $abonos = $map['abonos'] ?? [];
    $saldos = $map['saldos'] ?? [];
    $n = min(count($fechas), max(1,count($descs)));
    for ($i=0;$i<$n;$i++) {
      $fRaw = $fechas[$i] ?? '';
      if (!preg_match('/\d{2}\/\d{2}\/\d{4}/',$fRaw)) continue;
      $fecha = $normalizeDate($fRaw);
      $descripcion = $descs[$i] ?? '';
      $valAb = $parseAmount($abonos[$i] ?? null);
      $valCa = $parseAmount($cargos[$i] ?? null);
      $valSa = $parseAmount($saldos[$i] ?? null);
      if ($valAb !== null) { $monto = $valAb; $tipo = 'ingreso'; }
      elseif ($valCa !== null) { $monto = -1*abs($valCa); $tipo='gasto'; }
      else { $monto = 0.0; $tipo='gasto'; }
      $rows[] = ['fecha'=>$fecha,'descripcion'=>$descripcion,'canal'=>null,'monto'=>$monto,'tipo'=>$tipo,'saldo'=>$valSa];
    }
    if (!empty($rows)) return $rows;
  }

  // 3) line-level fallback
  $lines = preg_split('/\r\n|\r|\n/',$html);
  foreach ($lines as $line) {
    $line = trim($line);
    if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+(-?[\d\.\,]+)\s*$/',$line,$m)) {
      $fecha = $normalizeDate($m[1]);
      $descripcion = $m[2];
      $monto = $parseAmount($m[3]);
      $tipo = ($monto !== null && $monto>0) ? 'ingreso' : 'gasto';
      $rows[] = ['fecha'=>$fecha,'descripcion'=>$descripcion,'canal'=>null,'monto'=>$monto,'tipo'=>$tipo,'saldo'=>null];
    }
  }
  return $rows;
}

// Ejecutar parser y mostrar resultado
$rows = parse_bank_html($html);

echo "<h2>Parser result</h2>";
echo "<p>Rows parsed: " . count($rows) . "</p>";
echo "<pre>" . htmlspecialchars(json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . "</pre>";

echo "<h3>Sample converted HTML (first 3000 chars)</h3>";
echo "<pre>" . htmlspecialchars(mb_substr($html,0,3000)) . "</pre>";
