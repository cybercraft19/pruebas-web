(async () => {
  const user = await requireSession('evaluador');
  if (!user) return;

  initSidebar();
  document.getElementById('user-name').textContent = user.name;
  document.getElementById('user-avatar').textContent = user.name.charAt(0).toUpperCase();

  const logoutBtn = document.getElementById('logout-btn');
  logoutBtn.innerHTML = Icons.logout;
  logoutBtn.addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  document.querySelectorAll('.stat-card__icon')[0].innerHTML = Icons.fileText;
  document.querySelectorAll('.stat-card__icon')[1].innerHTML = Icons.checkCircle;
  document.querySelectorAll('.stat-card__icon')[2].innerHTML = Icons.users;

  const descargarBtn = document.getElementById('descargar-plantilla');
  descargarBtn.innerHTML = `${Icons.download} Descargar plantilla Excel`;
  descargarBtn.addEventListener('click', () => {
    Api.download('/api/pruebas/plantilla', 'plantilla-prueba.xlsx');
  });

  document.getElementById('import-submit').innerHTML = `${Icons.plus} Importar prueba`;
  document.getElementById('tmt-submit').innerHTML = `${Icons.plus} Crear prueba TMT`;
  document.getElementById('estudiante-submit').innerHTML = `${Icons.plus} Crear estudiante`;

  const exportarEstudiantesBtn = document.getElementById('exportar-estudiantes-btn');
  exportarEstudiantesBtn.innerHTML = `${Icons.download} Exportar Excel`;
  exportarEstudiantesBtn.addEventListener('click', () => {
    Api.download('/api/estudiantes/exportar', 'estudiantes.xlsx');
  });

  function initDropzone(inputId, iconoSvg) {
    const input = document.getElementById(inputId);
    const zone = document.getElementById(`dropzone-${inputId}`);
    const icono = document.getElementById(`dropzone-${inputId}-icon`);
    const nombre = document.getElementById(`dropzone-${inputId}-filename`);
    if (!input || !zone) return;

    icono.innerHTML = iconoSvg;

    function actualizar() {
      const archivo = input.files[0];
      zone.classList.toggle('has-file', !!archivo);
      nombre.textContent = archivo ? archivo.name : '';
    }

    input.addEventListener('change', actualizar);

    ['dragenter', 'dragover'].forEach((evento) => {
      zone.addEventListener(evento, (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach((evento) => {
      zone.addEventListener(evento, (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
      });
    });

    zone.addEventListener('drop', (e) => {
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        actualizar();
      }
    });
  }

  function resetDropzone(inputId) {
    document.getElementById(`dropzone-${inputId}`)?.classList.remove('has-file');
    const nombre = document.getElementById(`dropzone-${inputId}-filename`);
    if (nombre) nombre.textContent = '';
  }

  initDropzone('archivo', Icons.fileText);
  initDropzone('pdf_referencia', Icons.fileText);

  function emptyState(mensaje) {
    return `<div class="empty-state" style="grid-column:1/-1">${Icons.inbox}<p>${mensaje}</p></div>`;
  }

  async function cargarPruebas() {
    const pruebas = await Api.get('/api/pruebas');
    const grid = document.getElementById('pruebas-grid');

    document.getElementById('stat-total').textContent = pruebas.length;
    document.getElementById('stat-publicadas').textContent = pruebas.filter((p) => p.estado === 'publicada').length;

    if (pruebas.length === 0) {
      grid.innerHTML = emptyState('Aún no has creado ninguna prueba.');
      return;
    }

    grid.innerHTML = pruebas.map((p) => `
      <div class="test-card">
        <div class="test-card__top">
          <div class="test-card__icon ${p.tipo === 'tmt' ? 'tmt' : ''}">${p.tipo === 'tmt' ? Icons.stopwatch : Icons.fileText}</div>
          <span class="badge ${p.estado}">${p.estado}</span>
        </div>
        <h3>${p.titulo}</h3>
        <div class="test-card__meta">
          ${p.tipo === 'tmt'
            ? `<span>${Icons.grid} Lienzo interactivo</span>`
            : `<span>${Icons.fileText} ${p.preguntas_count} preguntas</span><span>${Icons.grid} ${p.categorias_count} categorías</span>`}
        </div>
        <div class="flex-between" style="margin-top:auto">
          <a class="link" href="/app/evaluador/prueba.html?id=${p.id}">Ver / gestionar</a>
          ${p.estado !== 'archivada' ? `<a class="link" href="#" data-archivar="${p.id}" style="color:var(--text-faint)">Archivar</a>` : ''}
        </div>
      </div>
    `).join('');

    grid.querySelectorAll('[data-archivar]').forEach((link) => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        await Api.post(`/api/pruebas/${link.dataset.archivar}/archivar`);
        cargarPruebas();
      });
    });
  }

  async function cargarEstudiantes() {
    const estudiantes = await Api.get('/api/estudiantes');
    const body = document.getElementById('estudiantes-body');

    document.getElementById('stat-estudiantes').textContent = estudiantes.length;
    document.getElementById('estudiantes-count').textContent = `${estudiantes.length} estudiante${estudiantes.length === 1 ? '' : 's'}`;

    if (estudiantes.length === 0) {
      body.innerHTML = `<tr><td colspan="3"><div class="empty-state">${Icons.inbox}<p>Aún no has creado estudiantes.</p></div></td></tr>`;
      return;
    }

    body.innerHTML = estudiantes.map((e) => `
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar" style="width:28px;height:28px;font-size:0.7rem">${e.name.charAt(0).toUpperCase()}</div>
            ${e.name}
          </div>
        </td>
        <td>${e.email}</td>
        <td style="text-align:right">
          <button type="button" class="icon-btn" style="margin-top:0" title="Eliminar estudiante" data-eliminar="${e.id}" data-nombre="${e.name}">${Icons.trash}</button>
        </td>
      </tr>
    `).join('');

    body.querySelectorAll('[data-eliminar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar a ${btn.dataset.nombre}? Se perderán también sus intentos y resultados.`)) return;
        await Api.delete(`/api/estudiantes/${btn.dataset.eliminar}`);
        cargarEstudiantes();
      });
    });
  }

  document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorBox = document.getElementById('import-error');
    const successBox = document.getElementById('import-success');
    const submitBtn = document.getElementById('import-submit');
    errorBox.textContent = '';
    successBox.textContent = '';

    const formData = new FormData();
    formData.append('archivo', document.getElementById('archivo').files[0]);
    const pdf = document.getElementById('pdf_referencia').files[0];
    if (pdf) formData.append('pdf_referencia', pdf);

    submitBtn.disabled = true;
    try {
      const prueba = await Api.postForm('/api/pruebas/importar', formData);
      successBox.textContent = `Prueba "${prueba.titulo}" importada correctamente como borrador.`;
      e.target.reset();
      resetDropzone('archivo');
      resetDropzone('pdf_referencia');
      cargarPruebas();
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });

  document.getElementById('tmt-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorBox = document.getElementById('tmt-error');
    const successBox = document.getElementById('tmt-success');
    const submitBtn = document.getElementById('tmt-submit');
    errorBox.textContent = '';
    successBox.textContent = '';

    submitBtn.disabled = true;
    try {
      const prueba = await Api.post('/api/pruebas/tmt', {
        titulo: document.getElementById('tmt-titulo').value,
        instrucciones: document.getElementById('tmt-instrucciones').value || null,
      });
      successBox.textContent = `Prueba TMT "${prueba.titulo}" creada correctamente como borrador.`;
      e.target.reset();
      cargarPruebas();
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });

  document.getElementById('estudiante-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorBox = document.getElementById('estudiante-error');
    const submitBtn = document.getElementById('estudiante-submit');
    errorBox.textContent = '';

    submitBtn.disabled = true;
    try {
      await Api.post('/api/estudiantes', {
        name: document.getElementById('est-name').value,
        email: document.getElementById('est-email').value,
        password: document.getElementById('est-password').value,
      });
      e.target.reset();
      cargarEstudiantes();
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });

  cargarPruebas();
  cargarEstudiantes();
})();
