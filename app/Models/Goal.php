<?php
class Goal extends BaseModel {
  public function create(int $usuario_id, string $nombre, float $objetivo, ?string $fecha_limite = null) {
    $sql = "INSERT INTO metas_ahorro (usuario_id,nombre,objetivo,actual,fecha_limite) VALUES (:uid,:nombre,:objetivo,0,:fecha_limite)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':uid' => $usuario_id,
      ':nombre' => $nombre,
      ':objetivo' => $objetivo,
      ':fecha_limite' => $fecha_limite
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function findById(int $id) {
    $sql = "SELECT * FROM metas_ahorro WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function listForUser(int $usuario_id): array {
    $sql = "SELECT * FROM metas_ahorro WHERE usuario_id = :uid ORDER BY creado_en DESC";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':uid' => $usuario_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function updateActual(int $id, float $nuevo_actual) {
    $sql = "UPDATE metas_ahorro SET actual = :actual WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':actual' => $nuevo_actual, ':id' => $id]);
  }

  public function delete(int $id) {
    $sql = "DELETE FROM metas_ahorro WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':id' => $id]);
  }

  public function addContribution(int $id, float $amount) {
    $sql = "UPDATE metas_ahorro SET actual = actual + :amt WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':amt' => $amount, ':id' => $id]);
  }
}
