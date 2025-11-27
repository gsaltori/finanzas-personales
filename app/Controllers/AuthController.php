<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Services\AuthService;

class AuthController extends BaseController
{
    public function loginView(): void
    {
        $this->view('auth/login', ['title' => 'Iniciar sesión']);
    }

    public function loginAction(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        /** @var AuthService $auth */
        $auth = $this->container?->get('auth') ?? null;
        if (!$auth) {
            throw new \RuntimeException('AuthService no disponible en container');
        }

        if ($auth->login($email, $password)) {
            header('Location: /dashboard');
            exit;
        }

        $this->view('auth/login', ['title' => 'Iniciar sesión', 'error' => 'Credenciales inválidas']);
    }

    public function logoutAction(): void
    {
        /** @var AuthService $auth */
        $auth = $this->container?->get('auth') ?? null;
        $auth?->logout();
        header('Location: /login');
        exit;
    }
}
