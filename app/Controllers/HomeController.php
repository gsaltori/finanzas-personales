<?php
// app/Controllers/HomeController.php
namespace App\Controllers;

use App\Services\AuthService;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->view('home/index', ['title' => 'Bienvenido']);
    }

    public function dashboard(): void
    {
        $auth = $this->container?->get('auth') ?? null;
        if ($auth instanceof AuthService && !$auth->isLogged()) {
            header('Location: /login');
            exit;
        }

        $this->view('home/index', ['title' => 'Dashboard (privado)']);
    }
}
