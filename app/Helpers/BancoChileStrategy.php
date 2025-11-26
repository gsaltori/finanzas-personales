<?php
/**
 * Estrategia específica para extractos del Banco de Chile
 * Optimizada para output de pdftohtml -stdout
 */
class BancoChileStrategy implements BankStatementParserStrategy {
    use ParserHelpers;
    
    public function getName(): string {
        return "Banco de Chile - pdftohtml stdout";
    }
    
    public function canHandle(string $html): bool {
        $text = strip_tags($html);
        
        // Detectar indicadores específicos del Banco de Chile
        $hasMovimientos = stripos($text, 'Movimientos') !== false && 
                         preg_match('/\d{2}\/\d{2}\/\d{4}/', $text);
        $hasBanco = stripos($text, 'Banco de Chile') !== false || 
                    stripos($text, 'www.sbif.cl') !== false;
        $hasColumns = stripos($text, 'Cargos') !== false && 
                      stripos($text, 'Abonos') !== false;
        
        return ($hasMovimientos && $hasColumns) || 
               ($hasBanco && preg_match('/\d{2}\/\d{2}\/\d{4}/', $text));
    }
    
    public function parse(string $html): array {
        // Extraer secciones por columnas usando tags <b>
        $sections = $this->extractColumnSections($html);
        
        if (empty($sections['fechas'])) {
            return [];
        }
        
        // Reconstruir transacciones desde las columnas
        return $this->reconstructTransactions($sections);
    }
    
    private function extractColumnSections(string $html): array {
        // El HTML viene con bloques <b>Nombre Columna</b> seguidos de los valores
        $text = $html;
        
        // Extraer bloques de texto
        $sections = [
            'fechas' => [],
            'descripciones' => [],
            'canales' => [],
            'cargos' => [],
            'abonos' => [],
            'saldos' => []
        ];
        
        // Buscar la sección de Fecha
        if (preg_match('/<b>Fecha<\/b>(.*?)(?=<b>|<hr|$)/s', $text, $match)) {
            $fechasText = strip_tags($match[1]);
            $lines = array_filter(array_map('trim', explode("\n", $fechasText)));
            foreach ($lines as $line) {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $line)) {
                    $sections['fechas'][] = $line;
                }
            }
        }
        
        // Buscar la sección de Descripción
        if (preg_match('/<b>Descripci[^<]*<\/b>(.*?)(?=<b>(?:Cargos|Canal)|<hr|$)/s', $text, $match)) {
            $descText = strip_tags($match[1]);
            $sections['descripciones'] = $this->extractDescriptions($descText);
        }
        
        // Buscar la sección de Canal
        if (preg_match('/<b>Canal o<\/b>.*?<b>Sucursal<\/b>(.*?)(?=<b>|<hr|$)/s', $text, $match)) {
            $canalText = strip_tags($match[1]);
            $lines = array_filter(array_map('trim', explode("\n", $canalText)));
            foreach ($lines as $line) {
                if (!empty($line) && !preg_match('/^\d+$/', $line)) {
                    $sections['canales'][] = $line;
                }
            }
        }
        
        // Buscar Cargos
        if (preg_match('/<b>Cargos[^<]*<\/b>(.*?)(?=<b>Abonos|<hr|$)/s', $text, $match)) {
            $cargosText = strip_tags($match[1]);
            $sections['cargos'] = $this->extractAmounts($cargosText);
        }
        
        // Buscar Abonos
        if (preg_match('/<b>Abonos[^<]*<\/b>(.*?)(?=<b>Saldo|<hr|$)/s', $text, $match)) {
            $abonosText = strip_tags($match[1]);
            $sections['abonos'] = $this->extractAmounts($abonosText);
        }
        
        // Buscar Saldos
        if (preg_match('/<b>Saldo[^<]*<\/b>(.*?)(?=<b>Canal|<hr|$)/s', $text, $match)) {
            $saldoText = strip_tags($match[1]);
            $sections['saldos'] = $this->extractAmounts($saldoText);
        }
        
        return $sections;
    }
    
    private function extractDescriptions(string $text): array {
        $descriptions = [];
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        
        // Canales conocidos que no son parte de la descripción
        $canales = ['Huerfanos', 'Oficina Central', 'Internet', 'Of.mall', 'Quilin', 
                    'Ser.clt', 'App', 'Paseo'];
        
        $current = '';
        foreach ($lines as $line) {
            // Si la línea es una fecha, no es descripción
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $line)) {
                continue;
            }
            
            // Si es un canal, skip
            $isCanal = false;
            foreach ($canales as $c) {
                if (stripos($line, $c) !== false) {
                    $isCanal = true;
                    break;
                }
            }
            if ($isCanal) continue;
            
            // Keywords que indican inicio de nueva transacción
            $keywords = ['Pago:', 'Traspaso', 'Giro', 'Cheque', 'Transferencia', 
                        'Intereses', 'Impuesto', 'Pac ', 'Cargo ', 'Comision', 'Protesto'];
            
            $isNewDesc = false;
            foreach ($keywords as $kw) {
                if (stripos($line, $kw) === 0) {
                    $isNewDesc = true;
                    break;
                }
            }
            
            if ($isNewDesc) {
                if (!empty($current)) {
                    $descriptions[] = trim($current);
                }
                $current = $line;
            } else {
                // Es continuación de la descripción anterior
                if (!empty($line)) {
                    $current .= ' ' . $line;
                }
            }
        }
        
        // Agregar la última
        if (!empty($current)) {
            $descriptions[] = trim($current);
        }
        
        return $descriptions;
    }
    
    private function extractAmounts(string $text): array {
        $amounts = [];
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        
        foreach ($lines as $line) {
            // Solo líneas que parecen montos
            if (preg_match('/^-?[\d\.]+$/', $line)) {
                $parsed = $this->parseAmount($line);
                if ($parsed !== null) {
                    $amounts[] = $parsed;
                }
            }
        }
        
        return $amounts;
    }
    
    private function reconstructTransactions(array $sections): array {
        $transactions = [];
        
        $fechas = $sections['fechas'];
        $descripciones = $sections['descripciones'];
        $canales = $sections['canales'];
        $cargos = $sections['cargos'];
        $abonos = $sections['abonos'];
        $saldos = $sections['saldos'];
        
        // El número de transacciones es el mínimo entre fechas y descripciones
        $numTransactions = min(count($fechas), count($descripciones));
        
        if ($numTransactions === 0) {
            return [];
        }
        
        // Índices para recorrer cada array
        $cargoIdx = 0;
        $abonoIdx = 0;
        $saldoIdx = 0;
        $canalIdx = 0;
        
        for ($i = 0; $i < $numTransactions; $i++) {
            $fecha = $this->normalizeDate($fechas[$i]);
            if ($fecha === null) continue;
            
            $descripcion = $descripciones[$i] ?? '';
            if (empty($descripcion)) continue;
            
            // Skip filas no deseadas
            if ($this->isSkippableRow($descripcion)) {
                continue;
            }
            
            // Determinar si es cargo o abono según la descripción
            $esAbono = $this->isAbono($descripcion);
            
            $monto = 0.0;
            $tipo = 'gasto';
            
            if ($esAbono) {
                // Es un abono (ingreso)
                if ($abonoIdx < count($abonos)) {
                    $monto = abs($abonos[$abonoIdx]);
                    $abonoIdx++;
                }
                $tipo = 'ingreso';
            } else {
                // Es un cargo (gasto)
                if ($cargoIdx < count($cargos)) {
                    $monto = -1 * abs($cargos[$cargoIdx]);
                    $cargoIdx++;
                }
                $tipo = 'gasto';
            }
            
            // Obtener saldo si existe
            $saldo = null;
            if ($saldoIdx < count($saldos)) {
                $saldo = $saldos[$saldoIdx];
                $saldoIdx++;
            }
            
            // Obtener canal si existe
            $canal = null;
            if ($canalIdx < count($canales)) {
                $canal = $canales[$canalIdx];
                // El canal se repite, así que avanzamos solo si es razonable
                if ($i > 0 && $i % 3 == 0) {
                    $canalIdx++;
                }
            }
            
            $transactions[] = [
                'fecha' => $fecha,
                'descripcion' => $this->cleanDescription($descripcion),
                'canal' => $canal,
                'monto' => $monto,
                'tipo' => $tipo,
                'saldo' => $saldo
            ];
        }
        
        return $transactions;
    }
    
    private function isAbono(string $descripcion): bool {
        $descLower = mb_strtolower($descripcion, 'UTF-8');
        
        $abonoKeywords = [
            'pago de sueldos',
            'pago:de sueldos',
            'traspaso de:',
            'cheque depositado',
            'deposito',
            'abono',
            'transferencia desde linea de credito'
        ];
        
        foreach ($abonoKeywords as $kw) {
            if (stripos($descLower, $kw) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function cleanDescription(string $desc): string {
        $desc = trim($desc);
        
        // Remover múltiples espacios
        $desc = preg_replace('/\s+/', ' ', $desc);
        
        // Capitalizar correctamente
        $desc = mb_convert_case($desc, MB_CASE_TITLE, 'UTF-8');
        
        return $desc;
    }
    
    protected function parseAmount(string $s): ?float {
        $s = trim($s);
        if ($s === '' || $s === '-') {
            return null;
        }
        
        // Guardar el signo si existe
        $negative = (strpos($s, '-') === 0);
        $s = str_replace('-', '', $s);
        
        // Formato chileno: 1.234.567 (punto como separador de miles)
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        
        if (!preg_match('/^\d+(\.\d+)?$/', $s)) {
            return null;
        }
        
        $value = (float)$s;
        return $negative ? -$value : $value;
    }
}