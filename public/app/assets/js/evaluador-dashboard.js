(async () => {
  const user = await requireSession('evaluador');
  if (!user) return;
  document.getElementById('user-name').textContent = `${user.name} (evaluador)`;

  document.getElementById('logout-btn').addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  document.getElementById('descargar-plantilla').addEventListener('click', () => {
    Api.download('/api/pruebas/plantilla', 'plantilla-prueba.xlsx');
  });

  async function cargarPruebas() {
    const pruebas = await Api.get('/api/pruebas');
    const body = document.getElementById('pruebas-body');

    if (pruebas.length === 0) {
      body.innerHTML = '<tr><td colspan="5" class="muted">Aún no has importado ninguna prueba.</td></tr>';
      return;
    }

    body.innerHTML = pruebas.map((p) => `
      <tr>
        <td>${p.titulo}</td>
        <td><span class="badge ${p.estado}">${p.estado}</span></td>
        <td>${p.preguntas_count}</td>
        <td>${p.categorias_count}</td>
        <td><a class="link" href="/app/evaluador/prueba.html?id=${p.id}">Ver / gestionar</a></td>
      </tr>
    `).join('');
  }

  async function cargarEstudiantes() {
    const estudiantes = await Api.get('/api/estudiantes');
    const body = document.getElementById('estudiantes-body');

    if (estudiantes.length === 0) {
      body.innerHTML = '<tr><td colspan="2" class="muted">Aún no has creado estudiantes.</td></tr>';
      return;
    }

    body.innerHTML = estudiantes.map((e) => `<tr><td>${e.name}</td><td>${e.email}</td></tr>`).join('');
  }

  document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorBox = document.getElementById('import-error');
    const successBox = document.getElementById('import-success');
    errorBox.textContent = '';
    successBox.textContent = '';

    const formData = new FormData();
    formData.append('archivo', document.getElementById('archivo').files[0]);
    const pdf = document.getElementById('pdf_referencia').files[0];
    if (pdf) formData.append('pdf_referencia', pdf);

    try {
      const prueba = await Api.postForm('/api/pruebas/importar', formData);
      successBox.textContent = `Prueba "${prueba.titulo}" importada correctamente como borrador.`;
      e.target.reset();
      cargarPruebas();
    } catch (err) {
      errorBox.textContent = formatError(err);
    }
  });

  document.getElementById('estudiante-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorBox = document.getElementById('estudiante-error');
    errorBox.textContent = '';

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
    }
  });

  cargarPruebas();
  cargarEstudiantes();
})();
