<?php

namespace Tests\Feature;

use App\Models\CategoriaEvaluacion;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirmaResultadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_estudiante_no_ve_resultados_hasta_que_el_evaluador_firma(): void
    {
        [$evaluador, $estudiante, $intentoId] = $this->intentoFinalizado();

        $antes = $this->actingAs($estudiante)->getJson("/api/intentos/{$intentoId}");
        $antes->assertOk();
        $this->assertNull($antes->json('firmado_at'));

        $this->actingAs($evaluador)->postJson("/api/intentos/{$intentoId}/firmar")->assertOk();

        $despues = $this->actingAs($estudiante)->getJson("/api/intentos/{$intentoId}");
        $despues->assertOk();
        $this->assertNotNull($despues->json('firmado_at'));
    }

    public function test_no_se_puede_firmar_un_intento_que_no_es_de_una_prueba_propia(): void
    {
        [, $estudiante, $intentoId] = $this->intentoFinalizado();
        $otroEvaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($otroEvaluador)->postJson("/api/intentos/{$intentoId}/firmar");

        $response->assertForbidden();
    }

    public function test_no_se_puede_firmar_un_intento_que_no_esta_finalizado(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $response = $this->actingAs($evaluador)->postJson("/api/intentos/{$intentoId}/firmar");

        $response->assertUnprocessable();
    }

    public function test_el_estudiante_no_puede_firmar(): void
    {
        [, $estudiante, $intentoId] = $this->intentoFinalizado();

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/firmar");

        $response->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: int}
     */
    private function intentoFinalizado(): array
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $categoria = CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'C', 'tipo_puntuacion' => 'PROMEDIO', 'orden' => 1]);
        $pregunta = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $categoria->id, 'texto' => 'P1', 'tipo' => 'escala', 'orden' => 1]);
        $opcion = OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => '1', 'peso' => 1, 'orden' => 1]);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion->id,
        ])->assertOk();
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        return [$evaluador, $estudiante, $intentoId];
    }
}
