<?php if (isset($execOk) && !$execOk && isset($hints)): ?>
  <div class="alert alert-warning">
    <strong>Atención:</strong> la conversión automática de PDF no está disponible en este servidor.
    <div class="mt-2">
      <strong>Instrucciones rápidas:</strong>
      <div class="small text-muted">
        <strong>Windows</strong>: <?= implode(' ', $hints['windows']['steps']) ?><br>
        <strong>Linux (Debian/Ubuntu)</strong>: <?= implode(' ', $hints['linux']['steps']) ?>
      </div>
    </div>
    <div class="mt-2 small">Alternativa rápida: convierte el PDF a HTML en tu PC con pdftohtml y pega el HTML en el textarea.</div>
  </div>
<?php endif; ?>

<?php
// Variables: csrf, flash
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Importar extracto bancario</h4>
  <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('?r=transaction/index') ?>">Volver a transacciones</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <?php if (!empty($flash)): ?><div class="alert alert-info"><?= $this->e($flash['message'] ?? '') ?></div><?php endif; ?>

    <form method="post" action="<?= base_url('?r=import/preview') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf) ?>">

      <div class="mb-3">
        <label class="form-label">Pegar HTML del PDF (recomendado)</label>
        <textarea name="html" rows="8" class="form-control" placeholder="Pega aquí el HTML extraído del PDF"></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">O subir archivo (pdf o html)</label>
        <input type="file" name="file" class="form-control">
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary">Previsualizar</button>
        <a class="btn btn-link" href="<?= base_url('?r=import/index') ?>">Limpiar</a>
      </div>
    </form>

    <hr>
    <div class="small text-muted">
      Si el servidor no tiene pdftohtml/pdftotext, exporta el PDF a HTML localmente y pega el HTML aquí.
    </div>
  </div>
</div>
