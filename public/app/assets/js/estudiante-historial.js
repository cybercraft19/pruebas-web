(async () => {
  const user = await requireSession('estudiante');
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

  function emptyState(mensaje) {
    return `<div class="empty-state">${Icons.inbox}<p>${mensaje}</p></div>`;
  }

  async function cargarHistorial() {
    const intentos = await Api.get('/api/mis-intentos');
    const body = document.getElementById('historial-body');

    if (intentos.length === 0) {
      body.innerHTML = `<tr><td colspan="3">${emptyState('Aún no has presentado ninguna prueba.')}</td></tr>`;
      return;
    }

    body.innerHTML = intentos.map((i) => {
      const destino = i.prueba.tipo === 'tmt' ? 'tmt' : 'prueba';
      return `
      <tr>
        <td>${esc(i.prueba.titulo)}</td>
        <td><span class="badge ${i.estado === 'finalizado' ? 'publicada' : 'borrador'}">${i.estado === 'finalizado' ? 'Finalizado' : 'En progreso'}</span></td>
        <td><a class="link" href="/app/estudiante/${destino}.html?intento=${i.id}">${i.estado === 'finalizado' ? 'Ver resultado' : 'Continuar'}</a></td>
      </tr>
    `;
    }).join('');
  }

  cargarHistorial();
})();
