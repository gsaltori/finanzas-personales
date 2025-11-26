<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Historial de cambios</h4>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead class="table-light"><tr><th>Fecha</th><th>Entidad</th><th>Id</th><th>Acción</th><th>Usuario</th><th>Antes</th><th>Después</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $this->e($r['creado_en']) ?></td>
              <td><?= $this->e($r['entidad']) ?></td>
              <td><?= (int)$r['entidad_id'] ?></td>
              <td><?= $this->e($r['accion']) ?></td>
              <td><?= $this->e($r['usuario_id']) ?></td>
              <td><pre style="max-width:200px;white-space:pre-wrap;font-size:.78rem;"><?= $this->e($r['antes']) ?></pre></td>
              <td><pre style="max-width:200px;white-space:pre-wrap;font-size:.78rem;"><?= $this->e($r['despues']) ?></pre></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
