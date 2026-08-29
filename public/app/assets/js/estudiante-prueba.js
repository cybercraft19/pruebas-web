(async () => {
  const user = await requireSession('estudiante');
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

  const intentoId = new URLSearchParams(window.location.search).get('intento');
  if (!intentoId) {
    window.location.href = '/app/estudiante/index.html';
    return;
  }

  const preguntasCard = document.getElementById('preguntas-card');
  const quizBody = document.getElementById('quiz-body');

  const BADGE_POR_ETIQUETA = {
    Bajo: 'completado',
    Medio: 'borrador',
    Alto: 'no-superada',
    Estándar: 'archivada',
    Fortaleza: 'completado',
  };

  function mostrarResultados(intento) {
    preguntasCard.style.display = 'none';
    const card = document.getElementById('resultados-card');
    card.style.display = 'block';
    card.innerHTML = `
      <div class="resultado-celebracion">
        <div class="resultado-celebracion__confetti" id="confetti-host"></div>
        <div class="resultado-celebracion__icon">${Icons.award}</div>
        <h2>¡Prueba completada!</h2>
        <p class="muted">Este es tu resultado por categoría.</p>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>Categoría</th><th>Puntaje</th><th>Interpretación</th></tr></thead>
          <tbody id="resultados-body"></tbody>
        </table>
      </div>
    `;
    document.getElementById('resultados-body').innerHTML = intento.resultados.map((r) => `
      <tr>
        <td>${r.categoria ? r.categoria.nombre : '—'}</td>
        <td>${r.puntaje}</td>
        <td>${r.etiqueta_interpretacion ? `<span class="badge ${BADGE_POR_ETIQUETA[r.etiqueta_interpretacion] || 'info'}">${r.etiqueta_interpretacion}</span>` : '—'}</td>
      </tr>
    `).join('') || '<tr><td colspan="3" class="muted">Sin resultados.</td></tr>';
    lanzarConfeti(document.getElementById('confetti-host'));
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

    preguntasCard.style.display = 'block';

    const categoriasPorId = {};
    prueba.categorias.forEach((c) => { categoriasPorId[c.id] = c; });

    const preguntas = prueba.preguntas;
    const respuestasPorPregunta = {};
    intento.respuestas.forEach((r) => { respuestasPorPregunta[r.pregunta_id] = r.opcion_id; });

    let indice = preguntas.findIndex((p) => !respuestasPorPregunta[p.id]);
    if (indice === -1) indice = 0;

    function actualizarProgreso() {
      const respondidas = Object.keys(respuestasPorPregunta).length;
      document.getElementById('progreso').textContent = `${respondidas} / ${preguntas.length} preguntas respondidas`;
      document.getElementById('progreso-fill').style.width = `${Math.round((respondidas / preguntas.length) * 100)}%`;
    }

    function renderOpciones(p, seleccionActual) {
      if (p.tipo === 'escala') {
        return `<div class="scale-options">${p.opciones.map((o) => {
          const separador = o.texto.indexOf(' - ');
          const numero = separador === -1 ? o.texto : o.texto.slice(0, separador);
          const etiqueta = separador === -1 ? '' : o.texto.slice(separador + 3);
          return `
            <button type="button" class="scale-btn ${seleccionActual === o.id ? 'selected' : ''}" data-opcion="${o.id}">
              <span class="scale-btn__num">${numero}</span>
              <span class="scale-btn__label">${etiqueta}</span>
            </button>
          `;
        }).join('')}</div>`;
      }

      if (p.tipo === 'verdadero_falso') {
        return `<div class="tf-options">${p.opciones.map((o) => {
          const esVerdadero = o.texto.toLowerCase() === 'verdadero';
          return `
            <button type="button" class="tf-btn ${esVerdadero ? 'tf-btn--true' : 'tf-btn--false'} ${seleccionActual === o.id ? 'selected' : ''}" data-opcion="${o.id}">
              ${esVerdadero ? Icons.checkCircle : Icons.xCircle}
              ${o.texto}
            </button>
          `;
        }).join('')}</div>`;
      }

      return `<div class="opciones-list">${p.opciones.map((o) => `
        <button type="button" class="option-btn ${seleccionActual === o.id ? 'selected' : ''}" data-opcion="${o.id}">${o.texto}</button>
      `).join('')}</div>`;
    }

    function render() {
      const p = preguntas[indice];
      const categoria = categoriasPorId[p.categoria_evaluacion_id];
      const seleccionActual = respuestasPorPregunta[p.id];
      const todasRespondidas = Object.keys(respuestasPorPregunta).length >= preguntas.length;
      const esUltima = indice === preguntas.length - 1;

      quizBody.innerHTML = `
        <div class="quiz-meta">
          ${categoria ? `<span class="badge info">${Icons.tag} ${categoria.nombre}</span>` : '<span></span>'}
          <span class="quiz-meta__counter">Pregunta ${indice + 1} de ${preguntas.length}</span>
        </div>
        <div class="quiz-question">${p.texto}</div>
        ${renderOpciones(p, seleccionActual)}
        <div class="quiz-nav">
          <button type="button" class="secondary" id="quiz-anterior" ${indice === 0 ? 'disabled' : ''}>${Icons.chevronLeft} Anterior</button>
          <div class="quiz-dots">${preguntas.map((pp, i) => `<button type="button" class="quiz-dot ${respuestasPorPregunta[pp.id] ? 'answered' : ''} ${i === indice ? 'current' : ''}" data-ir="${i}" aria-label="Pregunta ${i + 1}"></button>`).join('')}</div>
          ${esUltima
            ? `<button type="button" id="finalizar-btn" ${todasRespondidas ? '' : 'disabled'}>${Icons.checkCircle} Finalizar y calificar</button>`
            : `<button type="button" id="quiz-siguiente" ${seleccionActual ? '' : 'disabled'}>Siguiente ${Icons.chevronRight}</button>`}
        </div>
        <div class="error" id="finalizar-error"></div>
      `;

      quizBody.querySelectorAll('[data-opcion]').forEach((el) => {
        el.addEventListener('click', async () => {
          const opcionId = Number(el.dataset.opcion);
          await Api.post(`/api/intentos/${intentoId}/respuestas`, { pregunta_id: p.id, opcion_id: opcionId });
          respuestasPorPregunta[p.id] = opcionId;
          actualizarProgreso();

          if (!esUltima) {
            setTimeout(() => { indice += 1; render(); }, 250);
          } else {
            render();
          }
        });
      });

      quizBody.querySelectorAll('[data-ir]').forEach((el) => {
        el.addEventListener('click', () => { indice = Number(el.dataset.ir); render(); });
      });

      const anteriorBtn = document.getElementById('quiz-anterior');
      if (anteriorBtn) anteriorBtn.addEventListener('click', () => { indice -= 1; render(); });

      const siguienteBtn = document.getElementById('quiz-siguiente');
      if (siguienteBtn) siguienteBtn.addEventListener('click', () => { indice += 1; render(); });

      const finalizarBtn = document.getElementById('finalizar-btn');
      if (finalizarBtn) {
        finalizarBtn.addEventListener('click', async () => {
          const errorBox = document.getElementById('finalizar-error');
          errorBox.textContent = '';
          finalizarBtn.disabled = true;
          try {
            const resultado = await Api.post(`/api/intentos/${intentoId}/finalizar`);
            mostrarResultados({ resultados: resultado.resultados.map((r) => ({ ...r, categoria: categoriasPorId[r.categoria_evaluacion_id] })) });
          } catch (err) {
            errorBox.textContent = formatError(err);
            finalizarBtn.disabled = false;
          }
        });
      }
    }

    actualizarProgreso();
    render();
  }

  cargar();
})();
