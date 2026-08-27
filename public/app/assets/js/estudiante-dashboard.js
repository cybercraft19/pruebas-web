(async () => {
  const user = await requireSession('estudiante');
  if (!user) return;
  document.getElementById('user-name').textContent = `${user.name} (estudiante)`;

  document.getElementById('logout-btn').addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  async function cargarDisponibles() {
    const pruebas = await Api.get('/api/pruebas-publicadas');
    const body = document.getElementById('disponibles-body');

    if (pruebas.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="muted">No hay pruebas disponibles por ahora.</td></tr>';
      return;
    }

    body.innerHTML = pruebas.map((p) => `
      <tr>
        <td>${p.titulo}</td>
        <td>${p.preguntas_count}</td>
        <td>${p.tiempo_max_minutos ? p.tiempo_max_minutos + ' min' : '—'}</td>
        <td><button class="small" data-prueba-id="${p.id}">Iniciar</button></td>
      </tr>
    `).join('');

    body.querySelectorAll('button[data-prueba-id]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const intento = await Api.post('/api/intentos', { prueba_id: Number(btn.dataset.pruebaId) });
        window.location.href = `/app/estudiante/prueba.html?intento=${intento.id}`;
      });
    });
  }

  async function cargarHistorial() {
    const intentos = await Api.get('/api/mis-intentos');
    const body = document.getElementById('historial-body');

    if (intentos.length === 0) {
      body.innerHTML = '<tr><td colspan="3" class="muted">Aún no has presentado ninguna prueba.</td></tr>';
      return;
    }

    body.innerHTML = intentos.map((i) => `
      <tr>
        <td>${i.prueba.titulo}</td>
        <td><span class="badge ${i.estado === 'finalizado' ? 'publicada' : 'borrador'}">${i.estado}</span></td>
        <td><a class="link" href="/app/estudiante/prueba.html?intento=${i.id}">${i.estado === 'finalizado' ? 'Ver resultado' : 'Continuar'}</a></td>
      </tr>
    `).join('');
  }

  cargarDisponibles();
  cargarHistorial();
})();
