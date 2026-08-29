<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmtTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_crea_prueba_tmt_con_layout_generado(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->postJson('/api/pruebas/tmt', [
            'titulo' => 'Trail Making Test',
            'instrucciones' => 'Una una los círculos en orden.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tipo', 'tmt');

        $nodos = collect($response->json('tmt_nodos'));
        $this->assertCount(8, $nodos->where('parte', 'A')->where('practica', true));
        $this->assertCount(25, $nodos->where('parte', 'A')->where('practica', false));
        $this->assertCount(8, $nodos->where('parte', 'B')->where('practica', true));
        $this->assertCount(25, $nodos->where('parte', 'B')->where('practica', false));

        $etiquetasParteB = $nodos->where('parte', 'B')->where('practica', false)->sortBy('orden')->pluck('etiqueta')->values();
        $this->assertEquals(['1', 'A', '2', 'B', '3'], $etiquetasParteB->take(5)->all());
        $this->assertEquals('13', $etiquetasParteB->last());
    }

    public function test_estudiante_completa_flujo_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = $this->actingAs($evaluador)
            ->postJson('/api/pruebas/tmt', ['titulo' => 'TMT'])
            ->json();

        $this->actingAs($evaluador)->postJson("/api/pruebas/{$prueba['id']}/publicar")->assertOk();

        $intentoId = $this->actingAs($estudiante)
            ->postJson('/api/intentos', ['prueba_id' => $prueba['id']])
            ->assertCreated()
            ->json('id');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/tmt", ['parte' => 'A', 'tiempo_segundos' => 42, 'errores' => 1])
            ->assertOk()
            ->assertJsonPath('completado', true);

        // Excede el límite de 300s de la parte B -> se registra como no superada (301s).
        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/tmt", ['parte' => 'B', 'tiempo_segundos' => 350, 'errores' => 3])
            ->assertOk()
            ->assertJsonPath('completado', false)
            ->assertJsonPath('tiempo_segundos', 301);

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar");
        $response->assertOk();
        $response->assertJsonPath('intento.estado', 'finalizado');

        $resultados = $this->actingAs($evaluador)
            ->getJson("/api/pruebas/{$prueba['id']}/resultados")
            ->json();

        $this->assertCount(1, $resultados);
        $this->assertCount(2, $resultados[0]['tmt_resultados']);
    }

    public function test_finalizar_falla_si_falta_una_parte_del_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT', 'estado' => 'publicada']);

        $intentoId = $this->actingAs($estudiante)
            ->postJson('/api/intentos', ['prueba_id' => $prueba->id])
            ->json('id');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/tmt", ['parte' => 'A', 'tiempo_segundos' => 42, 'errores' => 0])
            ->assertOk();

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar");

        $response->assertUnprocessable();
    }
}
