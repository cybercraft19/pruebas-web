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

  document.getElementById('estudiante-submit').innerHTML = `${Icons.plus} Crear estudiante`;

  const exportarEstudiantesBtn = document.getElementById('exportar-estudiantes-btn');
  exportarEstudiantesBtn.innerHTML = `${Icons.download} Exportar Excel`;
  exportarEstudiantesBtn.addEventListener('click', () => {
    Api.download('/api/estudiantes/exportar', 'estudiantes.xlsx');
  });

  async function cargarEstudiantes() {
    const estudiantes = await Api.get('/api/estudiantes');
    const body = document.getElementById('estudiantes-body');

    document.getElementById('estudiantes-count').textContent = `${estudiantes.length} estudiante${estudiantes.length === 1 ? '' : 's'}`;

    if (estudiantes.length === 0) {
      body.innerHTML = `<tr><td colspan="3"><div class="empty-state">${Icons.inbox}<p>Aún no has creado estudiantes.</p></div></td></tr>`;
      return;
    }

    body.innerHTML = estudiantes.map((e) => `
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar" style="width:28px;height:28px;font-size:0.7rem">${esc(e.name.charAt(0).toUpperCase())}</div>
            ${esc(e.name)}
          </div>
        </td>
        <td>${esc(e.email)}</td>
        <td style="text-align:right;white-space:nowrap">
          <button type="button" class="icon-btn" style="margin-top:0" title="Restablecer contraseña" data-resetear="${e.id}" data-nombre="${esc(e.name)}">${Icons.key}</button>
          <button type="button" class="icon-btn" style="margin-top:0" title="Eliminar estudiante" data-eliminar="${e.id}" data-nombre="${esc(e.name)}">${Icons.trash}</button>
        </td>
      </tr>
    `).join('');

    body.querySelectorAll('[data-resetear]').forEach((btn) => {
      btn.addEventListener('click', async () => {
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
    });

    body.querySelectorAll('[data-eliminar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar a ${btn.dataset.nombre}? Se perderán también sus intentos y resultados.`)) return;
        try {
          await Api.delete(`/api/estudiantes/${btn.dataset.eliminar}`);
          cargarEstudiantes();
        } catch (err) {
          alert(formatError(err));
        }
      });
    });
  }

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

  cargarEstudiantes();
})();
