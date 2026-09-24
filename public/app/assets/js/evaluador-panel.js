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
  document.querySelectorAll('.stat-card__icon')[3].innerHTML = Icons.users;

  const quickIcons = document.querySelectorAll('.test-card__icon');
  quickIcons[0].innerHTML = Icons.fileText;
  quickIcons[1].innerHTML = Icons.stopwatch;
  quickIcons[2].innerHTML = Icons.users;

  function emptyState(mensaje) {
    return `<div class="empty-state">${Icons.inbox}<p>${mensaje}</p></div>`;
  }

  function renderRecientes(pruebas) {
    const host = document.getElementById('recientes-list');

    if (pruebas.length === 0) {
      host.innerHTML = emptyState('Todavía no ha creado ninguna prueba.');
      return;
    }

    host.innerHTML = pruebas.slice(0, 4).map((p) => {
      const fecha = new Date(p.created_at).toLocaleDateString('es-AR', { day: 'numeric', month: 'short' });
      return `
      <div class="recent-item">
        <div class="test-card__icon ${p.tipo !== 'cuestionario' ? 'tmt' : ''}" style="margin:0">${p.tipo !== 'cuestionario' ? Icons.stopwatch : Icons.fileText}</div>
        <div style="flex:1;min-width:0">
          <div class="recent-item__title">${esc(p.titulo)}</div>
          <div class="recent-item__meta">Creada el ${fecha}</div>
        </div>
        <span class="badge ${p.estado}">${p.estado}</span>
        <a class="link" href="/app/evaluador/prueba.html?id=${p.id}" style="font-size:0.85rem">Ver</a>
      </div>
    `;
    }).join('');
  }

  async function cargarResumen() {
    try {
      const [pruebas, estudiantes] = await Promise.all([
        Api.get('/api/pruebas'),
        Api.get('/api/estudiantes'),
      ]);

      document.getElementById('stat-total').textContent = pruebas.length;
      document.getElementById('stat-publicadas').textContent = pruebas.filter((p) => p.estado === 'publicada').length;
      document.getElementById('stat-estudiantes').textContent = estudiantes.length;

      const haceUnaSemana = Date.now() - 7 * 24 * 60 * 60 * 1000;
      document.getElementById('stat-nuevos').textContent = estudiantes.filter((e) => new Date(e.created_at).getTime() >= haceUnaSemana).length;

      renderRecientes(pruebas);
    } catch (err) {
      const contenedor = document.createElement('div');
      contenedor.className = 'error';
      contenedor.style.margin = '0 0 16px';
      contenedor.innerHTML = `<span>No se pudo cargar el panel: ${esc(formatError(err))}</span>`;
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'secondary small';
      boton.textContent = 'Reintentar';
      boton.addEventListener('click', () => { contenedor.remove(); cargarResumen(); });
      contenedor.appendChild(boton);
      document.querySelector('.container').prepend(contenedor);
    }
  }

  cargarResumen();
})();
