<?php
class Budget extends BaseModel {
  public function create(int $usuario_id, int $categoria_id, float $monto_maximo, int $mes, int $anio) {
    $sql = "INSERT INTO presupuestos (usuario_id, categoria_id, monto_maximo, mes, `anio`) VALUES (:uid,:cat,:monto,:mes,:anio)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':uid' => $usuario_id,
      ':cat' => $categoria_id,
      ':monto' => $monto_maximo,
      ':mes' => $mes,
      ':anio' => $anio
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function update(int $id, float $monto_maximo) {
    $sql = "UPDATE presupuestos SET monto_maximo = :monto WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':monto' => $monto_maximo, ':id' => $id]);
  }

  public function delete(int $id) {
    $sql = "DELETE FROM presupuestos WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':id' => $id]);
  }

  public function listForUserMonth(int $usuario_id, int $mes, int $anio): array {
    $sql = "SELECT p.*, c.nombre AS categoria_nombre FROM presupuestos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.usuario_id = :uid AND p.mes = :mes AND p.`anio` = :anio
            ORDER BY c.nombre";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':uid' => $usuario_id, ':mes' => $mes, ':anio' => $anio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function findById(int $id) {
    $sql = "SELECT * FROM presupuestos WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function getSpentForBudget(int $usuario_id, int $categoria_id, int $mes, int $anio): float {
    $sql = "SELECT COALESCE(SUM(monto),0) FROM transacciones WHERE usuario_id = :uid AND categoria_id = :cat AND tipo = 'gasto' AND MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':uid' => $usuario_id, ':cat' => $categoria_id, ':mes' => $mes, ':anio' => $anio]);
    return (float)$stmt->fetchColumn();
  }
}
