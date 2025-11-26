<div class="row g-3">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="m-0">Dashboard</h3>
      <small class="text-muted">Resumen mes <?= $mes ?>/<?= $anio ?></small>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="kpi">
        <div>
          <div class="value"><?= number_format($summary['balance'],2,',','.') ?></div>
          <div class="label">Balance</div>
        </div>
        <div class="ms-auto text-end">
          <i class="fa-solid fa-wallet fa-2x text-primary"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="kpi">
        <div>
          <div class="value"><?= number_format($summary['ingresos'],2,',','.') ?></div>
          <div class="label">Ingresos</div>
        </div>
        <div class="ms-auto text-end">
          <i class="fa-solid fa-sack-dollar fa-2x text-success"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="kpi">
        <div>
          <div class="value text-danger"><?= number_format($summary['gastos'],2,',','.') ?></div>
          <div class="label">Gastos</div>
        </div>
        <div class="ms-auto text-end">
          <i class="fa-solid fa-credit-card fa-2x text-danger"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="kpi">
        <div>
          <div class="value"><?= count($categories) ?></div>
          <div class="label">Categorías</div>
        </div>
        <div class="ms-auto text-end">
          <i class="fa-solid fa-tags fa-2x text-muted"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart card below (reuse previous canvas) -->
<div class="card mt-4">
  <div class="card-body">
    <h5 class="card-title">Gastos por categoría</h5>
    <canvas id="chartCategories" height="120"></canvas>
  </div>
</div>

<script>
  (function(){
    const data = <?= json_encode(array_column($byCategory, 'total')) ?>;
    const labels = <?= json_encode(array_column($byCategory, 'categoria')) ?>;
    const ctx = document.getElementById('chartCategories');
    if (ctx) {
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets:[{
            data,
            backgroundColor: ['#2563eb','#10b981','#f59e0b','#ef4444','#6f42c1','#06b6d4'],
            borderWidth:1
          }]
        },
        options:{
          plugins:{legend:{position:'bottom'}},
          responsive:true,
          maintainAspectRatio:false
        }
      });
    }
  })();
</script>
