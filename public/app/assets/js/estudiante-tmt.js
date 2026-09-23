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

  const LIMITES = { A: 100, B: 300 };
  const ANCHO = 700;
  const ALTO = 440;
  const RADIO = 20;

  const stage = document.getElementById('stage');
  const tituloEl = document.getElementById('titulo');
  const instruccionesEl = document.getElementById('instrucciones');
  const progresoCard = document.getElementById('progreso-card');
  const pasoLabel = document.getElementById('paso-label');
  const pasoFill = document.getElementById('paso-fill');

  function nodosDe(prueba, parte, practica) {
    return prueba.tmt_nodos
      .filter((n) => n.parte === parte && n.practica === practica)
      .sort((a, b) => a.orden - b.orden);
  }

  function mostrarResultadosFinales(tmtResultados, firmadoAt) {
    progresoCard.style.display = 'none';

    if (!firmadoAt) {
      stage.innerHTML = `
        <div class="resultado-celebracion">
          <div class="resultado-celebracion__confetti" id="confetti-host"></div>
          <div class="resultado-celebracion__icon">${Icons.award}</div>
          <h2>¡Trail Making Test completado!</h2>
          <p class="muted">Su evaluador revisará sus resultados y le avisaremos cuando estén disponibles.</p>
        </div>
      `;
      lanzarConfeti(document.getElementById('confetti-host'));
      return;
    }

    stage.innerHTML = `
      <div class="resultado-celebracion">
        <div class="resultado-celebracion__confetti" id="confetti-host"></div>
        <div class="resultado-celebracion__icon">${Icons.award}</div>
        <h2>¡Trail Making Test completado!</h2>
        <p class="muted">Este es su resultado por parte.</p>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>Parte</th><th>Tiempo</th><th>Errores</th><th>Estado</th></tr></thead>
          <tbody>
            ${['A', 'B'].map((parte) => {
              const r = tmtResultados.find((res) => res.parte === parte);
              if (!r) return '';
              return `<tr>
                <td>Parte ${parte}</td>
                <td>${r.tiempo_segundos} s</td>
                <td>${r.errores}</td>
                <td><span class="badge ${r.completado ? 'completado' : 'no-superada'}">${r.completado ? 'Completada' : 'No superada'}</span></td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
    lanzarConfeti(document.getElementById('confetti-host'));
  }

  function mostrarErrorGuardado(mensaje, reintentar) {
    const contenedor = document.createElement('div');
    contenedor.className = 'error';
    contenedor.style.marginTop = '12px';
    contenedor.innerHTML = `<span>${esc(mensaje)}</span>`;
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'secondary small';
    boton.textContent = 'Reintentar';
    boton.addEventListener('click', () => {
      contenedor.remove();
      reintentar();
    });
    contenedor.appendChild(boton);
    stage.appendChild(contenedor);
  }

  function mostrarIntro({ titulo, descripcion, limite, onComenzar }) {
    stage.innerHTML = `
      <div class="empty-state" style="padding: 32px 16px">
        <div class="test-card__icon tmt" style="margin:0 auto 14px">${Icons.stopwatch}</div>
        <h3 style="margin-bottom:8px">${titulo}</h3>
        <p style="max-width:440px;margin:0 auto 4px">${descripcion}</p>
        ${limite ? `<p class="muted" style="margin-top:4px">Límite oficial: ${limite} segundos.</p>` : ''}
        <button id="comenzar-btn">${Icons.play} Comenzar</button>
      </div>
    `;
    const comenzarBtn = document.getElementById('comenzar-btn');
    comenzarBtn.addEventListener('click', () => {
      comenzarBtn.disabled = true;
      onComenzar();
    });
  }

  function renderTablero({ nodos, timed, limite, onCompletar }) {
    let siguienteIndice = 0;
    let errores = 0;
    let inicio = null;
    let terminado = false;
    let timerHandle = null;
    let arrastrando = false;
    let punteroPos = null;
    let nodoError = null;
    let errorTimeout = null;
    let rafHandle = null;

    stage.innerHTML = `
      <div class="flex-between">
        <span class="muted" id="tmt-estado">${timed ? `Mantenga presionado sobre "${nodos[0].etiqueta}" y arrastre sin soltar, conectando en orden.` : 'Práctica — sin tiempo, sin límite de errores.'}</span>
        ${timed ? '<span class="badge info" id="tmt-timer">0 s</span>' : ''}
      </div>
      <canvas id="tmt-canvas" width="${ANCHO}" height="${ALTO}"
        style="width:100%;max-width:${ANCHO}px;aspect-ratio:${ANCHO}/${ALTO};border:1px solid var(--border);border-radius:var(--radius-md);background:#fff;touch-action:none;display:block;margin-top:14px;cursor:none;"></canvas>
      <div class="flex-between" style="margin-top:12px">
        <span class="muted" id="tmt-errores">Errores: 0</span>
      </div>
    `;

    const canvas = document.getElementById('tmt-canvas');
    const ctx = canvas.getContext('2d');
    const estadoEl = document.getElementById('tmt-estado');
    const erroresEl = document.getElementById('tmt-errores');
    const timerEl = document.getElementById('tmt-timer');

    function coords(nodo) {
      return { x: (nodo.pos_x / 100) * ANCHO, y: (nodo.pos_y / 100) * ALTO };
    }

    function dibujar() {
      ctx.clearRect(0, 0, ANCHO, ALTO);

      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.shadowColor = 'rgba(79, 70, 229, 0.28)';
      ctx.shadowBlur = 6;
      ctx.strokeStyle = '#4f46e5';
      ctx.lineWidth = 3;
      ctx.beginPath();
      for (let i = 1; i < siguienteIndice; i++) {
        const a = coords(nodos[i - 1]);
        const b = coords(nodos[i]);
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
      }
      ctx.stroke();
      ctx.shadowBlur = 0;

      if (arrastrando && punteroPos && siguienteIndice > 0 && siguienteIndice < nodos.length) {
        const ultimo = coords(nodos[siguienteIndice - 1]);
        ctx.save();
        ctx.setLineDash([6, 5]);
        ctx.strokeStyle = '#a5b4fc';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(ultimo.x, ultimo.y);
        ctx.lineTo(punteroPos.x, punteroPos.y);
        ctx.stroke();
        ctx.restore();
      }

      // Ningún nodo se marca como "el siguiente": el test mide que el estudiante lo
      // encuentre solo. El único nodo que se distingue es el que ya está conectado
      // (visitado, como el trazo de lápiz en la hoja real) o el que acaba de fallar.
      nodos.forEach((nodo) => {
        const { x, y } = coords(nodo);
        const indice = nodos.indexOf(nodo);
        const visitado = indice < siguienteIndice;
        const conError = nodo === nodoError;

        ctx.beginPath();
        ctx.arc(x, y, RADIO, 0, Math.PI * 2);
        ctx.fillStyle = conError ? '#fee2e2' : visitado ? '#e0e7ff' : '#ffffff';
        ctx.fill();
        ctx.strokeStyle = conError ? '#dc2626' : visitado ? '#818cf8' : '#cbd5e1';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.fillStyle = conError ? '#991b1b' : visitado ? '#4338ca' : '#1e293b';
        ctx.font = '600 15px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(nodo.etiqueta, x, y);
      });

      // "Inicio" y "Fin" sobre el primer y el último nodo de la secuencia,
      // igual que en la hoja oficial del Trail Making Test.
      if (nodos.length > 0) {
        ctx.fillStyle = '#1e293b';
        ctx.font = 'italic 600 12px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';
        const inicio = coords(nodos[0]);
        ctx.fillText('Inicio', inicio.x, inicio.y - RADIO - 6);
        if (nodos.length > 1) {
          const fin = coords(nodos[nodos.length - 1]);
          ctx.fillText('Fin', fin.x, fin.y - RADIO - 6);
        }
      }

      // Cursor propio: el puntero del sistema puede ser invisible sobre el fondo
      // blanco del lienzo según el tema del equipo (canvas usa cursor:none).
      if (punteroPos) {
        ctx.save();
        ctx.lineCap = 'round';
        [
          { color: '#ffffff', width: 4 },
          { color: '#1e293b', width: 2 },
        ].forEach(({ color, width }) => {
          ctx.strokeStyle = color;
          ctx.lineWidth = width;
          ctx.beginPath();
          ctx.moveTo(punteroPos.x - 9, punteroPos.y);
          ctx.lineTo(punteroPos.x + 9, punteroPos.y);
          ctx.moveTo(punteroPos.x, punteroPos.y - 9);
          ctx.lineTo(punteroPos.x, punteroPos.y + 9);
          ctx.stroke();
        });
        ctx.beginPath();
        ctx.arc(punteroPos.x, punteroPos.y, 2.5, 0, Math.PI * 2);
        ctx.fillStyle = '#1e293b';
        ctx.fill();
        ctx.restore();
      }
    }

    function animar() {
      dibujar();
      rafHandle = requestAnimationFrame(animar);
    }
    rafHandle = requestAnimationFrame(animar);

    function finalizarTablero(agotado) {
      if (terminado) return;
      terminado = true;
      if (timerHandle) clearInterval(timerHandle);
      if (rafHandle) cancelAnimationFrame(rafHandle);
      const tiempoSegundos = agotado
        ? limite + 1
        : (timed ? Math.max(1, Math.round((Date.now() - inicio) / 1000)) : 0);
      onCompletar({ tiempo_segundos: tiempoSegundos, errores });
    }

    function actualizarTimer() {
      const segundos = Math.floor((Date.now() - inicio) / 1000);
      timerEl.textContent = `${segundos} s`;
      if (segundos >= Math.floor(limite * 0.8)) {
        timerEl.classList.remove('info');
        timerEl.classList.add('borrador');
      }
      if (segundos >= limite) {
        estadoEl.textContent = 'Tiempo agotado.';
        finalizarTablero(true);
      }
    }

    function posDesdeEvento(e) {
      const rect = canvas.getBoundingClientRect();
      const escala = ANCHO / rect.width;
      return { x: (e.clientX - rect.left) * escala, y: (e.clientY - rect.top) * escala };
    }

    function intentarAvanzar(x, y) {
      if (terminado) return;

      const objetivo = nodos[siguienteIndice];
      const { x: objX, y: objY } = coords(objetivo);

      if (Math.hypot(x - objX, y - objY) <= RADIO) {
        if (timed && siguienteIndice === 0) {
          inicio = Date.now();
          timerHandle = setInterval(actualizarTimer, 250);
          estadoEl.textContent = 'En marcha…';
        }

        siguienteIndice += 1;

        if (siguienteIndice === nodos.length) {
          estadoEl.textContent = '¡Completado!';
          finalizarTablero();
        }
        return;
      }

      const nodoTocado = nodos.find((nodo, indice) => {
        if (indice === siguienteIndice) return false;
        const { x: nx, y: ny } = coords(nodo);
        return Math.hypot(x - nx, y - ny) <= RADIO;
      });

      if (nodoTocado) {
        errores += 1;
        erroresEl.textContent = `Errores: ${errores}`;
        nodoError = nodoTocado;
        clearTimeout(errorTimeout);
        errorTimeout = setTimeout(() => { nodoError = null; }, 260);
      }
    }

    canvas.addEventListener('pointerdown', (e) => {
      if (terminado) return;
      canvas.setPointerCapture(e.pointerId);
      const { x, y } = posDesdeEvento(e);
      arrastrando = true;
      punteroPos = { x, y };
      intentarAvanzar(x, y);
    });

    canvas.addEventListener('pointermove', (e) => {
      const { x, y } = posDesdeEvento(e);
      punteroPos = { x, y };
      if (arrastrando && !terminado) intentarAvanzar(x, y);
    });

    canvas.addEventListener('pointerleave', () => {
      if (!arrastrando) punteroPos = null;
    });

    canvas.addEventListener('pointerup', () => {
      arrastrando = false;
    });

    canvas.addEventListener('pointercancel', () => {
      arrastrando = false;
      punteroPos = null;
    });
  }

  async function cargar() {
    const intento = await Api.get(`/api/intentos/${intentoId}`);
    const prueba = intento.prueba;

    tituloEl.textContent = prueba.titulo;
    instruccionesEl.textContent = prueba.instrucciones || '';

    if (intento.estado === 'finalizado') {
      mostrarResultadosFinales(intento.tmt_resultados, intento.firmado_at);
      return;
    }

    const registradas = new Set(intento.tmt_resultados.map((r) => r.parte));
    const pasos = [];
    if (!registradas.has('A')) {
      pasos.push({
        parte: 'A', practica: true, titulo: 'Práctica — Parte A',
        descripcion: 'Una los círculos numerados en orden, del 1 al 8, tan rápido como pueda. Esta ronda no se cronometra.',
      });
      pasos.push({
        parte: 'A', practica: false, titulo: 'Parte A',
        descripcion: 'Ahora una los círculos del 1 al 25 en orden, lo más rápido posible.',
      });
    }
    if (!registradas.has('B')) {
      pasos.push({
        parte: 'B', practica: true, titulo: 'Práctica — Parte B',
        descripcion: 'Alterne entre números y letras: 1 → A → 2 → B → 3 → C… Esta ronda no se cronometra.',
      });
      pasos.push({
        parte: 'B', practica: false, titulo: 'Parte B',
        descripcion: 'Ahora alterne número y letra en orden hasta llegar al 13, lo más rápido posible.',
      });
    }

    progresoCard.style.display = pasos.length ? 'block' : 'none';
    let pasoActual = 0;

    function actualizarProgreso() {
      pasoLabel.textContent = `Paso ${pasoActual + 1} de ${pasos.length}`;
      pasoFill.style.width = `${Math.round((pasoActual / pasos.length) * 100)}%`;
    }

    async function siguientePaso() {
      if (pasoActual >= pasos.length) {
        progresoCard.style.display = 'none';
        try {
          const resultado = await Api.post(`/api/intentos/${intentoId}/finalizar`);
          mostrarResultadosFinales(resultado.tmt_resultados, null);
        } catch (err) {
          mostrarErrorGuardado('No se pudo guardar su resultado final. Revise su conexión e intente de nuevo.', siguientePaso);
        }
        return;
      }

      actualizarProgreso();
      const paso = pasos[pasoActual];

      mostrarIntro({
        titulo: paso.titulo,
        descripcion: paso.descripcion,
        limite: paso.practica ? null : LIMITES[paso.parte],
        onComenzar: async function comenzar() {
          if (!paso.practica) {
            try {
              await Api.post(`/api/intentos/${intentoId}/tmt/iniciar`, { parte: paso.parte });
            } catch (err) {
              mostrarErrorGuardado('No se pudo iniciar esta parte. Revise su conexión e intente de nuevo.', comenzar);
              return;
            }
          }

          const nodos = nodosDe(prueba, paso.parte, paso.practica);
          renderTablero({
            nodos,
            timed: !paso.practica,
            limite: LIMITES[paso.parte],
            onCompletar: ({ tiempo_segundos, errores }) => {
              const avanzar = async () => {
                try {
                  if (!paso.practica) {
                    await Api.post(`/api/intentos/${intentoId}/tmt`, { parte: paso.parte, tiempo_segundos, errores });
                  }
                  pasoActual += 1;
                  setTimeout(siguientePaso, paso.practica ? 400 : 1000);
                } catch (err) {
                  mostrarErrorGuardado('No se pudo guardar su resultado de esta parte. Revise su conexión e intente de nuevo.', avanzar);
                }
              };
              avanzar();
            },
          });
        },
      });
    }

    siguientePaso();
  }

  cargar();
})();
