<?php
class Audit extends BaseModel {
  public function log(string $entidad, int $entidad_id, ?int $usuario_id, string $accion, ?array $antes = null, ?array $despues = null, ?string $ip = null, ?string $ua = null) {
    $sql = "INSERT INTO audit_trx (entidad, entidad_id, usuario_id, accion, antes, despues, ip, user_agent) VALUES (:entidad,:entidad_id,:usuario_id,:accion,:antes,:despues,:ip,:ua)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':entidad' => $entidad,
      ':entidad_id' => $entidad_id,
      ':usuario_id' => $usuario_id,
      ':accion' => $accion,
      ':antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
      ':despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
      ':ip' => $ip,
      ':ua' => $ua
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function listRecent(int $limit = 50): array {
    $sql = "SELECT * FROM audit_trx ORDER BY creado_en DESC LIMIT :lim";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
