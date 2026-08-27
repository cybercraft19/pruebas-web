<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intento;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\RespuestaEstudiante;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntentoController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'prueba_id' => ['required', 'exists:pruebas,id'],
        ]);

        $prueba = Prueba::findOrFail($data['prueba_id']);
        abort_unless($prueba->estado === 'publicada', 422, 'La prueba no está publicada.');

        $intento = Intento::create([
            'prueba_id' => $prueba->id,
            'estudiante_id' => Auth::id(),
            'estado' => 'en_progreso',
            'iniciado_at' => now(),
        ]);

        return response()->json($intento->load('prueba.categorias', 'prueba.preguntas.opciones'), 201);
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

        $totalPreguntas = $intento->prueba()->first()->preguntas()->count();
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

        return $intento->load(['prueba.categorias', 'prueba.preguntas.opciones', 'respuestas', 'resultados.categoria']);
    }

    public function mios()
    {
        return Intento::where('estudiante_id', Auth::id())
            ->with('prueba:id,titulo')
            ->latest()
            ->get();
    }

    private function authorizeEstudiante(Intento $intento): void
    {
        abort_unless($intento->estudiante_id === Auth::id(), 403);
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
