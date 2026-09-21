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

  const form = document.getElementById('estudiante-form');
  const submitBtn = document.getElementById('estudiante-submit');
  const errorBox = document.getElementById('estudiante-error');
  const formTitle = document.getElementById('estudiante-form-title');
  const formSubtitle = document.getElementById('estudiante-form-subtitle');
  const passwordWrap = document.getElementById('est-password-wrap');
  const cancelarLink = document.getElementById('cancelar-edicion-link');

  submitBtn.innerHTML = `${Icons.plus} Crear estudiante`;

  const exportarEstudiantesBtn = document.getElementById('exportar-estudiantes-btn');
  exportarEstudiantesBtn.innerHTML = `${Icons.download} Exportar Excel`;
  exportarEstudiantesBtn.addEventListener('click', () => {
    Api.download('/api/estudiantes/exportar', 'estudiantes.xlsx');
  });

  let estudiantesPorId = {};
  let editandoId = null;

  function entrarModoEdicion(estudiante) {
    editandoId = estudiante.id;
    formTitle.textContent = `Editar a ${estudiante.name}`;
    formSubtitle.textContent = 'Corregí los datos y guardá los cambios. La contraseña se cambia aparte, con el ícono de llave.';
    passwordWrap.style.display = 'none';
    cancelarLink.style.display = 'inline';
    submitBtn.innerHTML = `${Icons.checkCircle} Guardar cambios`;

    document.getElementById('est-name').value = estudiante.name;
    document.getElementById('est-email').value = estudiante.email;
    document.getElementById('est-cedula').value = estudiante.cedula || '';
    document.getElementById('est-telefono').value = estudiante.telefono || '';
    document.getElementById('est-fecha-nacimiento').value = estudiante.fecha_nacimiento ? estudiante.fecha_nacimiento.slice(0, 10) : '';
    document.getElementById('est-acudiente-nombre').value = estudiante.acudiente_nombre || '';
    document.getElementById('est-acudiente-telefono').value = estudiante.acudiente_telefono || '';

    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function salirModoEdicion() {
    editandoId = null;
    formTitle.textContent = 'Crear estudiante';
    formSubtitle.textContent = 'Crea cuentas de acceso para que tus estudiantes puedan presentar pruebas.';
    passwordWrap.style.display = '';
    cancelarLink.style.display = 'none';
    submitBtn.innerHTML = `${Icons.plus} Crear estudiante`;
    form.reset();
    errorBox.textContent = '';
  }

  cancelarLink.addEventListener('click', (e) => {
    e.preventDefault();
    salirModoEdicion();
  });

  function emptyState(mensaje) {
    return `<div class="empty-state" style="grid-column:1/-1">${Icons.inbox}<p>${mensaje}</p></div>`;
  }

  async function cargarEstudiantes() {
    const estudiantes = await Api.get('/api/estudiantes');
    estudiantesPorId = {};
    estudiantes.forEach((e) => { estudiantesPorId[e.id] = e; });

    document.getElementById('estudiantes-count').textContent = `${estudiantes.length} estudiante${estudiantes.length === 1 ? '' : 's'}`;

    const rows = estudiantes.map((e) => [
      `<div style="display:flex;align-items:center;gap:10px">
        <div class="avatar" style="width:28px;height:28px;font-size:0.7rem">${esc(e.name.charAt(0).toUpperCase())}</div>
        ${esc(e.name)}
      </div>`,
      esc(e.email),
      e.cedula ? esc(e.cedula) : '—',
      e.telefono ? esc(e.telefono) : '—',
      e.acudiente_nombre ? `${esc(e.acudiente_nombre)}${e.acudiente_telefono ? ` (${esc(e.acudiente_telefono)})` : ''}` : '—',
      `<div style="text-align:right;white-space:nowrap">
        <button type="button" class="icon-btn" style="margin-top:0" title="Editar" data-editar="${e.id}">${Icons.pencil}</button>
        <button type="button" class="icon-btn" style="margin-top:0" title="Restablecer contraseña" data-resetear="${e.id}" data-nombre="${esc(e.name)}">${Icons.key}</button>
        <button type="button" class="icon-btn" style="margin-top:0" title="Eliminar estudiante" data-eliminar="${e.id}" data-nombre="${esc(e.name)}">${Icons.trash}</button>
      </div>`,
    ]);

    if ($.fn.dataTable.isDataTable('#estudiantes-table')) {
      $('#estudiantes-table').DataTable().destroy();
    }

    $('#estudiantes-table').DataTable({
      data: rows,
      language: { emptyTable: emptyState('Aún no has creado estudiantes.') },
    });
  }

  $(document).on('click', '#estudiantes-table [data-editar]', (e) => {
    const estudiante = estudiantesPorId[e.currentTarget.dataset.editar];
    if (estudiante) entrarModoEdicion(estudiante);
  });

  $(document).on('click', '#estudiantes-table [data-resetear]', async (e) => {
    const btn = e.currentTarget;
    const nueva = prompt(`Nueva contraseña para ${btn.dataset.nombre} (mínimo 8 caracteres):`);
    if (!nueva) return;
    if (nueva.length < 8) {
      alert('La contraseña debe tener al menos 8 caracteres.');
      return;
    }
    try {
      await Api.post(`/api/estudiantes/${btn.dataset.resetear}/reset-password`, { password: nueva });
      alert(`Contraseña de ${btn.dataset.nombre} actualizada.`);
    } catch (err) {
      alert(formatError(err));
    }
  });

  $(document).on('click', '#estudiantes-table [data-eliminar]', async (e) => {
    const btn = e.currentTarget;
    if (!confirm(`¿Eliminar a ${btn.dataset.nombre}? Se perderán también sus intentos y resultados.`)) return;
    try {
      await Api.delete(`/api/estudiantes/${btn.dataset.eliminar}`);
      if (editandoId === Number(btn.dataset.eliminar)) salirModoEdicion();
      cargarEstudiantes();
    } catch (err) {
      alert(formatError(err));
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.textContent = '';

    const datos = {
      name: document.getElementById('est-name').value,
      email: document.getElementById('est-email').value,
      cedula: document.getElementById('est-cedula').value || null,
      telefono: document.getElementById('est-telefono').value || null,
      fecha_nacimiento: document.getElementById('est-fecha-nacimiento').value || null,
      acudiente_nombre: document.getElementById('est-acudiente-nombre').value || null,
      acudiente_telefono: document.getElementById('est-acudiente-telefono').value || null,
    };

    submitBtn.disabled = true;
    try {
      if (editandoId) {
        await Api.put(`/api/estudiantes/${editandoId}`, datos);
        salirModoEdicion();
      } else {
        datos.password = document.getElementById('est-password').value;
        if (!datos.password || datos.password.length < 8) {
          throw { payload: { errors: { password: ['La contraseña debe tener al menos 8 caracteres.'] } } };
        }
        await Api.post('/api/estudiantes', datos);
        form.reset();
      }
      cargarEstudiantes();
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });

  cargarEstudiantes();
})();
