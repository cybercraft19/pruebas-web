(async () => {
  const user = await requireSession('estudiante');
  if (!user) return;

  document.getElementById('logout-btn').addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  const intentoId = new URLSearchParams(window.location.search).get('intento');
  if (!intentoId) {
    window.location.href = '/app/estudiante/index.html';
    return;
  }

  function mostrarResultados(intento) {
    document.getElementById('preguntas-card').style.display = 'none';
    document.getElementById('resultados-card').style.display = 'block';
    document.getElementById('resultados-body').innerHTML = intento.resultados.map((r) => `
      <tr>
        <td>${r.categoria.nombre}</td>
        <td>${r.puntaje}</td>
        <td>${r.etiqueta_interpretacion || '—'}</td>
      </tr>
    `).join('') || '<tr><td colspan="3" class="muted">Sin resultados.</td></tr>';
  }

  function actualizarProgreso(prueba, respuestas) {
    const total = prueba.preguntas.length;
    const respondidas = respuestas.length;
    document.getElementById('progreso').textContent = `${respondidas} / ${total} preguntas respondidas`;
  }

  async function cargar() {
    const intento = await Api.get(`/api/intentos/${intentoId}`);
    const prueba = intento.prueba;

    document.getElementById('titulo').textContent = prueba.titulo;
    document.getElementById('instrucciones').textContent = prueba.instrucciones || '';

    if (intento.estado === 'finalizado') {
      mostrarResultados(intento);
      return;
    }

    document.getElementById('preguntas-card').style.display = 'block';

    const respuestasPorPregunta = {};
    intento.respuestas.forEach((r) => { respuestasPorPregunta[r.pregunta_id] = r.opcion_id; });

    document.getElementById('preguntas-body').innerHTML = prueba.preguntas.map((p) => `
      <div class="pregunta-block">
        <strong>${p.orden}. ${p.texto}</strong>
        <div class="opciones-list">
          ${p.opciones.map((o) => `
            <label>
              <input type="radio" name="pregunta-${p.id}" value="${o.id}"
                ${respuestasPorPregunta[p.id] === o.id ? 'checked' : ''} />
              ${o.texto}
            </label>
          `).join('')}
        </div>
      </div>
    `).join('');

    actualizarProgreso(prueba, intento.respuestas);

    document.querySelectorAll('#preguntas-body input[type=radio]').forEach((input) => {
      input.addEventListener('change', async (e) => {
        const preguntaId = Number(e.target.name.replace('pregunta-', ''));
        const opcionId = Number(e.target.value);

        await Api.post(`/api/intentos/${intentoId}/respuestas`, { pregunta_id: preguntaId, opcion_id: opcionId });

        respuestasPorPregunta[preguntaId] = opcionId;
        actualizarProgreso(prueba, Object.keys(respuestasPorPregunta));
      });
    });

    document.getElementById('finalizar-btn').addEventListener('click', async () => {
      const errorBox = document.getElementById('finalizar-error');
      errorBox.textContent = '';
      try {
        const resultado = await Api.post(`/api/intentos/${intentoId}/finalizar`);
        mostrarResultados({ resultados: resultado.resultados.map((r) => ({ ...r, categoria: prueba.categorias.find((c) => c.id === r.categoria_evaluacion_id) })) });
      } catch (err) {
        errorBox.textContent = formatError(err);
      }
    });
  }

  cargar();
})();
