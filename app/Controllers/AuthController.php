<?php
class AuthController extends BaseController {
  private User $userModel;
  private RateLimiter $rateLimiter;

  public function __construct() {
    parent::__construct();
    $this->userModel = new User($this->db);
    $this->rateLimiter = new RateLimiter($this->config['rate_limit']);
  }

  public function login() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
      $password = $_POST['password'] ?? '';
      $token = $_POST['csrf_token'] ?? '';

      // Validación CSRF
      if (!$this->verify_csrf($token)) {
        $this->setFlash('danger', 'Token CSRF inválido.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
        exit;
      }

      // Rate limiting por IP
      $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
      
      if (!$this->rateLimiter->attempt('login', $identifier)) {
        $remaining = $this->rateLimiter->getRemainingTime('login', $identifier);
        $minutes = ceil($remaining / 60);
        $this->setFlash(
          'danger',
          "Demasiados intentos fallidos. Por favor espera {$minutes} minutos antes de intentar nuevamente."
        );
        header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
        exit;
      }

      // Validación de datos
      if (!$email || !$password) {
        $this->rateLimiter->hit('login', $identifier);
        $this->setFlash('danger', 'Email o contraseña inválidos.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
        exit;
      }

      // Verificar usuario
      $user = $this->userModel->findByEmail($email);
      
      if ($user && password_verify($password, $user['password'])) {
        // Login exitoso - limpiar rate limit
        $this->rateLimiter->clear('login', $identifier);
        
        // Regenerar session ID para prevenir session fixation
        session_regenerate_id(true);
        
        // Establecer datos de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Audit log
        if (class_exists('Audit')) {
          $audit = new Audit($this->db);
          $audit->log(
            'usuario',
            (int)$user['id'],
            (int)$user['id'],
            'login',
            null,
            ['email' => $email],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
          );
        }
        
        $this->setFlash('success', 'Bienvenido, ' . $this->e($user['nombre']));
        header('Location: ' . $this->config['base_url'] . '/?r=dashboard/index');
        exit;
      } else {
        // Login fallido - registrar intento
        $this->rateLimiter->hit('login', $identifier);
        
        $attemptsLeft = $this->rateLimiter->attemptsLeft('login', $identifier);
        
        if ($attemptsLeft > 0) {
          $this->setFlash(
            'danger',
            "Credenciales incorrectas. Te quedan {$attemptsLeft} intentos."
          );
        } else {
          $remaining = $this->rateLimiter->getRemainingTime('login', $identifier);
          $minutes = ceil($remaining / 60);
          $this->setFlash(
            'danger',
            "Demasiados intentos fallidos. Bloqueado por {$minutes} minutos."
          );
        }
        
        header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
        exit;
      }
    }

    // Verificar si hay rate limiting activo
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitWarning = null;
    
    if (!$this->rateLimiter->attempt('login', $identifier)) {
      $remaining = $this->rateLimiter->getRemainingTime('login', $identifier);
      $minutes = ceil($remaining / 60);
      $rateLimitWarning = "Temporalmente bloqueado. Espera {$minutes} minutos.";
    }

    $this->view('auth/login.php', [
      'csrf' => $this->csrf_token(),
      'flash' => $this->getFlash(),
      'rate_limit_warning' => $rateLimitWarning
    ]);
  }

  public function register() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
      $nombre = trim($_POST['nombre'] ?? '');
      $password = $_POST['password'] ?? '';
      $password2 = $_POST['password2'] ?? '';
      $token = $_POST['csrf_token'] ?? '';

      if (!$this->verify_csrf($token)) {
        $this->setFlash('danger', 'Token CSRF inválido.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/register');
        exit;
      }

      // Validaciones
      $errors = [];
      
      if (!$email) {
        $errors[] = 'Email inválido';
      }
      
      if (strlen($nombre) < 2) {
        $errors[] = 'El nombre debe tener al menos 2 caracteres';
      }
      
      if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres';
      }
      
      if ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden';
      }
      
      // Validar complejidad de contraseña
      if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener mayúsculas, minúsculas y números';
      }

      if (!empty($errors)) {
        $this->setFlash('danger', implode('. ', $errors) . '.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/register');
        exit;
      }

      // Verificar email existente
      if ($this->userModel->findByEmail($email)) {
        $this->setFlash('danger', 'Ya existe una cuenta con ese correo.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/register');
        exit;
      }

      // Crear usuario
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $id = $this->userModel->create($email, $hash, $nombre);
      
      if ($id) {
        // Audit log
        if (class_exists('Audit')) {
          $audit = new Audit($this->db);
          $audit->log(
            'usuario',
            $id,
            null,
            'register',
            null,
            ['email' => $email, 'nombre' => $nombre],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
          );
        }
        
        $this->setFlash('success', 'Cuenta creada. Puedes iniciar sesión.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
        exit;
      } else {
        $this->setFlash('danger', 'Error al crear cuenta. Intenta nuevamente.');
        header('Location: ' . $this->config['base_url'] . '/?r=auth/register');
        exit;
      }
    }

    $this->view('auth/register.php', [
      'csrf' => $this->csrf_token(),
      'flash' => $this->getFlash()
    ]);
  }

  public function logout() {
    // Audit log antes de destruir sesión
    if (!empty($_SESSION['user_id']) && class_exists('Audit')) {
      $audit = new Audit($this->db);
      $audit->log(
        'usuario',
        (int)$_SESSION['user_id'],
        (int)$_SESSION['user_id'],
        'logout',
        null,
        null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
      );
    }
    
    session_unset();
    session_destroy();
    
    // Crear nueva sesión limpia
    session_start();
    
    header('Location: ' . $this->config['base_url'] . '/?r=auth/login');
    exit;
  }
}