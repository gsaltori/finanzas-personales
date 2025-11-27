<?php
// app/Controllers/BaseController.php
namespace App\Controllers;

use App\Core\Container;

class BaseController
{
    protected ?Container $container = null;

    public function setContainer(Container $c): void
    {
        $this->container = $c;
    }

    protected function view(string $path, array $params = []): void
    {
        extract($params, EXTR_SKIP);
        $viewFile = __DIR__ . '/../../views/' . $path . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View {$viewFile} not found");
        }
        include __DIR__ . '/../../views/layouts/main.php';
    }
}
