(async () => {
  const user = await requireSession('evaluador');
  if (!user) return;

  document.getElementById('logout-btn').addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  const pruebaId = new URLSearchParams(window.location.search).get('id');
  if (!pruebaId) {
    window.location.href = '/app/evaluador/index.html';
    return;
  }

  async function cargar() {
    const prueba = await Api.get(`/api/pruebas/${pruebaId}`);

    document.getElementById('titulo').textContent = prueba.titulo;
    document.getElementById('instrucciones').textContent = prueba.instrucciones || '';

    const badge = document.getElementById('estado-badge');
    badge.textContent = prueba.estado;
    badge.className = `badge ${prueba.estado}`;

    const publicarBtn = document.getElementById('publicar-btn');
    if (prueba.estado === 'publicada') {
      publicarBtn.textContent = 'Ya publicada';
      publicarBtn.disabled = true;
    }
    publicarBtn.addEventListener('click', async () => {
      await Api.post(`/api/pruebas/${pruebaId}/publicar`);
      cargar();
    });

    document.getElementById('categorias-body').innerHTML = prueba.categorias.map((c) => `
      <tr><td>${c.nombre}</td><td>${c.tipo_puntuacion}</td></tr>
    `).join('');

    document.getElementById('preguntas-body').innerHTML = prueba.preguntas.map((p) => `
      <div class="pregunta-block">
        <strong>${p.orden}. ${p.texto}</strong>
        <ul class="opciones-list" style="list-style:none;padding-left:0">
          ${p.opciones.map((o) => `<li>${o.texto} <span class="muted">(peso: ${o.peso})</span></li>`).join('')}
        </ul>
      </div>
    `).join('') || '<p class="muted">Esta prueba no tiene preguntas.</p>';
  }

  async function cargarResultados() {
    const resultados = await Api.get(`/api/pruebas/${pruebaId}/resultados`);
    const rows = [];

    resultados.forEach((intento) => {
      intento.resultados.forEach((r) => {
        rows.push([
          intento.estudiante.name,
          intento.estudiante.email,
          r.categoria,
          r.puntaje,
          r.etiqueta || '—',
          new Date(intento.finalizado_at).toLocaleString(),
        ]);
      });
    });

    $('#resultados-table').DataTable({
      data: rows,
      language: { emptyTable: 'Aún no hay resultados de estudiantes.' },
    });
  }

  cargar();
  cargarResultados();
})();
