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

  async function cargarDisponibles() {
    const pruebas = await Api.get('/api/pruebas-publicadas');
    const grid = document.getElementById('disponibles-grid');

    if (pruebas.length === 0) {
      grid.innerHTML = emptyState('No hay pruebas disponibles por ahora.');
      return;
    }

    grid.innerHTML = pruebas.map((p) => {
      const esTmt = p.tipo === 'tmt';
      return `
      <div class="test-card">
        <div class="test-card__top">
          <div class="test-card__icon ${esTmt ? 'tmt' : ''}">${esTmt ? Icons.stopwatch : Icons.fileText}</div>
          <span class="badge info">${esTmt ? 'TMT' : 'Cuestionario'}</span>
        </div>
        <h3>${esc(p.titulo)}</h3>
        <div class="test-card__meta">
          <span>${Icons.fileText} ${esTmt ? 'Lienzo interactivo' : `${p.preguntas_count} preguntas`}</span>
          ${p.tiempo_max_minutos ? `<span>${Icons.stopwatch} ${p.tiempo_max_minutos} min</span>` : ''}
        </div>
        <button class="block" data-prueba-id="${p.id}" data-tipo="${p.tipo}">${Icons.play} Iniciar</button>
      </div>
    `;
    }).join('');

    grid.querySelectorAll('button[data-prueba-id]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Iniciando…';
        const intento = await Api.post('/api/intentos', { prueba_id: Number(btn.dataset.pruebaId) });
        const destino = btn.dataset.tipo === 'tmt' ? 'tmt' : 'prueba';
        window.location.href = `/app/estudiante/${destino}.html?intento=${intento.id}`;
      });
    });
  }

  cargarDisponibles();
})();
