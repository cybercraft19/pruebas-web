<?php

namespace Tests\Feature;

use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use App\Services\RejillaLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistaEstudianteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cuestionario de una pregunta que el estudiante responde y finaliza.
     */
    private function cuestionarioFinalizado(User $evaluador, User $estudiante, string $titulo = 'Cuestionario'): array
    {
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => $titulo, 'estado' => 'publicada']);
        $categoria = CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'Atención', 'tipo_puntuacion' => 'SUMA_PONDERADA', 'orden' => 1]);
        InterpretacionCategoria::create(['categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 5, 'etiqueta' => 'Alto', 'recomendacion' => 'Descansa más.']);
        $pregunta = Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $categoria->id, 'texto' => 'P', 'tipo' => 'escala', 'orden' => 1]);
        $opcion = OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => '4', 'peso' => 4, 'orden' => 1]);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", ['pregunta_id' => $pregunta->id, 'opcion_id' => $opcion->id])->assertOk();
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        return [$prueba, $intentoId];
    }

    public function test_el_evaluador_ve_los_datos_del_estudiante_sus_pruebas_y_resultados(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create([
            'role' => 'estudiante',
            'creado_por' => $evaluador->id,
            'cedula' => '12345',
            'acudiente_nombre' => 'María Pérez',
        ]);
        [$prueba, $intentoId] = $this->cuestionarioFinalizado($evaluador, $estudiante);

        $response = $this->actingAs($evaluador)->getJson("/api/estudiantes/{$estudiante->id}");

        $response->assertOk();
        $response->assertJsonPath('estudiante.cedula', '12345');
        $response->assertJsonPath('estudiante.acudiente_nombre', 'María Pérez');
        $response->assertJsonCount(1, 'intentos');
        $response->assertJsonPath('intentos.0.id', $intentoId);
        $response->assertJsonPath('intentos.0.prueba.titulo', 'Cuestionario');
        $response->assertJsonPath('intentos.0.estado', 'finalizado');
        $response->assertJsonPath('intentos.0.firmado', false);
        $response->assertJsonPath('intentos.0.resumen.0.concepto', 'Atención');
        $response->assertJsonPath('intentos.0.resumen.0.valor', '4');
        $response->assertJsonPath('intentos.0.resumen.0.interpretacion', 'Alto');
        $response->assertJsonPath('intentos.0.resumen.0.recomendacion', 'Descansa más.');
        $response->assertJsonCount(0, 'pendientes');
    }

    public function test_las_pruebas_publicadas_que_no_hizo_aparecen_como_pendientes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $hecha = $this->cuestionarioFinalizado($evaluador, $estudiante, 'Ya hecha')[0];
        $sinHacer = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Sin hacer', 'estado' => 'publicada']);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Borrador', 'estado' => 'borrador']);

        $response = $this->actingAs($evaluador)->getJson("/api/estudiantes/{$estudiante->id}");

        $response->assertOk();
        $this->assertSame([$sinHacer->id], collect($response->json('pendientes'))->pluck('id')->all());
        $this->assertNotContains($hecha->id, collect($response->json('pendientes'))->pluck('id')->all());
    }

    public function test_muestra_el_resultado_de_una_rejilla_y_de_un_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);

        $rejilla = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'rejilla', 'titulo' => 'Rejilla', 'estado' => 'publicada']);
        app(RejillaLayoutService::class)->generar($rejilla);
        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $rejilla->id])->json('id');
        foreach (['estandar', 'caballo'] as $variante) {
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/rejilla/iniciar", ['variante' => $variante])->assertOk();
            $this->travel(60)->seconds();
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => $variante, 'aciertos' => 22, 'errores' => 1])->assertOk();
        }
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        $resumen = $this->actingAs($evaluador)->getJson("/api/estudiantes/{$estudiante->id}")->json('intentos.0.resumen');

        $this->assertCount(2, $resumen);
        $this->assertSame('22 números · 1 errores', $resumen[0]['valor']);
        $this->assertSame('Buen nivel de concentración', $resumen[0]['interpretacion']);
    }

    public function test_un_intento_en_progreso_no_muestra_resultados(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->assertCreated();

        $response = $this->actingAs($evaluador)->getJson("/api/estudiantes/{$estudiante->id}");

        $response->assertJsonPath('intentos.0.estado', 'en_progreso');
        $response->assertJsonCount(0, 'intentos.0.resumen');
    }

    public function test_el_evaluador_no_ve_a_un_estudiante_de_otro_evaluador(): void
    {
        $evaluadorA = User::factory()->create(['role' => 'evaluador']);
        $evaluadorB = User::factory()->create(['role' => 'evaluador']);
        $estudianteDeB = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluadorB->id]);

        $this->actingAs($evaluadorA)->getJson("/api/estudiantes/{$estudianteDeB->id}")->assertForbidden();
    }

    public function test_un_estudiante_no_puede_ver_esta_vista(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);

        $this->actingAs($estudiante)->getJson("/api/estudiantes/{$estudiante->id}")->assertForbidden();
    }

    public function test_la_ruta_de_exportar_sigue_funcionando_junto_a_la_del_detalle(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $this->actingAs($evaluador)->get('/api/estudiantes/exportar')->assertOk();
    }
}
