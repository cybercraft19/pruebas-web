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

  const quickIcons = document.querySelectorAll('.test-card__icon');
  quickIcons[0].innerHTML = Icons.fileText;
  quickIcons[1].innerHTML = Icons.stopwatch;
  quickIcons[2].innerHTML = Icons.users;

  async function cargarResumen() {
    const [pruebas, estudiantes] = await Promise.all([
      Api.get('/api/pruebas'),
      Api.get('/api/estudiantes'),
    ]);

    document.getElementById('stat-total').textContent = pruebas.length;
    document.getElementById('stat-publicadas').textContent = pruebas.filter((p) => p.estado === 'publicada').length;
    document.getElementById('stat-estudiantes').textContent = estudiantes.length;
  }

  cargarResumen();
})();
