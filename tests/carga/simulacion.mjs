#!/usr/bin/env node
/**
 * Simula estudiantes reales contra el sitio para ver cuántos aguanta a la vez.
 * Sin dependencias: solo Node 20 o superior.
 *
 * Cada alumno virtual: pide la cookie CSRF, inicia sesión, abre el panel, empieza un
 * cuestionario, responde todas las preguntas (con tiempo de pensar) y lo finaliza.
 *
 * Uso:
 *   node tests/carga/simulacion.mjs --url https://tu-dominio --alumnos 50 --rampa 20 --prueba 1
 *
 * Opciones:
 *   --url        Sitio a probar (obligatorio).
 *   --alumnos    Cantidad de alumnos virtuales (por defecto 10, máximo 300).
 *   --rampa      Segundos en los que van entrando (0 = todos en el mismo instante).
 *   --prueba     ID del cuestionario que hacen (por defecto 1).
 *   --pensar     Segundos promedio entre respuestas (por defecto 3; 0 = sin pausa).
 *   --escenario  "completo" (por defecto) o "login" (solo la tormenta de inicios de sesión).
 *   --prefijo    Prefijo de los correos de prueba (por defecto "carga" -> carga001@prueba.local).
 *   --password   Contraseña de las cuentas de prueba (por defecto CargaTest#2026).
 *   --timeout    Segundos máximos por petición (por defecto 30).
 *   --reintentos Reintentos ante errores pasajeros (500/502/503/504/508 o corte de red), igual que
 *                el navegador del sitio (por defecto 3; 0 = sin reintentos, para medir el servidor a pelo).
 *   --salida     Archivo .json donde guardar el resumen.
 *   --sin-espera Salta la cuenta regresiva de 5 s antes de empezar.
 */
import { writeFileSync } from 'node:fs';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, actual, i, todos) => {
    if (actual.startsWith('--')) {
      const siguiente = todos[i + 1];
      acc.push([actual.slice(2), siguiente && !siguiente.startsWith('--') ? siguiente : true]);
    }
    return acc;
  }, []),
);

const config = {
  url: String(args.url || '').replace(/\/+$/, ''),
  alumnos: Number(args.alumnos ?? 10),
  rampa: Number(args.rampa ?? 0),
  prueba: Number(args.prueba ?? 1),
  pensar: Number(args.pensar ?? 3),
  escenario: args.escenario || 'completo',
  prefijo: args.prefijo || 'carga',
  password: args.password || 'CargaTest#2026',
  timeoutMs: Number(args.timeout ?? 30) * 1000,
  reintentos: Number(args.reintentos ?? 3),
  salida: args.salida && args.salida !== true ? args.salida : null,
};

if (!config.url) {
  console.error('Falta --url. Ejemplo: node tests/carga/simulacion.mjs --url https://tu-dominio --alumnos 50');
  process.exit(2);
}
if (!Number.isInteger(config.alumnos) || config.alumnos < 1 || config.alumnos > 300) {
  console.error('--alumnos tiene que ser un entero entre 1 y 300.');
  process.exit(2);
}
if (!['completo', 'login'].includes(config.escenario)) {
  console.error('--escenario tiene que ser "completo" o "login".');
  process.exit(2);
}

const metricas = {};
const estadosGlobales = {};
const abortos = {};
let completados = 0;
let activos = 0;
let peticiones = 0;
let reintentosTotales = 0;

const dormir = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

class Sesion {
  constructor() {
    this.cookies = new Map();
  }

  guardar(respuesta) {
    for (const cookie of respuesta.headers.getSetCookie?.() ?? []) {
      const [par] = cookie.split(';');
      const indice = par.indexOf('=');
      if (indice > 0) this.cookies.set(par.slice(0, indice).trim(), par.slice(indice + 1));
    }
  }

  cabeceras(conCuerpo) {
    const cookie = [...this.cookies].map(([nombre, valor]) => `${nombre}=${valor}`).join('; ');
    const xsrf = this.cookies.get('XSRF-TOKEN');
    return {
      Accept: 'application/json',
      Origin: config.url,
      Referer: `${config.url}/app/login.html`,
      ...(conCuerpo ? { 'Content-Type': 'application/json' } : {}),
      ...(cookie ? { Cookie: cookie } : {}),
      ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
    };
  }
}

async function intentar(sesion, metodo, ruta, cuerpo) {
  let estado = 0;
  let datos = null;
  let error = null;

  try {
    const respuesta = await fetch(config.url + ruta, {
      method: metodo,
      headers: sesion.cabeceras(cuerpo !== undefined),
      body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
      redirect: 'manual',
      signal: AbortSignal.timeout(config.timeoutMs),
    });
    estado = respuesta.status;
    sesion.guardar(respuesta);
    const texto = await respuesta.text();
    try {
      datos = texto ? JSON.parse(texto) : null;
    } catch {
      datos = null;
    }
  } catch (e) {
    error = e.name === 'TimeoutError' ? 'timeout' : (e.cause?.code || e.message);
  }

  return { estado, datos, error };
}

const esPasajero = ({ estado, error }) => error !== null || [500, 502, 503, 504, 508].includes(estado);

async function pedir(sesion, etiqueta, metodo, ruta, cuerpo) {
  const inicio = performance.now();
  const registro = (metricas[etiqueta] ??= { tiempos: [], errores: 0, reintentos: 0, estados: {} });
  const puedeReintentar = config.reintentos > 0 && !ruta.endsWith('/finalizar');

  let resultado;
  for (let n = 0; ; n += 1) {
    resultado = await intentar(sesion, metodo, ruta, cuerpo);
    const clave = resultado.error ?? resultado.estado;
    estadosGlobales[clave] = (estadosGlobales[clave] ?? 0) + 1;
    peticiones += 1;

    if (!esPasajero(resultado) || !puedeReintentar || n >= config.reintentos) break;
    registro.reintentos += 1;
    reintentosTotales += 1;
    await dormir(700 * 2 ** n * (0.6 + Math.random() * 0.8));
  }

  const { estado, datos, error } = resultado;
  const clave = error ?? estado;
  registro.tiempos.push(performance.now() - inicio);
  registro.estados[clave] = (registro.estados[clave] ?? 0) + 1;

  const exito = !error && estado >= 200 && estado < 300;
  if (!exito) registro.errores += 1;

  return { exito, estado, datos, error };
}

function abortar(motivo) {
  abortos[motivo] = (abortos[motivo] ?? 0) + 1;
}

async function alumnoVirtual(indice) {
  const sesion = new Sesion();
  const correo = `${config.prefijo}${String(indice).padStart(3, '0')}@prueba.local`;

  activos += 1;
  try {
    if (!(await pedir(sesion, '1 csrf-cookie', 'GET', '/sanctum/csrf-cookie')).exito) return abortar('csrf');

    const login = await pedir(sesion, '2 login', 'POST', '/api/login', { email: correo, password: config.password });
    if (!login.exito) return abortar(`login (${login.error ?? login.estado})`);

    if (config.escenario === 'login') {
      await pedir(sesion, '3 /api/user', 'GET', '/api/user');
      completados += 1;
      return;
    }

    await pedir(sesion, '3 /api/user', 'GET', '/api/user');

    const publicadas = await pedir(sesion, '4 pruebas-publicadas', 'GET', '/api/pruebas-publicadas');
    if (!publicadas.exito) return abortar('publicadas');

    const iniciar = await pedir(sesion, '5 iniciar intento', 'POST', '/api/intentos', { prueba_id: config.prueba });
    if (!iniciar.exito) return abortar(`iniciar intento (${iniciar.error ?? iniciar.estado})`);

    const intentoId = iniciar.datos.id;
    const intento = await pedir(sesion, '6 cargar prueba', 'GET', `/api/intentos/${intentoId}`);
    if (!intento.exito) return abortar('cargar prueba');

    for (const pregunta of intento.datos.prueba.preguntas) {
      if (config.pensar > 0) await dormir(config.pensar * 1000 * (0.5 + Math.random()));
      const opcion = pregunta.opciones[Math.floor(Math.random() * pregunta.opciones.length)];
      const respuesta = await pedir(sesion, '7 responder', 'POST', `/api/intentos/${intentoId}/respuestas`, {
        pregunta_id: pregunta.id,
        opcion_id: opcion.id,
      });
      if (!respuesta.exito) return abortar(`responder (${respuesta.error ?? respuesta.estado})`);
    }

    const final = await pedir(sesion, '8 finalizar', 'POST', `/api/intentos/${intentoId}/finalizar`);
    if (!final.exito) return abortar(`finalizar (${final.error ?? final.estado})`);

    completados += 1;
  } finally {
    activos -= 1;
  }
}

function percentil(ordenados, p) {
  if (ordenados.length === 0) return 0;
  return ordenados[Math.min(ordenados.length - 1, Math.ceil((p / 100) * ordenados.length) - 1)];
}

function resumen(duracionSeg) {
  const filas = Object.entries(metricas)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([etiqueta, m]) => {
      const t = [...m.tiempos].sort((a, b) => a - b);
      return {
        paso: etiqueta,
        peticiones: t.length,
        errores: m.errores,
        reintentos: m.reintentos,
        p50: Math.round(percentil(t, 50)),
        p95: Math.round(percentil(t, 95)),
        p99: Math.round(percentil(t, 99)),
        max: Math.round(t[t.length - 1] ?? 0),
      };
    });

  const totalErrores = filas.reduce((suma, f) => suma + f.errores, 0);
  const peorP95 = Math.max(0, ...filas.map((f) => f.p95));
  const abortados = Object.values(abortos).reduce((suma, n) => suma + n, 0);

  return {
    configuracion: { ...config, password: '***' },
    duracionSegundos: Math.round(duracionSeg),
    alumnos: config.alumnos,
    completaron: completados,
    abortaron: abortados,
    motivosDeAborto: abortos,
    peticiones,
    reintentosUsados: reintentosTotales,
    peticionesPorSegundo: Number((peticiones / duracionSeg).toFixed(1)),
    totalErrores,
    codigosDeEstado: estadosGlobales,
    peorP95ms: peorP95,
    pasos: filas,
  };
}

function imprimir(r) {
  console.log('\n================ RESULTADO ================');
  console.log(`Sitio: ${config.url}  |  escenario: ${config.escenario}  |  alumnos: ${r.alumnos}  |  rampa: ${config.rampa}s  |  pensar: ${config.pensar}s`);
  console.log(`Duración: ${r.duracionSegundos}s  |  peticiones: ${r.peticiones} (${r.peticionesPorSegundo}/s)  |  reintentos usados: ${r.reintentosUsados}`);
  console.log(`Alumnos que terminaron: ${r.completaron}/${r.alumnos}  |  abortaron: ${r.abortaron}`);
  if (r.abortaron) console.log('Motivos de aborto:', r.motivosDeAborto);
  console.log('Códigos de respuesta (cada intento cuenta, incluidos los reintentos):', r.codigosDeEstado);
  console.table(r.pasos.map((f) => ({ Paso: f.paso, Peticiones: f.peticiones, 'Errores finales': f.errores, Reintentos: f.reintentos, 'p50 ms': f.p50, 'p95 ms': f.p95, 'p99 ms': f.p99, 'máx ms': f.max })));

  const sinProblemas = r.abortaron === 0 && r.totalErrores === 0;
  const rapido = r.peorP95ms < 2000;
  console.log('\nCriterio: todos terminan, cero errores y p95 menor a 2000 ms en cada paso.');
  console.log(sinProblemas && rapido
    ? 'VEREDICTO: APROBADO con esta carga.'
    : `VEREDICTO: NO APROBADO. ${sinProblemas ? '' : 'Hubo errores o alumnos que no terminaron. '}${rapido ? '' : `El peor p95 fue ${r.peorP95ms} ms.`}`);
  return sinProblemas && rapido;
}

async function principal() {
  const local = /^https?:\/\/(localhost|127\.0\.0\.1)/.test(config.url);
  console.log(`\nApuntando a ${config.url} con ${config.alumnos} alumnos (${config.escenario}).`);
  if (!local && !args['sin-espera']) {
    console.log('Es un sitio real: puedes cancelar con Ctrl+C. Empieza en 5 segundos...');
    await dormir(5000);
  }

  const inicio = performance.now();
  const avance = setInterval(() => {
    console.log(`  [${Math.round((performance.now() - inicio) / 1000)}s] activos: ${activos} | terminaron: ${completados} | peticiones: ${peticiones} | errores: ${Object.entries(estadosGlobales).filter(([k]) => !/^2/.test(k)).reduce((s, [, n]) => s + n, 0)}`);
  }, 5000);

  const alumnos = [];
  for (let i = 1; i <= config.alumnos; i += 1) {
    const espera = config.rampa > 0 ? (config.rampa * 1000 * (i - 1)) / config.alumnos : 0;
    alumnos.push(dormir(espera).then(() => alumnoVirtual(i)));
  }
  await Promise.allSettled(alumnos);
  clearInterval(avance);

  const r = resumen((performance.now() - inicio) / 1000);
  const aprobado = imprimir(r);
  if (config.salida) {
    writeFileSync(config.salida, JSON.stringify(r, null, 2));
    console.log(`Resumen guardado en ${config.salida}`);
  }
  process.exit(aprobado ? 0 : 1);
}

principal();
