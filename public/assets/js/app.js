document.addEventListener('DOMContentLoaded', function(){
  // Theme toggle (si existe)
  const btn = document.getElementById('themeToggle');
  const root = document.documentElement;
  const saved = localStorage.getItem('fin_theme');
  if (saved) root.setAttribute('data-theme', saved);
  if (btn) btn.addEventListener('click', () => {
    const cur = root.getAttribute('data-theme') || '';
    const next = cur === 'dark' ? '' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('fin_theme', next);
    btn.innerHTML = next === 'dark' ? '<i class=\"fa-solid fa-sun\"></i> Claro' : '<i class=\"fa-solid fa-moon\"></i> Oscuro';
  });

  // Client-side bootstrap validation
  (function(){
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form){
      form.addEventListener('submit', function(event){
        if (!form.checkValidity()) {
          event.preventDefault(); event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();

  // === AJAX edit modal for transactions ===
  // create modal markup dynamically
  const modalContainer = document.createElement('div');
  modalContainer.innerHTML = `
  <div class="modal fade" id="modalEditTrx" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
    <form id="formEditTrx" class="modal-content needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="${document.querySelector('input[name=csrf_token]') ? document.querySelector('input[name=csrf_token]').value : ''}">
      <input type="hidden" name="id" value="">
      <div class="modal-header"><h5 class="modal-title">Editar transacción</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6 form-floating"><input type="number" name="monto" step="0.01" min="0.01" class="form-control" placeholder="Monto" required><label>Monto</label><div class="invalid-feedback">Ingresa un monto.</div></div>
          <div class="col-6 form-floating"><input type="date" name="fecha" class="form-control" placeholder="Fecha" required><label>Fecha</label><div class="invalid-feedback">Ingresa una fecha.</div></div>
          <div class="col-6 form-floating"><select name="tipo" class="form-select" required><option value="gasto">Gasto</option><option value="ingreso">Ingreso</option></select><label>Tipo</label></div>
          <div class="col-6 form-floating"><select name="categoria_id" class="form-select"><option value="">Sin categoría</option></select><label>Categoría</label></div>
          <div class="col-12 form-floating"><input type="text" name="descripcion" class="form-control" placeholder="Descripción"><label>Descripción</label></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div></div>`;
  document.body.appendChild(modalContainer);

  const modalEl = document.getElementById('modalEditTrx');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('formEditTrx');

  // click handler to open modal (buttons with data-edit-id)
  document.body.addEventListener('click', function(e){
    const btn = e.target.closest('[data-edit-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-edit-id');
    if (!id) return;
    fetch(`?r=transaction/ajaxGet&id=${id}`, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        if (json.error) { alert(json.error); return; }
        const d = json.data;
        const f = form;
        f.querySelector('input[name=id]').value = d.id;
        f.querySelector('input[name=monto]').value = d.monto;
        f.querySelector('input[name=fecha]').value = d.fecha;
        f.querySelector('select[name=tipo]').value = d.tipo;
        f.querySelector('input[name=descripcion]').value = d.descripcion ?? '';
        // fill categories select (clone from page select if exists)
        const selectPage = document.querySelector('select[name="categoria_id"]');
        const selectModal = f.querySelector('select[name=categoria_id]');
        selectModal.innerHTML = '<option value="">Sin categoría</option>';
        if (selectPage) {
          Array.from(selectPage.querySelectorAll('option')).forEach(opt => {
            const newOpt = document.createElement('option');
            newOpt.value = opt.value;
            newOpt.text = opt.text;
            if (opt.value === (d.categoria_id ? String(d.categoria_id) : '')) newOpt.selected = true;
            selectModal.appendChild(newOpt);
          });
        }
        modal.show();
      })
      .catch(err => { console.error(err); alert('Error al cargar datos'); });
  });

  // submit AJAX update
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = new FormData(form);
    if (!data.get('csrf_token')) {
      const globalCsrf = document.querySelector('input[name=csrf_token]');
      if (globalCsrf) data.set('csrf_token', globalCsrf.value);
    }
    fetch('?r=transaction/ajaxUpdate', {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(json => {
      if (json.error) { alert(json.error); return; }
      // actualizar fila en la tabla si existe
      const row = document.querySelector('button[data-edit-id="'+json.data.id+'"]')?.closest('tr');
      if (row) {
        row.querySelector('td:nth-child(1)').textContent = json.data.fecha;
        row.querySelector('td:nth-child(2)').textContent = json.data.descripcion || '—';
        // categoria_nombre puede no estar presente si join no hecho; usar categoria_id to infer text from page select
        const pageOpt = document.querySelector('select[name="categoria_id"] option[value="'+(json.data.categoria_id ?? '')+'"]');
        row.querySelector('td:nth-child(3)').textContent = pageOpt ? pageOpt.textContent : (json.data.categoria_nombre ?? 'Sin categoría');
        row.querySelector('td:nth-child(4)').innerHTML = json.data.tipo === 'ingreso' ? '<span class=\"badge badge-success\">ingreso</span>' : '<span class=\"badge badge-danger\">gasto</span>';
        row.querySelector('td:nth-child(5)').textContent = Number(json.data.monto).toLocaleString('es-CL', {minimumFractionDigits:2, maximumFractionDigits:2});
      }
      modal.hide();
      const div = document.createElement('div');
      div.className = 'alert alert-success fade-in';
      div.textContent = 'Transacción actualizada';
      document.querySelector('.app-main').insertAdjacentElement('afterbegin', div);
      setTimeout(()=>div.remove(), 3000);
    })
    .catch(err => { console.error(err); alert('Error al guardar cambios'); });
  });
});
