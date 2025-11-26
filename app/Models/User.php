<?php
class User extends BaseModel {
  public function create(string $email, string $hash, string $nombre) {
    $sql = "INSERT INTO usuarios (email,password,nombre) VALUES (:email,:password,:nombre)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':email' => $email, ':password' => $hash, ':nombre' => $nombre]);
    return (int)$this->db->lastInsertId();
  }

  public function findByEmail(string $email) {
    $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
  }

  public function findById(int $id) {
    $sql = "SELECT id,email,nombre,preferencias,fecha_creacion FROM usuarios WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
  }
}
