<?php
class Transaction extends BaseModel {
  public function create(int $usuario_id, float $monto, string $fecha, ?int $categoria_id, string $descripcion, string $tipo) {
    $sql = "INSERT INTO transacciones (usuario_id,monto,fecha,categoria_id,descripcion,tipo) VALUES (:uid,:monto,:fecha,:cat,:desc,:tipo)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':uid' => $usuario_id,
      ':monto' => $monto,
      ':fecha' => $fecha,
      ':cat' => $categoria_id,
      ':desc' => $descripcion,
      ':tipo' => $tipo
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function findById(int $id) {
    $sql = "SELECT * FROM transacciones WHERE id = :id LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function update(int $id, int $usuario_id, float $monto, string $fecha, ?int $categoria_id, string $descripcion, string $tipo): bool {
    // obtener estado anterior
    $antes = $this->findById($id);

    $sql = "UPDATE transacciones
            SET monto = :monto, fecha = :fecha, categoria_id = :cat, descripcion = :desc, tipo = :tipo
            WHERE id = :id AND usuario_id = :uid";
    $stmt = $this->db->prepare($sql);
    $ok = $stmt->execute([
      ':monto' => $monto,
      ':fecha' => $fecha,
      ':cat'   => $categoria_id,
      ':desc'  => $descripcion,
      ':tipo'  => $tipo,
      ':id'    => $id,
      ':uid'   => $usuario_id
    ]);

    if ($ok) {
      // refrescar estado posterior
      $despues = $this->findById($id);
      // log audit
      require_once __DIR__ . '/Audit.php';
      $audit = new Audit($this->db);
      $ip = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
      $audit->log('transaccion', $id, $usuario_id, 'update', $antes, $despues, $ip, $ua);
    }

    return (bool)$ok;
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM transacciones WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':id' => $id]);
  }

  public function getByUserWithFilters(int $usuario_id, array $filters, int $limit, int $offset): array {
    $sql = "SELECT t.*, c.nombre AS categoria_nombre FROM transacciones t LEFT JOIN categorias c ON t.categoria_id = c.id WHERE t.usuario_id = :uid";
    $params = [':uid' => $usuario_id];

    if (!empty($filters['from'])) {
      $sql .= " AND t.fecha >= :from";
      $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
      $sql .= " AND t.fecha <= :to";
      $params[':to'] = $filters['to'];
    }
    if (!empty($filters['category'])) {
      $sql .= " AND t.categoria_id = :cat";
      $params[':cat'] = $filters['category'];
    }
    if (!empty($filters['type'])) {
      $sql .= " AND t.tipo = :tipo";
      $params[':tipo'] = $filters['type'];
    }

    $sql .= " ORDER BY t.fecha DESC, t.id DESC LIMIT :limit OFFSET :offset";
    $stmt = $this->db->prepare($sql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function countByUser(int $usuario_id, array $filters): int {
    $sql = "SELECT COUNT(*) FROM transacciones WHERE usuario_id = :uid";
    $params = [':uid' => $usuario_id];

    if (!empty($filters['from'])) {
      $sql .= " AND fecha >= :from";
      $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
      $sql .= " AND fecha <= :to";
      $params[':to'] = $filters['to'];
    }
    if (!empty($filters['category'])) {
      $sql .= " AND categoria_id = :cat";
      $params[':cat'] = $filters['category'];
    }
    if (!empty($filters['type'])) {
      $sql .= " AND tipo = :tipo";
      $params[':tipo'] = $filters['type'];
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  }

  public function summaryForMonth(int $usuario_id, int $mes, int $anio): array {
    $sql = "
      SELECT
        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
        COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) AS gastos
      FROM transacciones
      WHERE usuario_id = :uid AND MONTH(fecha) = :mes AND YEAR(fecha) = :anio
    ";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'uid'  => $usuario_id,
      'mes'  => $mes,
      'anio' => $anio
    ]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['ingresos' => 0, 'gastos' => 0];
    $r['ingresos'] = (float)($r['ingresos'] ?? 0);
    $r['gastos']   = (float)($r['gastos'] ?? 0);
    $r['balance']  = $r['ingresos'] - $r['gastos'];
    return $r;
  }

  public function sumByCategoryForMonth(int $usuario_id, int $mes, int $anio): array {
    $sql = "
      SELECT COALESCE(c.nombre,'Sin categoría') AS categoria, COALESCE(SUM(t.monto),0) AS total
      FROM transacciones t
      LEFT JOIN categorias c ON t.categoria_id = c.id
      WHERE t.usuario_id = :uid AND MONTH(t.fecha) = :mes AND YEAR(t.fecha) = :anio AND t.tipo = 'gasto'
      GROUP BY COALESCE(c.nombre,'Sin categoría')
      ORDER BY total DESC
      LIMIT 20
    ";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      'uid'  => $usuario_id,
      'mes'  => $mes,
      'anio' => $anio
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function autoAssignCategoryId(int $usuario_id, string $descripcion): ?int {
    $map = [
      'super' => 'Alimentación',
      'uber' => 'Transporte',
      'taxi' => 'Transporte',
      'cine' => 'Entretenimiento',
      'salario' => 'Salario'
    ];
    $desc = mb_strtolower($descripcion, 'UTF-8');
    foreach ($map as $k => $catName) {
      if (strpos($desc, $k) !== false) {
        $sql = "SELECT id FROM categorias WHERE LOWER(nombre) = :n AND (usuario_id IS NULL OR usuario_id = :uid) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':n' => mb_strtolower($catName, 'UTF-8'), ':uid' => $usuario_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['id'];
      }
    }
    return null;
  }
}
