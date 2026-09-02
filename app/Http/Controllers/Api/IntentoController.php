<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intento;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\RespuestaEstudiante;
use App\Models\TmtResultado;
use App\Services\ScoringService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntentoController extends Controller
{
    private const TMT_LIMITES = ['A' => 100, 'B' => 300];

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

        $relaciones = $prueba->tipo === 'tmt'
            ? ['prueba.tmtNodos']
            : ['prueba.categorias', 'prueba.preguntas.opciones'];

        return response()->json($intento->load($relaciones), $creado ? 201 : 200);
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

        $relaciones = $intento->prueba->tipo === 'tmt'
            ? ['prueba.tmtNodos', 'tmtResultados']
            : ['prueba.categorias', 'prueba.preguntas.opciones', 'respuestas', 'resultados.categoria'];

        return $intento->load($relaciones);
    }

    public function mios()
    {
        return Intento::where('estudiante_id', Auth::id())
            ->with('prueba:id,titulo,tipo')
            ->latest()
            ->get();
    }

    public function firmar(Intento $intento)
    {
        $this->authorizeEvaluador($intento);
        abort_unless($intento->estado === 'finalizado', 422, 'El intento todavía no está finalizado.');

        $intento->update(['firmado_at' => now(), 'firmado_por' => Auth::id()]);

        return $intento->fresh();
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
