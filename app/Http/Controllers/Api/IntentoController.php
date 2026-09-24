<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intento;
use App\Models\IntentoParte;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\RejillaResultado;
use App\Models\RespuestaEstudiante;
use App\Models\TmtResultado;
use App\Services\InformeService;
use App\Services\ScoringService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntentoController extends Controller
{
    private const TMT_LIMITES = ['A' => 100, 'B' => 300];

    private const REJILLA_VARIANTES = ['estandar', 'caballo'];

    /** Margen (s) entre lo que mide el navegador y lo que mide el servidor. */
    private const TOLERANCIA_SEGUNDOS = 5;

    /** Un humano no conecta un círculo del TMT en menos de esto (s por círculo). */
    private const TMT_MIN_SEGUNDOS_POR_NODO = 0.25;

    /** Tope generoso de números tocados por segundo en la rejilla (lo real ronda 2). */
    private const REJILLA_MAX_ACIERTOS_POR_SEGUNDO = 5;

    /** Un minuto de la rejilla más un margen de red antes de dejar de contar el tiempo. */
    private const REJILLA_SEGUNDOS_MAXIMOS = 75;

    public function store(Request $request)
    {
        $data = $request->validate([
            'prueba_id' => ['required', 'exists:pruebas,id'],
        ]);

        $prueba = Prueba::findOrFail($data['prueba_id']);
        abort_unless($prueba->estado === 'publicada', 422, 'La prueba no está publicada.');

        $existente = Intento::where('prueba_id', $prueba->id)
            ->where('estudiante_id', Auth::id())
            ->first();

        abort_if($existente && $existente->estado === 'finalizado', 422, 'Ya presentaste esta prueba. No se puede repetir.');

        $creado = false;

        if ($existente) {
            $intento = $existente;
        } else {
            try {
                $intento = Intento::create([
                    'prueba_id' => $prueba->id,
                    'estudiante_id' => Auth::id(),
                    'estado' => 'en_progreso',
                    'iniciado_at' => now(),
                ]);
                $creado = true;
            } catch (QueryException $e) {
                // Dos peticiones simultáneas del mismo estudiante: la otra ganó la carrera
                // y el índice único (prueba_id, estudiante_id) rechazó esta segunda inserción.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }

                $intento = Intento::where('prueba_id', $prueba->id)
                    ->where('estudiante_id', Auth::id())
                    ->firstOrFail();

                abort_if($intento->estado === 'finalizado', 422, 'Ya presentaste esta prueba. No se puede repetir.');
            }
        }

        $relaciones = match ($prueba->tipo) {
            'tmt' => ['prueba.tmtNodos'],
            'rejilla' => ['prueba.rejillaCeldas'],
            default => ['prueba.categorias', 'prueba.preguntas.opciones'],
        };

        return response()->json($intento->load($relaciones), $creado ? 201 : 200);
    }

    public function iniciarTmt(Request $request, Intento $intento)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');
        abort_unless($intento->prueba()->first()->tipo === 'tmt', 422, 'Esta prueba no es de tipo TMT.');

        $data = $request->validate(['parte' => ['required', 'in:A,B']]);

        return $this->marcarInicio($intento, 'tmt-'.$data['parte']);
    }

    public function iniciarRejilla(Request $request, Intento $intento)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');
        abort_unless($intento->prueba()->first()->tipo === 'rejilla', 422, 'Esta prueba no es de tipo rejilla.');

        $data = $request->validate(['variante' => ['required', 'in:'.implode(',', self::REJILLA_VARIANTES)]]);

        return $this->marcarInicio($intento, 'rejilla-'.$data['variante']);
    }

    public function registrarTmt(Request $request, Intento $intento)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');

        $prueba = $intento->prueba()->first();
        abort_unless($prueba->tipo === 'tmt', 422, 'Esta prueba no es de tipo TMT.');

        $data = $request->validate([
            'parte' => ['required', 'in:A,B'],
            'tiempo_segundos' => ['required', 'integer', 'min:1'],
            'errores' => ['required', 'integer', 'min:0'],
        ]);

        $transcurrido = $this->segundosDesdeElInicio($intento, 'tmt-'.$data['parte']);
        $nodos = $prueba->tmtNodos()->where('parte', $data['parte'])->where('practica', false)->count();

        abort_if(
            $data['tiempo_segundos'] > $transcurrido + self::TOLERANCIA_SEGUNDOS,
            422,
            'El tiempo informado es mayor al que realmente transcurrió.'
        );
        abort_if(
            $data['tiempo_segundos'] < floor($nodos * self::TMT_MIN_SEGUNDOS_POR_NODO),
            422,
            'El tiempo informado es demasiado corto para completar la parte.'
        );

        $limite = self::TMT_LIMITES[$data['parte']];
        $completado = $data['tiempo_segundos'] <= $limite;

        $resultado = TmtResultado::updateOrCreate(
            ['intento_id' => $intento->id, 'parte' => $data['parte']],
            [
                'tiempo_segundos' => $completado ? $data['tiempo_segundos'] : $limite + 1,
                'errores' => $data['errores'],
                'completado' => $completado,
            ]
        );

        return response()->json($resultado);
    }

    public function registrarRejilla(Request $request, Intento $intento)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');

        $prueba = $intento->prueba()->first();
        abort_unless($prueba->tipo === 'rejilla', 422, 'Esta prueba no es de tipo rejilla.');

        $data = $request->validate([
            'variante' => ['required', 'in:'.implode(',', self::REJILLA_VARIANTES)],
            'aciertos' => ['required', 'integer', 'min:0', 'max:100'],
            'errores' => ['required', 'integer', 'min:0'],
        ]);

        $transcurrido = min($this->segundosDesdeElInicio($intento, 'rejilla-'.$data['variante']), self::REJILLA_SEGUNDOS_MAXIMOS);

        abort_if(
            $data['aciertos'] > floor($transcurrido * self::REJILLA_MAX_ACIERTOS_POR_SEGUNDO),
            422,
            'Los números señalados no son posibles en el tiempo transcurrido.'
        );

        $resultado = RejillaResultado::updateOrCreate(
            ['intento_id' => $intento->id, 'variante' => $data['variante']],
            ['aciertos' => $data['aciertos'], 'errores' => $data['errores']]
        );

        return response()->json($resultado);
    }

    public function responder(Request $request, Intento $intento)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');

        $data = $request->validate([
            'pregunta_id' => ['required', 'exists:preguntas,id'],
            'opcion_id' => ['required', 'exists:opciones_respuesta,id'],
        ]);

        Pregunta::where('id', $data['pregunta_id'])->where('prueba_id', $intento->prueba_id)->firstOrFail();
        $opcion = OpcionRespuesta::where('id', $data['opcion_id'])->where('pregunta_id', $data['pregunta_id'])->firstOrFail();

        $respuesta = RespuestaEstudiante::updateOrCreate(
            ['intento_id' => $intento->id, 'pregunta_id' => $data['pregunta_id']],
            ['opcion_id' => $opcion->id]
        );

        return response()->json($respuesta);
    }

    public function finalizar(Intento $intento, ScoringService $scoringService)
    {
        $this->authorizeEstudiante($intento);
        abort_if($intento->estado === 'finalizado', 422, 'El intento ya fue finalizado.');

        $prueba = $intento->prueba()->first();

        if ($prueba->tipo === 'tmt') {
            $partesRegistradas = $intento->tmtResultados()->pluck('parte');
            abort_unless(
                $partesRegistradas->contains('A') && $partesRegistradas->contains('B'),
                422,
                'Faltan partes del TMT por completar.'
            );

            $intento->update(['estado' => 'finalizado', 'finalizado_at' => now()]);

            return response()->json(['intento' => $intento, 'tmt_resultados' => $intento->tmtResultados]);
        }

        if ($prueba->tipo === 'rejilla') {
            $variantesRegistradas = $intento->rejillaResultados()->pluck('variante');
            abort_unless(
                $variantesRegistradas->contains('estandar') && $variantesRegistradas->contains('caballo'),
                422,
                'Faltan variantes de la rejilla por completar.'
            );

            $intento->update(['estado' => 'finalizado', 'finalizado_at' => now()]);

            return response()->json(['intento' => $intento, 'rejilla_resultados' => $intento->rejillaResultados]);
        }

        $totalPreguntas = $prueba->preguntas()->count();
        $totalRespondidas = $intento->respuestas()->count();

        abort_if(
            $totalRespondidas < $totalPreguntas,
            422,
            "Faltan preguntas por responder ({$totalRespondidas}/{$totalPreguntas})."
        );

        $intento->update(['estado' => 'finalizado', 'finalizado_at' => now()]);

        $resultados = $scoringService->calcular($intento);

        return response()->json(['intento' => $intento, 'resultados' => $resultados]);
    }

    public function show(Intento $intento)
    {
        $this->authorizeAcceso($intento);

        $relaciones = match ($intento->prueba->tipo) {
            'tmt' => ['prueba.tmtNodos', 'tmtResultados'],
            'rejilla' => ['prueba.rejillaCeldas', 'rejillaResultados'],
            default => ['prueba.categorias', 'prueba.preguntas.opciones', 'respuestas', 'resultados.categoria'],
        };

        return $intento->load($relaciones);
    }

    /**
     * Datos listos para el informe (base para cuando exista la plantilla visual): el evaluador
     * dueño de la prueba lo puede ver aunque no esté firmado todavía (para revisar antes de
     * firmar); el estudiante solo una vez que está firmado, igual que el resto del sitio.
     */
    public function informe(Intento $intento, InformeService $informeService)
    {
        $userId = Auth::id();
        $esEvaluador = $intento->prueba->creado_por === $userId;
        $esEstudiante = $intento->estudiante_id === $userId;

        abort_unless($esEvaluador || $esEstudiante, 403);
        abort_if($esEstudiante && ! $esEvaluador && $intento->firmado_at === null, 403, 'El informe todavía no está disponible.');
        abort_unless($intento->estado === 'finalizado', 422, 'El intento todavía no está finalizado.');

        return $informeService->generar($intento);
    }

    public function mios()
    {
        return Intento::where('estudiante_id', Auth::id())
            ->with('prueba:id,titulo,tipo')
            ->latest()
            ->get();
    }

    public function miInforme(InformeService $informeService)
    {
        return $informeService->completoDe(Auth::user());
    }

    public function firmar(Intento $intento)
    {
        $this->authorizeEvaluador($intento);
        abort_unless($intento->estado === 'finalizado', 422, 'El intento todavía no está finalizado.');

        $intento->update(['firmado_at' => now(), 'firmado_por' => Auth::id()]);

        return $intento->fresh();
    }

    private function marcarInicio(Intento $intento, string $parte)
    {
        $registro = IntentoParte::updateOrCreate(
            ['intento_id' => $intento->id, 'parte' => $parte],
            ['iniciado_at' => now()]
        );

        return response()->json($registro);
    }

    /**
     * Segundos que el servidor lleva contando desde que el estudiante inició la parte.
     */
    private function segundosDesdeElInicio(Intento $intento, string $parte): int
    {
        $inicio = IntentoParte::where('intento_id', $intento->id)->where('parte', $parte)->first();

        abort_unless($inicio, 422, 'Debes iniciar esta parte antes de registrar su resultado.');

        return max(0, now()->getTimestamp() - $inicio->iniciado_at->getTimestamp());
    }

    private function authorizeEstudiante(Intento $intento): void
    {
        abort_unless($intento->estudiante_id === Auth::id(), 403);
    }

    private function authorizeEvaluador(Intento $intento): void
    {
        abort_unless($intento->prueba->creado_por === Auth::id(), 403);
    }

    private function authorizeAcceso(Intento $intento): void
    {
        $userId = Auth::id();

        abort_unless(
            $intento->estudiante_id === $userId || $intento->prueba->creado_por === $userId,
            403
        );
    }
}
