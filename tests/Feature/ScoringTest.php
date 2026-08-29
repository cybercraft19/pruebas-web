<?php

namespace Tests\Feature;

use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Replicates the two real scoring strategies found in the reference PDFs:
     * PROMEDIO (Cuestionario de Ansiedad: sum / n° preguntas de la categoria)
     * and CONTEO (Test de Inteligencias Multiples: cuenta de respuestas "verdadero").
     */
    public function test_finalizar_calcula_puntajes_por_categoria(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'titulo' => 'Prueba mixta',
            'estado' => 'publicada',
        ]);

        $cognitiva = CategoriaEvaluacion::create([
            'prueba_id' => $prueba->id,
            'nombre' => 'Cognitiva',
            'tipo_puntuacion' => 'PROMEDIO',
            'orden' => 1,
        ]);
        InterpretacionCategoria::create(['categoria_evaluacion_id' => $cognitiva->id, 'valor_min' => 1, 'valor_max' => 3, 'etiqueta' => 'Bajo', 'recomendacion' => 'Seguí así.']);
        InterpretacionCategoria::create(['categoria_evaluacion_id' => $cognitiva->id, 'valor_min' => 3.01, 'valor_max' => 5, 'etiqueta' => 'Alto']);

        $verbal = CategoriaEvaluacion::create([
            'prueba_id' => $prueba->id,
            'nombre' => 'Verbal',
            'tipo_puntuacion' => 'CONTEO',
            'orden' => 2,
        ]);

        // Cognitiva: dos preguntas de escala 1-5, respondidas con 4 y 2 -> promedio (4+2)/2 = 3
        $p1 = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $cognitiva->id, 'texto' => 'P1', 'tipo' => 'escala', 'orden' => 1]);
        $p1Op4 = OpcionRespuesta::create(['pregunta_id' => $p1->id, 'texto' => '4', 'peso' => 4, 'orden' => 4]);
        OpcionRespuesta::create(['pregunta_id' => $p1->id, 'texto' => '1', 'peso' => 1, 'orden' => 1]);

        $p2 = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $cognitiva->id, 'texto' => 'P2', 'tipo' => 'escala', 'orden' => 2]);
        $p2Op2 = OpcionRespuesta::create(['pregunta_id' => $p2->id, 'texto' => '2', 'peso' => 2, 'orden' => 2]);

        // Verbal: dos preguntas verdadero/falso, "verdadero" pesa 1 -> conteo de verdaderos = 1
        $p3 = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $verbal->id, 'texto' => 'P3', 'tipo' => 'verdadero_falso', 'orden' => 3]);
        $p3Verdadero = OpcionRespuesta::create(['pregunta_id' => $p3->id, 'texto' => 'Verdadero', 'peso' => 1, 'orden' => 1]);
        OpcionRespuesta::create(['pregunta_id' => $p3->id, 'texto' => 'Falso', 'peso' => 0, 'orden' => 2]);

        $p4 = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $verbal->id, 'texto' => 'P4', 'tipo' => 'verdadero_falso', 'orden' => 4]);
        $p4Falso = OpcionRespuesta::create(['pregunta_id' => $p4->id, 'texto' => 'Falso', 'peso' => 0, 'orden' => 2]);
        OpcionRespuesta::create(['pregunta_id' => $p4->id, 'texto' => 'Verdadero', 'peso' => 1, 'orden' => 1]);

        $intentoResponse = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id]);
        $intentoResponse->assertCreated();
        $intentoId = $intentoResponse->json('id');

        foreach ([
            [$p1->id, $p1Op4->id],
            [$p2->id, $p2Op2->id],
            [$p3->id, $p3Verdadero->id],
            [$p4->id, $p4Falso->id],
        ] as [$preguntaId, $opcionId]) {
            $this->actingAs($estudiante)
                ->postJson("/api/intentos/{$intentoId}/respuestas", ['pregunta_id' => $preguntaId, 'opcion_id' => $opcionId])
                ->assertOk();
        }

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar");

        $response->assertOk();
        $resultados = collect($response->json('resultados'));

        $cognitivaResultado = $resultados->firstWhere('categoria_evaluacion_id', $cognitiva->id);
        $this->assertEquals(3, $cognitivaResultado['puntaje']);
        $this->assertEquals('Bajo', $cognitivaResultado['etiqueta_interpretacion']);
        $this->assertEquals('Seguí así.', $cognitivaResultado['recomendacion']);

        $verbalResultado = $resultados->firstWhere('categoria_evaluacion_id', $verbal->id);
        $this->assertEquals(1, $verbalResultado['puntaje']);
    }

    public function test_finalizar_falla_si_faltan_respuestas(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $categoria = CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'C', 'tipo_puntuacion' => 'SUMA_PONDERADA', 'orden' => 1]);
        Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $categoria->id, 'texto' => 'P1', 'tipo' => 'escala', 'orden' => 1]);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar");

        $response->assertUnprocessable();
    }

    public function test_estudiante_no_puede_iniciar_intento_de_prueba_no_publicada(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Borrador', 'estado' => 'borrador']);

        $response = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id]);

        $response->assertUnprocessable();
    }

    public function test_estudiante_no_puede_responder_intento_de_otro_estudiante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $pregunta = Pregunta::create(['prueba_id' => $prueba->id, 'texto' => 'P1', 'tipo' => 'escala', 'orden' => 1]);
        $opcion = OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => '1', 'peso' => 1, 'orden' => 1]);

        $dueno = User::factory()->create(['role' => 'estudiante']);
        $intentoId = $this->actingAs($dueno)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $otro = User::factory()->create(['role' => 'estudiante']);
        $response = $this->actingAs($otro)->postJson("/api/intentos/{$intentoId}/respuestas", [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion->id,
        ]);

        $response->assertForbidden();
    }
}
