<?php
/**
 * RateLimiter - Protección contra fuerza bruta y abuso
 * Usa sesión para tracking simple sin dependencias externas
 */
class RateLimiter {
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    /**
     * Verifica si una acción está bloqueada por rate limiting
     * 
     * @param string $action Identificador de la acción (ej: 'login', 'import')
     * @param string $identifier Identificador único (IP, user ID, email, etc)
     * @return bool true si está permitido, false si está bloqueado
     */
    public function attempt(string $action, string $identifier): bool {
        $key = $this->getKey($action, $identifier);
        $attempts = $this->getAttempts($key);
        $maxAttempts = $this->getMaxAttempts($action);
        $window = $this->getWindow($action);
        
        // Limpiar intentos antiguos
        $attempts = $this->cleanOldAttempts($attempts, $window);
        
        // Verificar si excede el límite
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Registra un intento fallido
     */
    public function hit(string $action, string $identifier): void {
        $key = $this->getKey($action, $identifier);
        $attempts = $this->getAttempts($key);
        $window = $this->getWindow($action);
        
        // Limpiar antiguos
        $attempts = $this->cleanOldAttempts($attempts, $window);
        
        // Agregar nuevo intento
        $attempts[] = time();
        
        // Guardar en sesión
        $_SESSION['rate_limit'][$key] = $attempts;
    }
    
    /**
     * Limpia los intentos de una acción (después de éxito)
     */
    public function clear(string $action, string $identifier): void {
        $key = $this->getKey($action, $identifier);
        unset($_SESSION['rate_limit'][$key]);
    }
    
    /**
     * Obtiene el tiempo restante de bloqueo en segundos
     */
    public function getRemainingTime(string $action, string $identifier): int {
        $key = $this->getKey($action, $identifier);
        $attempts = $this->getAttempts($key);
        $window = $this->getWindow($action);
        
        if (empty($attempts)) {
            return 0;
        }
        
        // El tiempo de bloqueo expira cuando el intento más antiguo sale de la ventana
        $oldestAttempt = min($attempts);
        $expiresAt = $oldestAttempt + $window;
        $remaining = $expiresAt - time();
        
        return max(0, $remaining);
    }
    
    /**
     * Retorna cuántos intentos quedan
     */
    public function attemptsLeft(string $action, string $identifier): int {
        $key = $this->getKey($action, $identifier);
        $attempts = $this->getAttempts($key);
        $maxAttempts = $this->getMaxAttempts($action);
        $window = $this->getWindow($action);
        
        $attempts = $this->cleanOldAttempts($attempts, $window);
        
        return max(0, $maxAttempts - count($attempts));
    }
    
    // Métodos privados
    
    private function getKey(string $action, string $identifier): string {
        return md5($action . ':' . $identifier);
    }
    
    private function getAttempts(string $key): array {
        return $_SESSION['rate_limit'][$key] ?? [];
    }
    
    private function cleanOldAttempts(array $attempts, int $window): array {
        $cutoff = time() - $window;
        return array_filter($attempts, fn($timestamp) => $timestamp > $cutoff);
    }
    
    private function getMaxAttempts(string $action): int {
        $key = $action . '_attempts';
        return $this->config[$key] ?? 5;
    }
    
    private function getWindow(string $action): int {
        $key = $action . '_window';
        return $this->config[$key] ?? 900; // 15 min por defecto
    }
}