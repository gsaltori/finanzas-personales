<?php
/**
 * Interface para estrategias de parsing de extractos bancarios
 */
interface BankStatementParserStrategy {
    /**
     * Intenta parsear el HTML y retorna array de transacciones
     * @return array Array de transacciones o array vacío si no puede parsear
     */
    public function parse(string $html): array;
    
    /**
     * Indica si esta estrategia puede manejar el HTML dado
     */
    public function canHandle(string $html): bool;
    
    /**
     * Nombre descriptivo de la estrategia
     */
    public function getName(): string;
}

/**
 * Clase principal que coordina las estrategias de parsing
 */
class BankStatementParser {
    private array $strategies = [];
    private array $diagnostics = [];
    
    public function __construct() {
        // Registrar estrategias en orden de prioridad
        // Primero las más específicas, luego las genéricas
        $this->addStrategy(new BancoChileStrategy());
        $this->addStrategy(new TableWithHeadersStrategy());
        $this->addStrategy(new VerticalBlocksStrategy());
        $this->addStrategy(new PlainTextStrategy());
    }
    
    public function addStrategy(BankStatementParserStrategy $strategy): void {
        $this->strategies[] = $strategy;
    }
    
    /**
     * Parsea el HTML usando la primera estrategia que pueda manejarlo
     */
    public function parse(string $html): array {
        $this->diagnostics = [];
        
        foreach ($this->strategies as $strategy) {
            $this->diagnostics[] = "Intentando estrategia: " . $strategy->getName();
            
            if ($strategy->canHandle($html)) {
                $this->diagnostics[] = "Estrategia {$strategy->getName()} puede manejar el contenido";
                
                $result = $strategy->parse($html);
                
                if (!empty($result)) {
                    $this->diagnostics[] = "Estrategia {$strategy->getName()} exitosa: " . count($result) . " filas";
                    return $result;
                }
                
                $this->diagnostics[] = "Estrategia {$strategy->getName()} no retornó resultados";
            } else {
                $this->diagnostics[] = "Estrategia {$strategy->getName()} no puede manejar este contenido";
            }
        }
        
        $this->diagnostics[] = "Ninguna estrategia pudo parsear el contenido";
        return [];
    }
    
    public function getDiagnostics(): array {
        return $this->diagnostics;
    }
}

/**
 * Helper trait para funciones comunes de parsing
 */
trait ParserHelpers {
    protected function normalizeDate(string $d): ?string {
        $d = trim($d);
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $d, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $ts = @strtotime($d);
        return $ts ? date('Y-m-d', $ts) : null;
    }
    
    protected function parseAmount(string $s): ?float {
        $s = trim($s);
        if ($s === '' || $s === '-') {
            return null;
        }
        
        // Remover símbolos de moneda y espacios
        $s = preg_replace('/[$€£¥\s]/', '', $s);
        
        // Manejar formato chileno: 1.234.567,89
        if (substr_count($s, '.') > 1 || (strpos($s, '.') !== false && strpos($s, ',') !== false)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // Manejar formato americano: 1,234,567.89
            $s = str_replace(',', '', $s);
        }
        
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        
        return (float)$s;
    }
    
    protected function isSkippableRow(string $descripcion): bool {
        $descLow = mb_strtolower($descripcion, 'UTF-8');
        $skipPatterns = [
            'total',
            'saldo disponible',
            'saldo anterior',
            'movimientos al',
            'página',
            'cuenta',
            'rut:'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (mb_stripos($descLow, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}