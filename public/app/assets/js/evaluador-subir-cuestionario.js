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

  const descargarBtn = document.getElementById('descargar-plantilla');
  descargarBtn.innerHTML = `${Icons.download} Descargar plantilla Excel`;
  descargarBtn.addEventListener('click', () => {
    Api.download('/api/pruebas/plantilla', 'plantilla-prueba.xlsx');
  });

  document.getElementById('import-submit').innerHTML = `${Icons.plus} Importar prueba`;

  initDropzone('archivo', Icons.fileText);
  initDropzone('pdf_referencia', Icons.fileText);

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
      successBox.innerHTML = `Prueba "${esc(prueba.titulo)}" importada correctamente como borrador. <a class="link" href="/app/evaluador/mis-pruebas.html">Ver mis pruebas</a>`;
      e.target.reset();
      resetDropzone('archivo');
      resetDropzone('pdf_referencia');
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
