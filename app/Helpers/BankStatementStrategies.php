<?php
/**
 * Estrategia 1: Parsing de tablas HTML con encabezados
 */
class TableWithHeadersStrategy implements BankStatementParserStrategy {
    use ParserHelpers;
    
    public function getName(): string {
        return "Tabla HTML con encabezados";
    }
    
    public function canHandle(string $html): bool {
        return stripos($html, '<table') !== false && 
               (stripos($html, '<th') !== false || stripos($html, 'fecha') !== false);
    }
    
    public function parse(string $html): array {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $tables = $xpath->query('//table');
        
        foreach ($tables as $table) {
            $rows = $this->parseTable($table, $xpath);
            if (!empty($rows)) {
                return $rows;
            }
        }
        
        return [];
    }
    
    private function parseTable($table, DOMXPath $xpath): array {
        // Extraer headers
        $headers = $this->extractHeaders($table, $xpath);
        
        if (empty($headers) || !$this->hasRequiredColumns($headers)) {
            return [];
        }
        
        // Mapear columnas
        $colMap = $this->mapColumns($headers);
        
        // Extraer filas
        $rows = [];
        $trNodes = $xpath->query('.//tr', $table);
        
        foreach ($trNodes as $tr) {
            $row = $this->parseRow($tr, $xpath, $colMap);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        
        return $rows;
    }
    
    private function extractHeaders($table, DOMXPath $xpath): array {
        $headers = [];
        
        // Intentar con <th>
        $thNodes = $xpath->query('.//th', $table);
        if ($thNodes->length > 0) {
            foreach ($thNodes as $th) {
                $headers[] = $this->cleanText($th->textContent);
            }
            return $headers;
        }
        
        // Fallback: primera fila como headers
        $firstTds = $xpath->query('.//tr[1]//td', $table);
        if ($firstTds->length > 0) {
            foreach ($firstTds as $td) {
                $headers[] = $this->cleanText($td->textContent);
            }
        }
        
        return $headers;
    }
    
    private function hasRequiredColumns(array $headers): bool {
        $headerStr = implode(' ', array_map('mb_strtolower', $headers));
        return mb_stripos($headerStr, 'fecha') !== false;
    }
    
    private function mapColumns(array $headers): array {
        $map = [];
        
        foreach ($headers as $i => $h) {
            $hl = mb_strtolower($h);
            
            if (mb_stripos($hl, 'fecha') !== false) {
                $map[$i] = 'fecha';
            } elseif (mb_stripos($hl, 'descrip') !== false || mb_stripos($hl, 'detalle') !== false) {
                $map[$i] = 'descripcion';
            } elseif (mb_stripos($hl, 'canal') !== false || mb_stripos($hl, 'sucursal') !== false) {
                $map[$i] = 'canal';
            } elseif (mb_stripos($hl, 'cargo') !== false) {
                $map[$i] = 'cargo';
            } elseif (mb_stripos($hl, 'abono') !== false) {
                $map[$i] = 'abono';
            } elseif (mb_stripos($hl, 'saldo') !== false) {
                $map[$i] = 'saldo';
            } else {
                $map[$i] = 'unknown';
            }
        }
        
        return $map;
    }
    
    private function parseRow($tr, DOMXPath $xpath, array $colMap): ?array {
        $tds = $xpath->query('.//td', $tr);
        if ($tds->length === 0) {
            return null;
        }
        
        $cells = [];
        foreach ($tds as $td) {
            $cells[] = $this->cleanText($td->textContent);
        }
        
        // Verificar que tenga fecha
        $hasDate = false;
        foreach ($cells as $c) {
            if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $c)) {
                $hasDate = true;
                break;
            }
        }
        
        if (!$hasDate) {
            return null;
        }
        
        // Mapear datos
        $mapped = [
            'fecha' => null,
            'descripcion' => null,
            'canal' => null,
            'monto' => null,
            'tipo' => null,
            'saldo' => null
        ];
        
        foreach ($cells as $idx => $txt) {
            $key = $colMap[$idx] ?? 'unknown';
            
            switch ($key) {
                case 'fecha':
                    $mapped['fecha'] = $this->normalizeDate($txt);
                    break;
                case 'descripcion':
                    $mapped['descripcion'] = $txt;
                    break;
                case 'canal':
                    $mapped['canal'] = $txt;
                    break;
                case 'cargo':
                    $v = $this->parseAmount($txt);
                    if ($v !== null) {
                        $mapped['monto'] = -1 * abs($v);
                        $mapped['tipo'] = 'gasto';
                    }
                    break;
                case 'abono':
                    $v = $this->parseAmount($txt);
                    if ($v !== null) {
                        $mapped['monto'] = abs($v);
                        $mapped['tipo'] = 'ingreso';
                    }
                    break;
                case 'saldo':
                    $mapped['saldo'] = $this->parseAmount($txt);
                    break;
                case 'unknown':
                    // Intentar inferir
                    $this->inferField($txt, $mapped);
                    break;
            }
        }
        
        // Validar datos mínimos
        if (empty($mapped['fecha'])) {
            return null;
        }
        
        if ($mapped['monto'] === null) {
            $mapped['monto'] = 0.0;
            $mapped['tipo'] = 'gasto';
        }
        
        // Skip filas de totales
        if ($this->isSkippableRow($mapped['descripcion'] ?? '')) {
            return null;
        }
        
        return $mapped;
    }
    
    private function inferField(string $txt, array &$mapped): void {
        // Si está vacío descripcion y no es fecha ni número, asumir descripción
        if (empty($mapped['descripcion']) && 
            !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $txt) &&
            !preg_match('/^[\d\.\,\-\s]+$/', $txt)) {
            $mapped['descripcion'] = $txt;
            return;
        }
        
        // Intentar parsear como monto
        $maybe = $this->parseAmount($txt);
        if ($maybe !== null) {
            if ($mapped['saldo'] === null) {
                $mapped['saldo'] = $maybe;
            } elseif ($mapped['monto'] === null) {
                $mapped['monto'] = -1 * abs($maybe);
                $mapped['tipo'] = 'gasto';
            }
        }
    }
    
    private function cleanText(string $text): string {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}

/**
 * Estrategia 2: Parsing de bloques verticales (pdftohtml -stdout)
 */
class VerticalBlocksStrategy implements BankStatementParserStrategy {
    use ParserHelpers;
    
    public function getName(): string {
        return "Bloques verticales (pdftohtml)";
    }
    
    public function canHandle(string $html): bool {
        // Detectar si tiene tags <b> y estructura de bloques
        return stripos($html, '<b>') !== false && 
               stripos($html, 'fecha') !== false;
    }
    
    public function parse(string $html): array {
        // Normalizar HTML
        $html = preg_replace('/\r\n|\r/', "\n", $html);
        
        // Extraer body
        if (preg_match('|<body.*?>(.*)</body>|is', $html, $m)) {
            $body = $m[1];
        } else {
            $body = $html;
        }
        
        // Convertir <br> a newlines
        $body = preg_replace('/<\s*br\s*\/?>/i', "\n", $body);
        
        // Marcar headers con delimitadores especiales
        $body = preg_replace('/<\s*b[^>]*>(.*?)<\s*\/\s*b\s*>/is', "[H]\\1[H]", $body);
        
        // Strip tags
        $text = strip_tags($body);
        
        // Normalizar headers
        $text = preg_replace('/\s*\[H\]\s*/', "\n[H]", $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);
        
        // Extraer líneas
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        
        // Agrupar por secciones
        $sections = $this->groupByHeaders($lines);
        
        // Parsear secciones
        return $this->parseSections($sections);
    }
    
    private function groupByHeaders(array $lines): array {
        $sections = [];
        $current = null;
        
        foreach ($lines as $line) {
            if (preg_match('/^\[H\](.+?)\[H\]$/', $line, $m)) {
                $current = trim($m[1]);
                if (!isset($sections[$current])) {
                    $sections[$current] = [];
                }
                continue;
            }
            
            if ($current !== null) {
                // Skip líneas de metadata
                if ($line === '' || preg_match('/Movimientos\s*al/i', $line)) {
                    continue;
                }
                $sections[$current][] = $line;
            }
        }
        
        return $sections;
    }
    
    private function parseSections(array $sections): array {
        // Mapear secciones a campos conocidos
        $map = [];
        
        foreach ($sections as $k => $v) {
            $kl = mb_strtolower($k);
            
            if (mb_stripos($kl, 'fecha') !== false) {
                $map['fecha'] = $v;
            } elseif (mb_stripos($kl, 'descrip') !== false) {
                $map['descripcion'] = $v;
            } elseif (mb_stripos($kl, 'cargo') !== false) {
                $map['cargos'] = $v;
            } elseif (mb_stripos($kl, 'abono') !== false) {
                $map['abonos'] = $v;
            } elseif (mb_stripos($kl, 'saldo') !== false) {
                $map['saldos'] = $v;
            }
        }
        
        // Validar campos mínimos
        if (empty($map['fecha']) || empty($map['descripcion'])) {
            return [];
        }
        
        // Filtrar fechas válidas
        $fechas = array_values(array_filter($map['fecha'], function($l) {
            return preg_match('/\d{2}\/\d{2}\/\d{4}/', $l);
        }));
        
        $descs = array_values($map['descripcion']);
        $cargos = $map['cargos'] ?? [];
        $abonos = $map['abonos'] ?? [];
        $saldos = $map['saldos'] ?? [];
        
        // Construir filas
        $rows = [];
        $n = min(count($fechas), max(1, count($descs)));
        
        for ($i = 0; $i < $n; $i++) {
            $fRaw = $fechas[$i] ?? '';
            if (!preg_match('/\d{2}\/\d{2}\/\d{4}/', $fRaw)) {
                continue;
            }
            
            $fecha = $this->normalizeDate($fRaw);
            $descripcion = $descs[$i] ?? '';
            
            $valAb = $this->parseAmount($abonos[$i] ?? '');
            $valCa = $this->parseAmount($cargos[$i] ?? '');
            $valSa = $this->parseAmount($saldos[$i] ?? '');
            
            if ($valAb !== null) {
                $monto = abs($valAb);
                $tipo = 'ingreso';
            } elseif ($valCa !== null) {
                $monto = -1 * abs($valCa);
                $tipo = 'gasto';
            } else {
                $monto = 0.0;
                $tipo = 'gasto';
            }
            
            // Skip totales
            if ($this->isSkippableRow($descripcion)) {
                continue;
            }
            
            $rows[] = [
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'canal' => null,
                'monto' => $monto,
                'tipo' => $tipo,
                'saldo' => $valSa
            ];
        }
        
        return $rows;
    }
}

/**
 * Estrategia 3: Parsing de texto plano línea por línea
 */
class PlainTextStrategy implements BankStatementParserStrategy {
    use ParserHelpers;
    
    public function getName(): string {
        return "Texto plano línea por línea";
    }
    
    public function canHandle(string $html): bool {
        // Siempre puede intentar como fallback
        return true;
    }
    
    public function parse(string $html): array {
        $text = strip_tags($html);
        $lines = preg_split('/\r\n|\r|\n/', $text);
        
        $rows = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Patrón: FECHA DESCRIPCION MONTO [MONTO] [MONTO]
            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([\d\.\,\-]+(?:\s+[\d\.\,\-]+){0,2})\s*$/', $line, $m)) {
                $fecha = $this->normalizeDate($m[1]);
                $descripcion = trim($m[2]);
                $amounts = preg_split('/\s+/', trim($m[3]));
                
                // Interpretar montos: último es saldo, anteriores son cargo/abono
                $saldo = $this->parseAmount(array_pop($amounts));
                $abono = null;
                $cargo = null;
                
                if (count($amounts) >= 2) {
                    $cargo = $this->parseAmount($amounts[0]);
                    $abono = $this->parseAmount($amounts[1]);
                } elseif (count($amounts) === 1) {
                    // Asumir que es cargo si es negativo o si descripción sugiere gasto
                    $val = $this->parseAmount($amounts[0]);
                    if ($val !== null) {
                        if ($val < 0 || stripos($descripcion, 'compra') !== false || stripos($descripcion, 'pago') !== false) {
                            $cargo = abs($val);
                        } else {
                            $abono = abs($val);
                        }
                    }
                }
                
                if ($abono !== null) {
                    $monto = abs($abono);
                    $tipo = 'ingreso';
                } elseif ($cargo !== null) {
                    $monto = -1 * abs($cargo);
                    $tipo = 'gasto';
                } else {
                    continue;
                }
                
                if ($this->isSkippableRow($descripcion)) {
                    continue;
                }
                
                $rows[] = [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'canal' => null,
                    'monto' => $monto,
                    'tipo' => $tipo,
                    'saldo' => $saldo
                ];
            }
        }
        
        return $rows;
    }
}