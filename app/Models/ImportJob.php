<?php
// app/Models/ImportJob.php
// Modelo mínimo para import_jobs e import_rows.
// Usa PDO provisto por tu capa de conexión existente (no crea su propia conexión).

class ImportJob {
  private PDO $db;

  public function __construct(PDO $db) {
    $this->db = $db;
  }

  public function createJob(int $usuario_id, string $file_hash, ?string $origen = null, array $meta = []): int {
    $sql = "INSERT INTO import_jobs (usuario_id, file_hash, origen, filas_total, filas_importadas, estado, meta, creado_en)
            VALUES (:uid, :fh, :origen, 0, 0, 'pending', :meta, NOW())";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':uid' => $usuario_id,
      ':fh' => $file_hash,
      ':origen' => $origen,
      ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function findByHash(int $usuario_id, string $file_hash) {
    $stmt = $this->db->prepare("SELECT * FROM import_jobs WHERE usuario_id = :uid AND file_hash = :fh LIMIT 1");
    $stmt->execute([':uid' => $usuario_id, ':fh' => $file_hash]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    $r['meta_parsed'] = null;
    if (!empty($r['meta'])) {
      $decoded = json_decode($r['meta'], true);
      $r['meta_parsed'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : ['raw' => $r['meta']];
    }
    return $r;
  }

  public function findById(int $jobId) {
    $stmt = $this->db->prepare("SELECT * FROM import_jobs WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $jobId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    $r['meta_parsed'] = !empty($r['meta']) ? json_decode($r['meta'], true) : null;
    return $r;
  }

  public function setCounts(int $jobId, int $total, int $imported, string $estado = 'done'): bool {
    $stmt = $this->db->prepare("UPDATE import_jobs SET filas_total = :total, filas_importadas = :imported, estado = :estado WHERE id = :id");
    return $stmt->execute([':total' => $total, ':imported' => $imported, ':estado' => $estado, ':id' => $jobId]);
  }

  public function addRow(int $jobId, string $fila_hash, array $row, string $estado = 'new', ?string $detalle = null): int {
    $sql = "INSERT INTO import_rows (job_id, fila_hash, fecha, descripcion, canal, monto, tipo, saldo, estado, detalle, creado_en)
            VALUES (:job, :fh, :fecha, :desc, :canal, :monto, :tipo, :saldo, :estado, :detalle, NOW())";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      ':job' => $jobId,
      ':fh' => $fila_hash,
      ':fecha' => $row['fecha'] ?? null,
      ':desc' => $row['descripcion'] ?? null,
      ':canal' => $row['canal'] ?? null,
      ':monto' => $row['monto'] ?? null,
      ':tipo' => $row['tipo'] ?? null,
      ':saldo' => $row['saldo'] ?? null,
      ':estado' => $estado,
      ':detalle' => $detalle
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function markRowImported(int $jobId, string $fila_hash): bool {
    $stmt = $this->db->prepare("UPDATE import_rows SET estado = 'imported' WHERE job_id = :job AND fila_hash = :fh");
    return $stmt->execute([':job' => $jobId, ':fh' => $fila_hash]);
  }
}