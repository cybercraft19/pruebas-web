(async () => {
  const user = await requireSession('evaluador');
  if (!user) return;

  initSidebar();
  document.getElementById('user-name').textContent = user.name;
  document.getElementById('user-avatar').textContent = user.name.charAt(0).toUpperCase();
  document.getElementById('volver-link').innerHTML = `${Icons.arrowLeft} Volver a estudiantes`;

  const logoutBtn = document.getElementById('logout-btn');
  logoutBtn.innerHTML = Icons.logout;
  logoutBtn.addEventListener('click', async () => {
    await Api.post('/api/logout');
    window.location.href = '/app/login.html';
  });

  const estudianteId = new URLSearchParams(window.location.search).get('id');
  if (!estudianteId) {
    window.location.href = '/app/evaluador/estudiantes.html';
    return;
  }

  const TIPOS = { cuestionario: 'Cuestionario', tmt: 'TMT', rejilla: 'Rejilla' };

  const fecha = (valor) => (valor ? new Date(valor).toLocaleString('es') : '—');

  function edadDe(fechaNacimiento) {
    const nacimiento = new Date(fechaNacimiento);
    const hoy = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    if (hoy < new Date(hoy.getFullYear(), nacimiento.getMonth(), nacimiento.getDate())) edad -= 1;
    return edad;
  }

  function renderDatos(e) {
    document.getElementById('est-nombre').textContent = e.name;
    document.getElementById('est-correo').textContent = e.email;

    const nacimiento = e.fecha_nacimiento
      ? `${new Date(e.fecha_nacimiento).toLocaleDateString('es', { timeZone: 'UTC' })} (${edadDe(e.fecha_nacimiento.slice(0, 10))} años)`
      : '—';

    const datos = [
      ['Cédula', e.cedula || '—'],
      ['Teléfono', e.telefono || '—'],
      ['Fecha de nacimiento', nacimiento],
      ['Acudiente', e.acudiente_nombre || '—'],
      ['Teléfono del acudiente', e.acudiente_telefono || '—'],
      ['Registrado', fecha(e.created_at)],
      ['Consentimiento de datos', e.consentimiento_at ? fecha(e.consentimiento_at) : 'No registrado'],
    ];

    document.getElementById('datos-grid').innerHTML = datos
      .map(([etiqueta, valor]) => `<div><dt>${esc(etiqueta)}</dt><dd>${esc(valor)}</dd></div>`)
      .join('');
  }

  function estadoDe(intento) {
    if (intento.estado === 'en_progreso') return '<span class="badge borrador">En progreso</span>';
    return intento.firmado
      ? '<span class="badge completado">Firmado</span>'
      : '<span class="badge info">Pendiente de firma</span>';
  }

  function renderIntento(intento) {
    const filas = intento.resumen.map((r) => `
      <tr>
        <td>${esc(r.concepto)}</td>
        <td>${esc(r.valor)}</td>
        <td>
          ${r.interpretacion ? esc(r.interpretacion) : '—'}
          ${r.recomendacion ? `<div class="muted" style="font-size:0.8rem;margin-top:2px">${esc(r.recomendacion)}</div>` : ''}
        </td>
      </tr>
    `).join('');

    const detalle = intento.estado === 'finalizado'
      ? `<div style="overflow-x:auto"><table><thead><tr><th>Concepto</th><th>Resultado</th><th>Interpretación</th></tr></thead><tbody>${filas || '<tr><td colspan="3" class="muted">Sin resultados.</td></tr>'}</tbody></table></div>`
      : '<p class="muted" style="margin-top:8px">Todavía no la ha terminado.</p>';

    const firmar = intento.estado === 'finalizado' && !intento.firmado
      ? `<button type="button" class="secondary small" data-firmar="${intento.id}">Firmar y publicar</button>`
      : '';

    return `
      <div class="intento-bloque">
        <div class="flex-between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
          <div>
            <strong>${esc(intento.prueba.titulo)}</strong>
            <div class="muted" style="font-size:0.8rem;margin-top:2px">
              ${esc(TIPOS[intento.prueba.tipo] || intento.prueba.tipo)} · ${intento.finalizado_at ? `finalizada el ${fecha(intento.finalizado_at)}` : `iniciada el ${fecha(intento.iniciado_at)}`}
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px">${estadoDe(intento)}${firmar}</div>
        </div>
        ${detalle}
      </div>
    `;
  }

  async function cargar() {
    const data = await Api.get(`/api/estudiantes/${estudianteId}`);

    renderDatos(data.estudiante);

    document.getElementById('intentos-body').innerHTML = data.intentos.length
      ? data.intentos.map(renderIntento).join('')
      : `<div class="empty-state">${Icons.inbox}<p>Todavía no ha presentado ninguna prueba.</p></div>`;

    const pendientesCard = document.getElementById('pendientes-card');
    pendientesCard.style.display = data.pendientes.length ? 'block' : 'none';
    document.getElementById('pendientes-body').innerHTML = data.pendientes
      .map((p) => `<div class="recent-item"><div style="flex:1;min-width:0"><div class="recent-item__title">${esc(p.titulo)}</div><div class="recent-item__meta">${esc(TIPOS[p.tipo] || p.tipo)}</div></div><span class="badge info">Sin iniciar</span></div>`)
      .join('');
  }

  document.getElementById('intentos-body').addEventListener('click', async (e) => {
    const boton = e.target.closest('[data-firmar]');
    if (!boton) return;

    boton.disabled = true;
    try {
      await Api.post(`/api/intentos/${boton.dataset.firmar}/firmar`);
      cargar();
    } catch (err) {
      boton.disabled = false;
      alert(formatError(err));
    }
  });

  try {
    await cargar();
  } catch (err) {
    document.getElementById('intentos-body').innerHTML = `<div class="error">${esc(formatError(err))}</div>`;
  }
})();
