<?php
// app/Views/import/view.php
// Variables esperadas: $job (assoc), $rows (array), $meta (array), $csrf
$meta = $meta ?? [];
?>
<h2>Import job #<?= htmlspecialchars($job['id']) ?></h2>
<p>Estado: <strong><?= htmlspecialchars($job['estado']) ?></strong></p>
<p>Filas total: <?= (int)$job['filas_total'] ?> — Importadas: <?= (int)$job['filas_importadas'] ?></p>

<?php if (!empty($meta['conversion_log'])): ?>
  <h3>Conversion log</h3>
  <pre style="background:#f6f6f6;border:1px solid #ddd;padding:8px;"><?= htmlspecialchars(implode("\n", $meta['conversion_log'])) ?></pre>
<?php endif; ?>

<?php if (!empty($meta['sample_content_base64'])): ?>
  <h3>Sample content (primeros 40000 chars)</h3>
  <?php $sample = base64_decode($meta['sample_content_base64']); ?>
  <pre style="background:#111;color:#dcdcdc;padding:8px;overflow:auto;max-height:360px;"><?= htmlspecialchars(mb_substr($sample,0,40000)) ?></pre>
<?php endif; ?>

<h3>Filas del job</h3>
<?php if (empty($rows)): ?>
  <p>No hay filas para este job.</p>
<?php else: ?>
  <table border="1" cellpadding="6">
    <thead><tr><th>#</th><th>fecha</th><th>descripcion</th><th>monto</th><th>tipo</th><th>estado</th><th>detalle</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['fecha'] ?? '') ?></td>
          <td><?= htmlspecialchars(mb_substr($r['descripcion'] ?? '',0,140)) ?></td>
          <td><?= htmlspecialchars((string)($r['monto'] ?? '')) ?></td>
          <td><?= htmlspecialchars($r['tipo'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['estado'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['detalle'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
