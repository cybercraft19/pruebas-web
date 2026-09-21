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

  const SEGUNDOS = 60;
  const TOTAL = 100;

  const NOMBRES = {
    estandar: 'Rejilla estándar (Harris y Harris)',
    caballo: 'Rejilla del caballo (Núñez Nieto)',
  };

  const stage = document.getElementById('stage');
  const tituloEl = document.getElementById('titulo');
  const instruccionesEl = document.getElementById('instrucciones');
  const progresoCard = document.getElementById('progreso-card');
  const pasoLabel = document.getElementById('paso-label');
  const pasoFill = document.getElementById('paso-fill');

  const pad = (n) => String(n).padStart(2, '0');

  function celdasDe(prueba, variante) {
    return prueba.rejilla_celdas
      .filter((c) => c.variante === variante)
      .sort((a, b) => a.posicion - b.posicion);
  }

  function mostrarResultadosFinales(rejillaResultados, firmadoAt) {
    progresoCard.style.display = 'none';

    if (!firmadoAt) {
      stage.innerHTML = `
        <div class="resultado-celebracion">
          <div class="resultado-celebracion__confetti" id="confetti-host"></div>
          <div class="resultado-celebracion__icon">${Icons.award}</div>
          <h2>¡Test de la Rejilla completado!</h2>
          <p class="muted">Tu evaluador revisará tus resultados y te avisaremos cuando estén disponibles.</p>
        </div>
      `;
      lanzarConfeti(document.getElementById('confetti-host'));
      return;
    }

    stage.innerHTML = `
      <div class="resultado-celebracion">
        <div class="resultado-celebracion__confetti" id="confetti-host"></div>
        <div class="resultado-celebracion__icon">${Icons.award}</div>
        <h2>¡Test de la Rejilla completado!</h2>
        <p class="muted">Este es tu resultado por rejilla.</p>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>Rejilla</th><th>Números señalados</th><th>Errores</th><th>Nivel</th></tr></thead>
          <tbody>
            ${['estandar', 'caballo'].map((variante) => {
              const r = rejillaResultados.find((res) => res.variante === variante);
              if (!r) return '';
              return `<tr>
                <td>${NOMBRES[variante]}</td>
                <td>${r.aciertos}</td>
                <td>${r.errores}</td>
                <td><span class="badge ${r.aciertos >= 20 ? 'completado' : 'no-superada'}">${esc(r.nivel)}</span></td>
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

  function mostrarIntro({ titulo, descripcion, onComenzar }) {
    stage.innerHTML = `
      <div class="empty-state" style="padding: 32px 16px">
        <div class="test-card__icon tmt" style="margin:0 auto 14px">${Icons.grid}</div>
        <h3 style="margin-bottom:8px">${esc(titulo)}</h3>
        <p style="max-width:440px;margin:0 auto 4px">${esc(descripcion)}</p>
        <p class="muted" style="margin-top:4px">Tienes ${SEGUNDOS} segundos. El tiempo empieza al pulsar Comenzar.</p>
        <button id="comenzar-btn">${Icons.play} Comenzar</button>
      </div>
    `;
    document.getElementById('comenzar-btn').addEventListener('click', onComenzar);
  }

  function renderRejilla({ celdas, onCompletar }) {
    let siguiente = 0;
    let errores = 0;
    let terminado = false;
    const inicio = Date.now();

    stage.innerHTML = `
      <div class="flex-between">
        <span class="muted" id="rejilla-estado">Toca los números en orden, empezando por el 00.</span>
        <span class="badge info" id="rejilla-timer">${SEGUNDOS} s</span>
      </div>
      <div class="rejilla-grid" id="rejilla-grid">
        ${celdas.map((c) => `<button type="button" class="rejilla-cell" data-numero="${c.numero}">${pad(c.numero)}</button>`).join('')}
      </div>
      <div class="flex-between" style="margin-top:12px">
        <span class="muted" id="rejilla-aciertos">Señalados: 0</span>
        <span class="muted" id="rejilla-errores">Errores: 0</span>
      </div>
    `;

    const grid = document.getElementById('rejilla-grid');
    const timerEl = document.getElementById('rejilla-timer');
    const estadoEl = document.getElementById('rejilla-estado');
    const aciertosEl = document.getElementById('rejilla-aciertos');
    const erroresEl = document.getElementById('rejilla-errores');

    function finalizar(mensaje) {
      if (terminado) return;
      terminado = true;
      clearInterval(timerHandle);
      estadoEl.textContent = mensaje;
      grid.querySelectorAll('button').forEach((b) => { b.disabled = true; });
      onCompletar({ aciertos: siguiente, errores });
    }

    const timerHandle = setInterval(() => {
      const restantes = Math.max(0, SEGUNDOS - Math.floor((Date.now() - inicio) / 1000));
      timerEl.textContent = `${restantes} s`;
      if (restantes <= 10) {
        timerEl.classList.remove('info');
        timerEl.classList.add('borrador');
      }
      if (restantes === 0) finalizar('Tiempo agotado.');
    }, 250);

    grid.addEventListener('click', (e) => {
      const celda = e.target.closest('.rejilla-cell');
      if (!celda || terminado) return;

      if (Number(celda.dataset.numero) === siguiente) {
        celda.classList.add('hecha');
        siguiente += 1;
        aciertosEl.textContent = `Señalados: ${siguiente}`;
        estadoEl.textContent = siguiente < TOTAL ? `Sigue con el ${pad(siguiente)}.` : '';
        if (siguiente === TOTAL) finalizar('¡Completaste la rejilla!');
        return;
      }

      if (celda.classList.contains('hecha')) return;

      errores += 1;
      erroresEl.textContent = `Errores: ${errores}`;
      celda.classList.add('error');
      setTimeout(() => celda.classList.remove('error'), 260);
    });
  }

  async function cargar() {
    const intento = await Api.get(`/api/intentos/${intentoId}`);
    const prueba = intento.prueba;

    tituloEl.textContent = prueba.titulo;
    instruccionesEl.textContent = prueba.instrucciones || '';

    if (intento.estado === 'finalizado') {
      mostrarResultadosFinales(intento.rejilla_resultados, intento.firmado_at);
      return;
    }

    const registradas = new Set(intento.rejilla_resultados.map((r) => r.variante));
    const pasos = [];
    if (!registradas.has('estandar')) {
      pasos.push({
        variante: 'estandar',
        titulo: NOMBRES.estandar,
        descripcion: 'Toca los números del 00 al 99 en orden ascendente, de menor a mayor. Señala todos los que puedas.',
      });
    }
    if (!registradas.has('caballo')) {
      pasos.push({
        variante: 'caballo',
        titulo: NOMBRES.caballo,
        descripcion: 'Es otra rejilla, con los números en otro orden. Toca de nuevo los números del 00 al 99 en orden ascendente.',
      });
    }

    progresoCard.style.display = pasos.length ? 'block' : 'none';
    let pasoActual = 0;

    function actualizarProgreso() {
      pasoLabel.textContent = `Rejilla ${pasoActual + 1} de ${pasos.length}`;
      pasoFill.style.width = `${Math.round((pasoActual / pasos.length) * 100)}%`;
    }

    async function siguientePaso() {
      if (pasoActual >= pasos.length) {
        progresoCard.style.display = 'none';
        try {
          const resultado = await Api.post(`/api/intentos/${intentoId}/finalizar`);
          mostrarResultadosFinales(resultado.rejilla_resultados, null);
        } catch (err) {
          mostrarErrorGuardado('No se pudo guardar tu resultado final. Revisa tu conexión e inténtalo de nuevo.', siguientePaso);
        }
        return;
      }

      actualizarProgreso();
      const paso = pasos[pasoActual];

      mostrarIntro({
        titulo: paso.titulo,
        descripcion: paso.descripcion,
        onComenzar: () => {
          renderRejilla({
            celdas: celdasDe(prueba, paso.variante),
            onCompletar: ({ aciertos, errores }) => {
              const guardar = async () => {
                try {
                  await Api.post(`/api/intentos/${intentoId}/rejilla`, { variante: paso.variante, aciertos, errores });
                  pasoActual += 1;
                  setTimeout(siguientePaso, 1000);
                } catch (err) {
                  mostrarErrorGuardado('No se pudo guardar tu resultado de esta rejilla. Revisa tu conexión e inténtalo de nuevo.', guardar);
                }
              };
              guardar();
            },
          });
        },
      });
    }

    siguientePaso();
  }

  cargar();
})();
