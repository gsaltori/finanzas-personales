<?php
// app/Models/User.php
namespace App\Models;

class User
{
    public ?int $id = null;
    public string $email = '';
    public string $password = ''; // hashed

    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id = $row['id'] ?? null;
        $u->email = $row['email'] ?? '';
        $u->password = $row['password'] ?? '';
        return $u;
    }
}
