<?php
// app/Core/Container.php
namespace App\Core;

class Container
{
    private array $factories = [];
    private array $instances = [];

    public function set(string $key, callable $factory): void
    {
        $this->factories[$key] = $factory;
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]) || isset($this->instances[$key]);
    }

    public function get(string $key)
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }
        if (!isset($this->factories[$key])) {
            throw new \RuntimeException("Service '{$key}' not found in container");
        }
        $factory = $this->factories[$key];
        $instance = $factory($this);
        $this->instances[$key] = $instance;
        return $instance;
    }
}
