<?php
class Category extends BaseModel {
  public function listForUser(int $userId): array {
    $sql = "SELECT * FROM categorias WHERE usuario_id IS NULL OR usuario_id = :uid ORDER BY usuario_id DESC, nombre";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function create(string $name, string $tipo, ?int $userId = null) {
    $sql = "INSERT INTO categorias (nombre,tipo,usuario_id) VALUES (:nombre,:tipo,:uid)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':nombre' => $name, ':tipo' => $tipo, ':uid' => $userId]);
    return (int)$this->db->lastInsertId();
  }

  public function findById(int $id) {
    $sql = "SELECT * FROM categorias WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function update(int $id, string $nombre, string $tipo): bool {
    $sql = "UPDATE categorias SET nombre = :nombre, tipo = :tipo WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':nombre' => $nombre, ':tipo' => $tipo, ':id' => $id]);
  }
}
