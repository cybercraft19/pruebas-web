<?php

namespace Tests\Feature;

use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use App\Services\RejillaLayoutService;
use App\Services\TmtLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: int}
     */
    private function cuestionarioFinalizado(): array
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id, 'cedula' => '999']);

        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Cuestionario', 'estado' => 'publicada']);
        $categoria = CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'Atención', 'tipo_puntuacion' => 'SUMA_PONDERADA', 'orden' => 1]);
        InterpretacionCategoria::create(['categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 3, 'etiqueta' => 'Bajo']);
        InterpretacionCategoria::create(['categoria_evaluacion_id' => $categoria->id, 'valor_min' => 3.01, 'valor_max' => 5, 'etiqueta' => 'Alto', 'recomendacion' => 'Descansa más.']);
        $pregunta = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $categoria->id, 'texto' => 'P', 'tipo' => 'escala', 'orden' => 1]);
        $opcion = OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => '4', 'peso' => 4, 'orden' => 1]);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", ['pregunta_id' => $pregunta->id, 'opcion_id' => $opcion->id])->assertOk();
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        return [$evaluador, $estudiante, $intentoId];
    }

    public function test_el_evaluador_ve_el_informe_aunque_no_este_firmado(): void
    {
        [$evaluador, , $intentoId] = $this->cuestionarioFinalizado();

        $response = $this->actingAs($evaluador)->getJson("/api/intentos/{$intentoId}/informe");

        $response->assertOk();
        $response->assertJsonPath('estudiante.cedula', '999');
        $response->assertJsonPath('prueba.titulo', 'Cuestionario');
        $response->assertJsonPath('firmado_at', null);
        $response->assertJsonPath('resumen.0.concepto', 'Atención');
        $response->assertJsonPath('grafica.0.concepto', 'Atención');
        $response->assertJsonPath('grafica.0.valor', 4);
        $response->assertJsonCount(2, 'grafica.0.bandas');
        $response->assertJsonPath('grafica.0.bandas.1.etiqueta', 'Alto');
    }

    public function test_el_estudiante_no_ve_el_informe_hasta_que_esta_firmado(): void
    {
        [$evaluador, $estudiante, $intentoId] = $this->cuestionarioFinalizado();

        $this->actingAs($estudiante)->getJson("/api/intentos/{$intentoId}/informe")->assertForbidden();

        $this->actingAs($evaluador)->postJson("/api/intentos/{$intentoId}/firmar")->assertOk();

        $response = $this->actingAs($estudiante)->getJson("/api/intentos/{$intentoId}/informe");
        $response->assertOk();
        $response->assertJsonPath('firmado_por.id', $evaluador->id);
    }

    public function test_no_hay_informe_de_un_intento_sin_finalizar(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $this->actingAs($evaluador)->getJson("/api/intentos/{$intentoId}/informe")->assertUnprocessable();
    }

    public function test_otro_evaluador_no_ve_el_informe_de_una_prueba_ajena(): void
    {
        [, , $intentoId] = $this->cuestionarioFinalizado();
        $otroEvaluador = User::factory()->create(['role' => 'evaluador']);

        $this->actingAs($otroEvaluador)->getJson("/api/intentos/{$intentoId}/informe")->assertForbidden();
    }

    public function test_el_informe_de_tmt_trae_el_limite_oficial(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT', 'estado' => 'publicada']);
        app(TmtLayoutService::class)->generar($prueba);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        foreach (['A' => 42, 'B' => 120] as $parte => $segundos) {
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/tmt/iniciar", ['parte' => $parte])->assertOk();
            $this->travel($segundos)->seconds();
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/tmt", ['parte' => $parte, 'tiempo_segundos' => $segundos, 'errores' => 0])->assertOk();
        }
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        $grafica = $this->actingAs($evaluador)->getJson("/api/intentos/{$intentoId}/informe")->json('grafica');

        $this->assertSame(100, $grafica[0]['limite_oficial']);
        $this->assertSame(300, $grafica[1]['limite_oficial']);
        $this->assertTrue($grafica[0]['aprobado']);
    }

    public function test_el_informe_de_rejilla_trae_el_umbral(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'rejilla', 'titulo' => 'Rejilla', 'estado' => 'publicada']);
        app(RejillaLayoutService::class)->generar($prueba);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        foreach (['estandar', 'caballo'] as $variante) {
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/rejilla/iniciar", ['variante' => $variante])->assertOk();
            $this->travel(60)->seconds();
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => $variante, 'aciertos' => 24, 'errores' => 1])->assertOk();
        }
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        $grafica = $this->actingAs($evaluador)->getJson("/api/intentos/{$intentoId}/informe")->json('grafica');

        $this->assertSame(20, $grafica[0]['umbral_buen_nivel']);
        $this->assertTrue($grafica[0]['aprobado']);
    }
}
