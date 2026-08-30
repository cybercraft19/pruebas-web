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

  document.getElementById('nueva-prueba-link').innerHTML = `${Icons.plus} Nueva prueba`;

  function emptyState(mensaje) {
    return `<div class="empty-state" style="grid-column:1/-1">${Icons.inbox}<p>${mensaje}</p></div>`;
  }

  async function cargarPruebas() {
    const pruebas = await Api.get('/api/pruebas');
    const grid = document.getElementById('pruebas-grid');

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
        <h3>${esc(p.titulo)}</h3>
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

  cargarPruebas();
})();
