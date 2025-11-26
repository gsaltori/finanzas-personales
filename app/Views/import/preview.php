<?php if (!empty($conversionLog) && is_array($conversionLog)): ?>
  <div class="alert alert-secondary">
    <strong>Conversion log:</strong>
    <ul class="mb-0">
      <?php foreach ($conversionLog as $cl): ?>
        <li><?= htmlspecialchars($cl) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php
// Variables: csrf, jobId, rows (array), jobNote
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Previsualización de movimientos (Job #<?= (int)$jobId ?>)</h4>
  <div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('?r=import/index') ?>">Volver</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= base_url('?r=import/jobs') ?>">Ver Jobs</a>
  </div>
</div>

<?php if (!empty($jobNote)): ?>
  <div class="alert alert-warning"><?= $this->e($jobNote) ?></div>
<?php endif; ?>

<form method="post" action="<?= base_url('?r=import/run') ?>">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">
  <input type="hidden" name="job_id" value="<?= (int)$jobId ?>">

  <div class="card mb-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:42px"><input type="checkbox" id="selectAll"></th>
              <th>Fecha</th>
              <th>Descripción</th>
              <th>Canal</th>
              <th class="text-end">Monto</th>
              <th>Tipo</th>
              <th class="text-end">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-center py-4 small text-muted">No se detectaron movimientos.</td></tr>
            <?php else: foreach ($rows as $r):
              $fh = hash('sha256', ($r['fecha'] ?? '') . '|' . mb_substr($r['descripcion'] ?? '',0,120) . '|' . number_format((float)$r['monto'],2,'.',''));
              $m = (float)$r['monto'];
              ?>
              <tr>
                <td><input type="checkbox" name="select[]" value="<?= $this->e($fh) ?>" checked></td>
                <td><?= $this->e($r['fecha'] ?? '') ?></td>
                <td><?= $this->e($r['descripcion'] ?? '') ?></td>
                <td><?= $this->e($r['canal'] ?? '') ?></td>
                <td class="text-end"><?= number_format(abs($m),2,',','.') ?></td>
                <td><?= $this->e($r['tipo'] ?? '') ?></td>
                <td class="text-end"><?= isset($r['saldo']) ? number_format((float)$r['saldo'],2,',','.') : '' ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-success">Importar seleccionados</button>
    <a class="btn btn-link" href="<?= base_url('?r=import/index') ?>">Cancelar</a>
  </div>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function(){
  const checked = this.checked;
  document.querySelectorAll('input[name="select[]"]').forEach(cb => cb.checked = checked);
});
</script>
