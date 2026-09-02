<?php

namespace Tests\Feature;

use App\Models\Intento;
use App\Models\Prueba;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentoRepeticionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_iniciar_de_nuevo_una_prueba_ya_finalizada(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        $response = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('intentos', 1);
    }

    public function test_iniciar_de_nuevo_una_prueba_en_progreso_reanuda_el_mismo_intento(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $primerIntentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $response = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id]);

        $response->assertOk();
        $this->assertSame($primerIntentoId, $response->json('id'));
        $this->assertDatabaseCount('intentos', 1);
    }

    public function test_distintos_estudiantes_pueden_hacer_la_misma_prueba_cada_uno_una_vez(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudianteUno = User::factory()->create(['role' => 'estudiante']);
        $estudianteDos = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $this->actingAs($estudianteUno)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->assertCreated();
        $this->actingAs($estudianteDos)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->assertCreated();

        $this->assertDatabaseCount('intentos', 2);
    }

    /**
     * Regresión: verifica que el índice único (prueba_id, estudiante_id) en la base de datos
     * es la última línea de defensa contra la carrera "dos requests simultáneas crean dos
     * intentos", más allá de la verificación a nivel de aplicación en el controlador.
     */
    public function test_la_base_de_datos_rechaza_dos_intentos_para_el_mismo_estudiante_y_prueba(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        Intento::create([
            'prueba_id' => $prueba->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'en_progreso',
            'iniciado_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Intento::create([
            'prueba_id' => $prueba->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'en_progreso',
            'iniciado_at' => now(),
        ]);
    }
}
