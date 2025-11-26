<?php
// app/Controllers/ImportController.php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Helpers/PdfConverter.php';
require_once __DIR__ . '/../Models/ImportJob.php';

class ImportController extends BaseController {
  private Transaction $trxModel;
  private Category $catModel;
  private ImportJob $jobModel;

  public function __construct() {
    parent::__construct();
    $this->trxModel = new Transaction($this->db);
    $this->catModel = new Category($this->db);
    $this->jobModel = new ImportJob($this->db);
  }

  public function index() {
    $this->requireAuth();
    $execOk = PdfConverter::isExecAvailable();
    $hints = method_exists('PdfConverter','installationHints') ? PdfConverter::installationHints() : [];
    $this->view('import/index.php', [
      'csrf' => $this->csrf_token(),
      'flash' => $this->getFlash(),
      'execOk' => $execOk,
      'hints' => $hints
    ]);
  }

  public function preview() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location:' . $this->config['base_url'] . '/?r=import/index');
      exit;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) {
      $this->setFlash('danger','Token CSRF inválido');
      header('Location:' . $this->config['base_url'] . '/?r=import/index');
      exit;
    }

    $content = '';
    $origen = null;
    $conversionLog = [];

    if (!empty($_POST['html'])) {
      $content = $_POST['html'];
      $origen = 'pasted_html';
      $conversionLog[] = 'Contenido pegado por usuario (HTML).';
    } elseif (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
      $f = $_FILES['file'];
      $origen = $f['name'] ?? 'uploaded';
      $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
      $tmp = $f['tmp_name'];

      if (in_array($ext, ['html','htm','txt'])) {
        $content = file_get_contents($tmp);
        $conversionLog[] = 'Leído HTML/TXT desde archivo subido.';
      } elseif ($ext === 'pdf') {
        $popplerBin = $this->config['poppler_bin'] ?? null;
        $res = PdfConverter::convertPdfToHtml($tmp, $popplerBin);
        if (!empty($res['diagnostic']) && is_array($res['diagnostic'])) {
          foreach ($res['diagnostic'] as $d) $conversionLog[] = $d;
        }
        if (!empty($res['ok'])) {
          $content = $res['content'];
          $conversionLog[] = "Conversión exitosa vía {$res['method']}.";
        } else {
          $conversionLog[] = "Conversión fallida: {$res['error']}";
          $file_hash = hash('sha256', file_get_contents($tmp));
          $userId = (int)($_SESSION['user_id'] ?? 0);
          $existing = $this->jobModel->findByHash($userId, $file_hash);
          if (empty($existing)) {
            $jobId = $this->jobModel->createJob($userId, $file_hash, $origen, ['created_by'=>$userId, 'conversion_log'=>$conversionLog]);
            $this->jobModel->setCounts($jobId, 0, 0, 'error');
          } else {
            $metaToSave = $existing['meta_parsed'] ?? [];
            $metaToSave['conversion_log'] = $conversionLog;
            $stmt = $this->db->prepare("UPDATE import_jobs SET meta = :meta, estado = 'error' WHERE id = :id");
            $stmt->execute([':meta'=>json_encode($metaToSave, JSON_UNESCAPED_UNICODE), ':id'=>$existing['id']]);
          }
          $this->setFlash('danger','No se pudo convertir el PDF. Revisa el job en Import Jobs para ver logs.');
          header('Location:' . $this->config['base_url'] . '/?r=import/jobs');
          exit;
        }
      } else {
        $content = file_get_contents($tmp);
        $conversionLog[] = "Archivo con extensión {$ext} leído en crudo; puede no contener HTML legible.";
      }
    } else {
      $this->setFlash('danger','No se entregó contenido para previsualizar.');
      header('Location:' . $this->config['base_url'] . '/?r=import/index');
      exit;
    }

    $file_hash = hash('sha256', $content);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $existing = $this->jobModel->findByHash($userId, $file_hash);

    $jobNote = null;
    if (!empty($existing) && is_array($existing)) {
      $jobId = (int)($existing['id'] ?? 0);
      $estadoExistente = $existing['estado'] ?? null;
      $jobNote = "Este archivo ya fue procesado anteriormente (job id {$jobId}" . ($estadoExistente !== null ? ", estado {$estadoExistente}" : "") . ").";
    } else {
      $jobId = $this->jobModel->createJob($userId, $file_hash, $origen, ['created_by'=>$userId, 'conversion_log'=>$conversionLog]);
    }

    $rows = $this->parseBankStatementHtml($content);

    $total = 0;
    foreach ($rows as $r) {
      $fila_hash = hash('sha256', ($r['fecha'] ?? '') . '|' . mb_substr($r['descripcion'] ?? '',0,120) . '|' . number_format((float)($r['monto'] ?? 0),2,'.',''));
      try {
        $this->jobModel->addRow($jobId, $fila_hash, $r, 'new', null);
        $total++;
      } catch (PDOException $e) {
        // duplicado en job; ignorar
      }
    }

    $this->jobModel->setCounts($jobId, $total, 0, 'pending');

    $this->view('import/preview.php', [
      'csrf' => $this->csrf_token(),
      'jobId' => $jobId,
      'rows' => $rows,
      'jobNote' => $jobNote,
      'conversionLog' => $conversionLog,
      'flash' => $this->getFlash()
    ]);
  }

  public function run() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . $this->config['base_url'] . '/?r=import/index'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','Token CSRF inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/index'); exit; }

    $jobId = (int)($_POST['job_id'] ?? 0);
    $selected = $_POST['select'] ?? [];
    if ($jobId <= 0 || !is_array($selected)) {
      $this->setFlash('danger','Request inválido.');
      header('Location:' . $this->config['base_url'] . '/?r=import/index');
      exit;
    }

    $imported = 0; $skipped = 0;
    foreach ($selected as $fh) {
      $stmtRow = $this->db->prepare("SELECT * FROM import_rows WHERE job_id = :job AND fila_hash = :fh LIMIT 1");
      $stmtRow->execute([':job'=>$jobId, ':fh'=>$fh]);
      $r = $stmtRow->fetch(PDO::FETCH_ASSOC);
      if (!$r) continue;
      if (($r['estado'] ?? '') === 'imported') { $skipped++; continue; }

      $row = [
        'fecha' => $r['fecha'],
        'descripcion' => $r['descripcion'],
        'canal' => $r['canal'],
        'monto' => (float)($r['monto'] ?? 0),
        'tipo' => $r['tipo'],
        'saldo' => $r['saldo']
      ];

      if (empty($row['fecha']) || $row['monto'] == 0.0) {
        $stmtUpd = $this->db->prepare("UPDATE import_rows SET estado='error', detalle = :det WHERE job_id = :job AND fila_hash = :fh");
        $stmtUpd->execute([':det'=>'Fecha o monto inválido', ':job'=>$jobId, ':fh'=>$fh]);
        continue;
      }

      $userId = (int)($_SESSION['user_id'] ?? 0);
      $exists = $this->existsSimilarTransaction($userId, $row['fecha'], $row['descripcion'], $row['monto']);
      if ($exists) {
        $stmtUpd = $this->db->prepare("UPDATE import_rows SET estado='skipped', detalle = :det WHERE job_id = :job AND fila_hash = :fh");
        $stmtUpd->execute([':det'=>'Ya existe transacción similar', ':job'=>$jobId, ':fh'=>$fh]);
        $skipped++;
        continue;
      }

      $catId = $this->trxModel->autoAssignCategoryId($userId, $row['descripcion'] ?? '');
      $tipo = $row['tipo'] === 'ingreso' ? 'ingreso' : 'gasto';
      $montoToInsert = abs((float)$row['monto']);
      try {
        $newId = $this->trxModel->create($userId, $montoToInsert, $row['fecha'], $catId, $row['descripcion'] ?? '', $tipo);
        if ($newId) {
          $this->jobModel->markRowImported($jobId, $fh);
          $stmtUpd = $this->db->prepare("UPDATE import_rows SET estado='imported', detalle = :det WHERE job_id = :job AND fila_hash = :fh");
          $stmtUpd->execute([':det'=>"Imported trx id {$newId}", ':job'=>$jobId, ':fh'=>$fh]);
          $imported++;
        } else {
          $stmtUpd = $this->db->prepare("UPDATE import_rows SET estado='error', detalle = :det WHERE job_id = :job AND fila_hash = :fh");
          $stmtUpd->execute([':det'=>'Error al crear transacción', ':job'=>$jobId, ':fh'=>$fh]);
        }
      } catch (Throwable $e) {
        $stmtUpd = $this->db->prepare("UPDATE import_rows SET estado='error', detalle = :det WHERE job_id = :job AND fila_hash = :fh");
        $stmtUpd->execute([':det'=>$e->getMessage(), ':job'=>$jobId, ':fh'=>$fh]);
      }
    }

    $this->jobModel->setCounts($jobId, (int)$this->countRowsForJob($jobId), $imported, 'done');
    $this->setFlash('success', "Import finalizado. Importados: {$imported}. Omitidos: {$skipped}.");
    header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId);
    exit;
  }

  public function jobs() {
    $this->requireAuth();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(10, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $fEstado = trim($_GET['estado'] ?? 'all');
    $fFrom = trim($_GET['from'] ?? '');
    $fTo = trim($_GET['to'] ?? '');
    $params = [':uid' => $userId];

    $where = " WHERE usuario_id = :uid ";
    if ($fEstado !== '' && $fEstado !== 'all') {
      $where .= " AND estado = :estado ";
      $params[':estado'] = $fEstado;
    }
    if ($fFrom !== '') {
      $where .= " AND DATE(creado_en) >= :from ";
      $params[':from'] = $fFrom;
    }
    if ($fTo !== '') {
      $where .= " AND DATE(creado_en) <= :to ";
      $params[':to'] = $fTo;
    }

    $sqlCount = "SELECT COUNT(*) FROM import_jobs " . $where;
    $stmtCount = $this->db->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT * FROM import_jobs " . $where . " ORDER BY creado_en DESC LIMIT :limit OFFSET :offset";
    $stmt = $this->db->prepare($sql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pages = max(1, (int)ceil($total / $limit));
    $this->view('import/jobs.php', [
      'jobs' => $jobs,
      'csrf' => $this->csrf_token(),
      'page' => $page,
      'pages' => $pages,
      'limit' => $limit,
      'total' => $total,
      'filters' => ['estado' => $fEstado, 'from' => $fFrom, 'to' => $fTo]
    ]);
  }

  public function view(string $path = '', array $data = []) {
    if ($path !== '') {
      parent::view($path, $data);
      return;
    }

    $this->requireAuth();
    $jobId = (int)($_GET['job_id'] ?? 0);
    if ($jobId <= 0) {
      $this->setFlash('danger','Job inválido');
      header('Location:' . $this->config['base_url'] . '/?r=import/jobs');
      exit;
    }

    $stmt = $this->db->prepare("SELECT * FROM import_jobs WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
      $this->setFlash('danger','Job no encontrado');
      header('Location:' . $this->config['base_url'] . '/?r=import/jobs');
      exit;
    }

    $meta = null;
    if (!empty($job['meta'])) {
      $decoded = json_decode($job['meta'], true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $meta = $decoded; else $meta = ['raw'=>$job['meta']];
    }

    $stmt = $this->db->prepare("SELECT * FROM import_rows WHERE job_id = :job ORDER BY id");
    $stmt->execute([':job'=>$jobId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    parent::view('import/view.php', ['job'=>$job, 'rows'=>$rows, 'meta'=>$meta, 'csrf'=>$this->csrf_token()]);
  }

  public function runJob() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','CSRF inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }

    $jobId = (int)($_POST['job_id'] ?? 0);
    if ($jobId <= 0) { $this->setFlash('danger','Job inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }

    $stmt = $this->db->prepare("SELECT * FROM import_rows WHERE job_id = :job AND estado IN ('new','skipped','error') ORDER BY id");
    $stmt->execute([':job'=>$jobId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $imported = 0; $skipped = 0;
    foreach ($rows as $r) {
      $ok = $this->importRowByRowRecord($r, $jobId);
      if ($ok === true) $imported++; else $skipped++;
    }

    $this->jobModel->setCounts($jobId, (int)$this->countRowsForJob($jobId), $imported, 'done');
    $this->setFlash('success', "Reprocesamiento completado. Importados: {$imported}. Omitidos: {$skipped}.");
    header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId);
    exit;
  }

  public function deleteJob() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','CSRF inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $jobId = (int)($_POST['job_id'] ?? 0);
    if ($jobId <= 0) { $this->setFlash('danger','Job inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $stmt = $this->db->prepare("DELETE FROM import_jobs WHERE id = :id");
    $stmt->execute([':id'=>$jobId]);
    $this->setFlash('success','Job eliminado.');
    header('Location:' . $this->config['base_url'] . '/?r=import/jobs');
    exit;
  }

  public function runRow() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','CSRF inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $jobId = (int)($_POST['job_id'] ?? 0);
    $filaId = (int)($_POST['fila_id'] ?? 0);
    if ($jobId <= 0 || $filaId <= 0) { $this->setFlash('danger','Request inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId); exit; }
    $stmt = $this->db->prepare("SELECT * FROM import_rows WHERE id = :id AND job_id = :job LIMIT 1");
    $stmt->execute([':id'=>$filaId, ':job'=>$jobId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) { $this->setFlash('danger','Fila no encontrada'); header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId); exit; }
    $ok = $this->importRowByRowRecord($r, $jobId);
    $this->setFlash($ok ? 'success' : 'danger', $ok ? 'Fila importada' : 'Fila no importada (ver detalle)');
    header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId);
    exit;
  }

  public function markSkipped() {
    $this->requireAuth();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $token = $_POST['csrf_token'] ?? '';
    if (!$this->verify_csrf($token)) { $this->setFlash('danger','CSRF inválido'); header('Location:' . $this->config['base_url'] . '/?r=import/jobs'); exit; }
    $jobId = (int)($_POST['job_id'] ?? 0);
    $filaId = (int)($_POST['fila_id'] ?? 0);
    $stmt = $this->db->prepare("UPDATE import_rows SET estado='skipped', detalle='Marcado manualmente' WHERE id = :id AND job_id = :job");
    $stmt->execute([':id'=>$filaId, ':job'=>$jobId]);
    $this->setFlash('success','Fila marcada como skipped');
    header('Location:' . $this->config['base_url'] . '/?r=import/view&job_id=' . $jobId);
    exit;
  }

  private function importRowByRowRecord(array $r, int $jobId): bool {
    if (empty($r['fecha']) || !isset($r['monto'])) {
      $stmt = $this->db->prepare("UPDATE import_rows SET estado='error', detalle=:det WHERE id=:id");
      $stmt->execute([':det'=>'Fecha o monto inválido', ':id'=>$r['id'] ?? 0]);
      return false;
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $exists = $this->existsSimilarTransaction($userId, $r['fecha'], $r['descripcion'] ?? '', (float)$r['monto']);
    if ($exists) {
      $stmt = $this->db->prepare("UPDATE import_rows SET estado='skipped', detalle=:det WHERE id=:id");
      $stmt->execute([':det'=>'Ya existe transacción similar', ':id'=>$r['id'] ?? 0]);
      return false;
    }
    $catId = $this->trxModel->autoAssignCategoryId($userId, $r['descripcion'] ?? '');
    $tipo = ($r['tipo'] ?? '') === 'ingreso' ? 'ingreso' : 'gasto';
    $montoToInsert = abs((float)$r['monto']);
    try {
      $newId = $this->trxModel->create($userId, $montoToInsert, $r['fecha'], $catId, $r['descripcion'] ?? '', $tipo);
      if ($newId) {
        $stmt = $this->db->prepare("UPDATE import_rows SET estado='imported', detalle=:det WHERE id=:id");
        $stmt->execute([':det'=>"Imported trx id {$newId}", ':id'=>$r['id'] ?? 0]);
        $this->jobModel->markRowImported((int)$jobId, $r['fila_hash'] ?? '');
        if (class_exists('Audit')) {
          $audit = new Audit($this->db);
          $ip = $_SERVER['REMOTE_ADDR'] ?? null;
          $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
          $audit->log('import_row', (int)($r['id'] ?? 0), $userId, 'import', $r, ['transaccion_id' => $newId], $ip, $ua);
        }
        return true;
      } else {
        $stmt = $this->db->prepare("UPDATE import_rows SET estado='error', detalle=:det WHERE id=:id");
        $stmt->execute([':det'=>'Error al crear transacción', ':id'=>$r['id'] ?? 0]);
      }
    } catch (Throwable $e) {
      $stmt = $this->db->prepare("UPDATE import_rows SET estado='error', detalle=:det WHERE id=:id");
      $stmt->execute([':det'=>$e->getMessage(), ':id'=>$r['id'] ?? 0]);
    }
    return false;
  }

  private function countRowsForJob(int $jobId): int {
    $stmt = $this->db->prepare("SELECT COUNT(*) FROM import_rows WHERE job_id = :job");
    $stmt->execute([':job'=>$jobId]);
    return (int)$stmt->fetchColumn();
  }

  private function parseBankStatementHtml(string $html): array {
    $rows = [];

    $normalizeDate = function($d) {
        return $this->normalizeDate(trim((string)$d));
    };
    
    $parseAmount = function($s) {
        if ($s === null) return null;
        $s = trim((string)$s);
        if ($s === '' || $s === '-') return null;
        // Manejar puntos como separadores de miles
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return null;
        return (float)$s;
    };

    // Extraer el texto plano del HTML
    $body = $html;
    if (preg_match('|<body.*?>(.*)</body>|is', $html, $mBody)) {
        $body = $mBody[1];
    }
    
    // Reemplazar <br> por saltos de línea
    $body = preg_replace('/<\s*br\s*\/?>/i', "\n", $body);
    
    // Extraer solo el texto sin tags
    $text = strip_tags($body);
    
    // Dividir en líneas y limpiar
    $lines = array_map('trim', explode("\n", $text));
    $lines = array_filter($lines, function($line) {
        return $line !== '';
    });
    $lines = array_values($lines);
    
    $i = 0;
    $n = count($lines);
    
    // Función auxiliar para verificar si es una línea de encabezado/footer
    $isHeaderOrFooter = function($line) {
        return preg_match('/(Infórmate|Informate|©|Banco de|Todos los Derechos|www\.sbif|Saldo Disponible|Saldo Contable|Retenciones|Total Cargos|Total Abonos|Línea de Crédito|Linea de Credito|Sr\(a\)|Rut:|Cuenta N|Moneda :|Saldo al|Movimientos\s*al|^Fecha$|^Descripción|^Descripcion|^Canal|^Cargos|^Abonos|^Saldo|Canal o\s+Sucursal|Cargos \(CLP\)|Abonos \(CLP\)|Saldo \(CLP\))/i', $line);
    };
    
    // Función para verificar si parece un canal
    $isChannel = function($line) {
        $line = trim($line);
        if (strlen($line) > 30) return false;
        return preg_match('/(Huerfanos|Internet|Oficina|Of\.|Quilin|Paseo|Ser\.|Central)/i', $line);
    };
    
    while ($i < $n) {
        $line = $lines[$i];
        
        // Saltar headers y footers
        if ($isHeaderOrFooter($line)) {
            $i++;
            continue;
        }
        
        // Detectar si es una fecha (formato DD/MM/YYYY)
        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})$/', $line, $match)) {
            $fecha = $normalizeDate($match[1]);
            $i++; // Avanzar a la siguiente línea
            
            // Leer todas las transacciones de esta fecha
            while ($i < $n) {
                // Si encontramos otra fecha, detenemos
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $lines[$i])) {
                    break;
                }
                
                // Si es header/footer, saltar
                if ($isHeaderOrFooter($lines[$i])) {
                    $i++;
                    continue;
                }
                
                // Leer la descripción (debe ser texto, no número)
                $descripcion = '';
                $maybeDesc = $parseAmount($lines[$i]);
                
                // Si la línea es un número, no es una descripción válida - saltar
                if ($maybeDesc !== null) {
                    $i++;
                    continue;
                }
                
                $descripcion = $lines[$i];
                
                // Validar que la descripción tenga sentido
                if (strlen($descripcion) < 3) {
                    $i++;
                    continue;
                }
                
                $i++; // Avanzar después de la descripción
                
                // Ahora buscar: [canal?], números (1-3)
                $canal = null;
                $numbers = []; // Almacenar todos los números encontrados
                $maxLookAhead = 6;
                $linesChecked = 0;
                
                while ($i < $n && $linesChecked < $maxLookAhead) {
                    // Si encontramos otra fecha, detener
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $lines[$i])) {
                        break;
                    }
                    
                    // Si es header/footer, saltar pero no contar
                    if ($isHeaderOrFooter($lines[$i])) {
                        $i++;
                        continue;
                    }
                    
                    $nextLine = $lines[$i];
                    $maybeAmount = $parseAmount($nextLine);
                    
                    if ($maybeAmount !== null) {
                        // Es un número
                        $numbers[] = $maybeAmount;
                        
                        // Si ya tenemos 3 números, suficiente
                        if (count($numbers) >= 3) {
                            $i++;
                            break;
                        }
                    } else {
                        // Es texto
                        // Si parece un canal y no tenemos uno aún
                        if ($canal === null && $isChannel($nextLine)) {
                            $canal = $nextLine;
                        } else {
                            // Si es texto largo y ya tenemos números, probablemente es otra descripción
                            if (strlen($nextLine) > 15 && count($numbers) > 0) {
                                break;
                            }
                            // Si es texto corto, podría ser continuación de descripción o canal
                            if (strlen($nextLine) < 50 && count($numbers) == 0) {
                                if ($canal === null && $isChannel($nextLine)) {
                                    $canal = $nextLine;
                                } else {
                                    $descripcion .= ' ' . $nextLine;
                                }
                            }
                        }
                    }
                    
                    $i++;
                    $linesChecked++;
                }
                
                // Limpiar descripción
                $descripcion = trim($descripcion);
                $descripcion = preg_replace('/\s+/', ' ', $descripcion);
                
                // Validar que tengamos al menos descripción y un número
                if (empty($descripcion) || count($numbers) == 0) {
                    continue;
                }
                
                // Interpretar los números
                // Casos posibles:
                // 1 número: cargo (puede ser saldo si cargo está en 0)
                // 2 números: cargo, abono (o cargo, saldo si abono=0)
                // 3 números: cargo, abono, saldo
                
                $cargo = null;
                $abono = null;
                $saldo = null;
                
                if (count($numbers) == 1) {
                    // Solo un número - asumirlo como cargo
                    $cargo = $numbers[0];
                } elseif (count($numbers) == 2) {
                    // Dos números: primera interpretación es cargo y abono
                    // Pero si el primer número es grande (>100000), probablemente es cargo y saldo
                    $cargo = $numbers[0];
                    $abono = $numbers[1];
                } elseif (count($numbers) >= 3) {
                    // Tres números: cargo, abono, saldo
                    $cargo = $numbers[0];
                    $abono = $numbers[1];
                    $saldo = $numbers[2];
                }
                
                // Determinar monto y tipo
                $monto = 0.0;
                $tipo = 'gasto';
                
                // Si abono > 0, es ingreso
                // Si cargo > 0 y abono == 0 (o null), es gasto
                if ($abono !== null && $abono > 0) {
                    $monto = $abono;
                    $tipo = 'ingreso';
                } elseif ($cargo !== null && $cargo > 0) {
                    $monto = -1 * abs($cargo);
                    $tipo = 'gasto';
                } else {
                    // Sin monto válido
                    continue;
                }
                
                // Agregar transacción
                $rows[] = [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'canal' => $canal,
                    'monto' => $monto,
                    'tipo' => $tipo,
                    'saldo' => $saldo
                ];
            }
            
            continue;
        }
        
        $i++;
    }
    
    return $rows;
  }

  private function normalizeDate(string $d): string {
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $d, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    $ts = strtotime($d);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
  }

  private function parseAmount(string $s): ?float {
    $s = trim($s);
    if ($s === '' || $s === '-') return null;
    
    // Remover espacios
    $s = str_replace(' ', '', $s);
    
    // Si tiene punto Y coma, asumir que punto es miles y coma es decimal
    if (strpos($s, '.') !== false && strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } 
    // Si solo tiene puntos (formato chileno: 1.234.567)
    elseif (substr_count($s, '.') > 1 || (strpos($s, '.') !== false && strlen($s) - strrpos($s, '.') - 1 != 2)) {
        // Múltiples puntos o punto no en posición de decimal = separador de miles
        $s = str_replace('.', '', $s);
    }
    // Si solo tiene coma, es decimal
    elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    // Si tiene un solo punto en posición decimal (últimos 2-3 dígitos), dejarlo
    
    if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return null;
    return (float)$s;
  }

  private function existsSimilarTransaction(int $userId, string $fecha, string $descripcion, float $monto): bool {
    $sql = "SELECT COUNT(*) FROM transacciones WHERE usuario_id = :uid AND fecha = :fecha AND monto = :monto AND descripcion LIKE :desc LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $short = mb_substr($descripcion,0,40);
    $stmt->execute([':uid'=>$userId, ':fecha'=>$fecha, ':monto'=>abs($monto), ':desc'=>"%{$short}%"]);
    return (int)$stmt->fetchColumn() > 0;
  }
}