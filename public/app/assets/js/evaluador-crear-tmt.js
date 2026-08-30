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

  document.getElementById('tmt-submit').innerHTML = `${Icons.plus} Crear prueba TMT`;

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
      successBox.innerHTML = `Prueba TMT "${esc(prueba.titulo)}" creada correctamente como borrador. <a class="link" href="/app/evaluador/mis-pruebas.html">Ver mis pruebas</a>`;
      e.target.reset();
    } catch (err) {
      errorBox.textContent = formatError(err);
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
