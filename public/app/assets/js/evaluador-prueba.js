(async () => {
  const user = await requireSession('evaluador');
  if (!user) return;

  initSidebar();
  document.getElementById('user-name').textContent = user.name;
  document.getElementById('user-avatar').textContent = user.name.charAt(0).toUpperCase();
  document.getElementById('volver-link').innerHTML = `${Icons.arrowLeft} Volver al panel`;

  const logoutBtn = document.getElementById('logout-btn');
  logoutBtn.innerHTML = Icons.logout;
  logoutBtn.addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  const pruebaId = new URLSearchParams(window.location.search).get('id');
  if (!pruebaId) {
    window.location.href = '/app/evaluador/index.html';
    return;
  }

  const exportarBtn = document.getElementById('exportar-resultados-btn');
  exportarBtn.innerHTML = `${Icons.download} Exportar Excel`;
  exportarBtn.addEventListener('click', () => {
    Api.download(`/api/pruebas/${pruebaId}/resultados/exportar`, `prueba-${pruebaId}-resultados.xlsx`);
  });

  let tipoPrueba = 'cuestionario';

  async function cargar() {
    const prueba = await Api.get(`/api/pruebas/${pruebaId}`);
    tipoPrueba = prueba.tipo;

    document.getElementById('titulo').textContent = prueba.titulo;
    document.getElementById('instrucciones').textContent = prueba.instrucciones || '';

    const badge = document.getElementById('estado-badge');
    badge.textContent = prueba.estado;
    badge.className = `badge ${prueba.estado}`;

    const publicarBtn = document.getElementById('publicar-btn');
    if (prueba.estado === 'publicada') {
      publicarBtn.innerHTML = `${Icons.checkCircle} Ya publicada`;
      publicarBtn.disabled = true;
    } else {
      publicarBtn.innerHTML = `${Icons.play} Publicar prueba`;
    }
    publicarBtn.addEventListener('click', async () => {
      await Api.post(`/api/pruebas/${pruebaId}/publicar`);
      cargar();
    });

    if (tipoPrueba === 'tmt') {
      document.getElementById('categorias-card').style.display = 'none';
      document.getElementById('preguntas-card').style.display = 'none';
      document.getElementById('tmt-card').style.display = 'block';

      const contar = (parte, practica) => prueba.tmt_nodos.filter((n) => n.parte === parte && n.practica === practica).length;
      document.getElementById('tmt-resumen').innerHTML = `
        <div class="stat-card">
          <div class="stat-card__icon">${Icons.grid}</div>
          <div>
            <div class="stat-card__value">${contar('A', false)}</div>
            <div class="stat-card__label">Círculos Parte A (práctica: ${contar('A', true)})</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card__icon">${Icons.grid}</div>
          <div>
            <div class="stat-card__value">${contar('B', false)}</div>
            <div class="stat-card__label">Círculos Parte B (práctica: ${contar('B', true)})</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card__icon">${Icons.stopwatch}</div>
          <div>
            <div class="stat-card__value">100 s / 300 s</div>
            <div class="stat-card__label">Límite oficial (Parte A / Parte B)</div>
          </div>
        </div>
      `;
      return;
    }

    document.getElementById('categorias-body').innerHTML = prueba.categorias.map((c) => `
      <div class="categoria-tile">
        <strong>${c.nombre}</strong>
        <span class="badge info">${c.tipo_puntuacion}</span>
      </div>
    `).join('');

    const categoriasPorId = {};
    prueba.categorias.forEach((c) => { categoriasPorId[c.id] = c; });

    document.getElementById('preguntas-count').textContent = `${prueba.preguntas.length} preguntas`;

    document.getElementById('preguntas-body').innerHTML = prueba.preguntas.map((p) => {
      const categoria = categoriasPorId[p.categoria_evaluacion_id];
      return `
        <div class="pregunta-block">
          <div class="flex-between" style="align-items:flex-start;gap:12px">
            <strong>${p.orden}. ${p.texto}</strong>
            ${categoria ? `<span class="badge info" style="flex-shrink:0">${categoria.nombre}</span>` : ''}
          </div>
          <ul class="opciones-preview">
            ${p.opciones.map((o) => `<li>${o.texto} <span class="peso">${o.peso}</span></li>`).join('')}
          </ul>
        </div>
      `;
    }).join('') || '<p class="muted">Esta prueba no tiene preguntas.</p>';
  }

  async function cargarResultados() {
    const resultados = await Api.get(`/api/pruebas/${pruebaId}/resultados`);
    const rows = [];

    if (tipoPrueba === 'tmt') {
      document.getElementById('resultados-head').innerHTML =
        '<tr><th>Estudiante</th><th>Correo</th><th>Parte</th><th>Tiempo</th><th>Errores</th><th>Estado</th><th>Finalizado</th></tr>';

      resultados.forEach((intento) => {
        intento.tmt_resultados.forEach((r) => {
          rows.push([
            intento.estudiante.name,
            intento.estudiante.email,
            r.parte,
            `${r.tiempo_segundos} s`,
            r.errores,
            `<span class="badge ${r.completado ? 'completado' : 'no-superada'}">${r.completado ? 'Completada' : 'No superada'}</span>`,
            new Date(intento.finalizado_at).toLocaleString(),
          ]);
        });
      });
    } else {
      document.getElementById('resultados-head').innerHTML =
        '<tr><th>Estudiante</th><th>Correo</th><th>Categoría</th><th>Puntaje</th><th>Interpretación</th><th>Finalizado</th></tr>';

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
    }

    $('#resultados-table').DataTable({
      data: rows,
      language: { emptyTable: 'Aún no hay resultados de estudiantes.' },
    });
  }

  cargar().then(cargarResultados);
})();
