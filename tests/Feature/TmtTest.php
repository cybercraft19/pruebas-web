<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\User;
use App\Services\TmtLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmtTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_layout_generado_tiene_la_cantidad_y_orden_correctos(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT', 'estado' => 'borrador']);

        app(TmtLayoutService::class)->generar($prueba);

        $nodos = $prueba->tmtNodos()->get();
        $this->assertCount(8, $nodos->where('parte', 'A')->where('practica', true));
        $this->assertCount(25, $nodos->where('parte', 'A')->where('practica', false));
        $this->assertCount(8, $nodos->where('parte', 'B')->where('practica', true));
        $this->assertCount(25, $nodos->where('parte', 'B')->where('practica', false));

        $etiquetasParteB = $nodos->where('parte', 'B')->where('practica', false)->sortBy('orden')->pluck('etiqueta')->values();
        $this->assertEquals(['1', 'A', '2', 'B', '3'], $etiquetasParteB->take(5)->all());
        $this->assertEquals('13', $etiquetasParteB->last());
    }

    public function test_el_layout_es_siempre_el_mismo_entre_distintas_pruebas(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $pruebaUno = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT 1', 'estado' => 'borrador']);
        $pruebaDos = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT 2', 'estado' => 'borrador']);

        $layoutService = app(TmtLayoutService::class);
        $layoutService->generar($pruebaUno);
        $layoutService->generar($pruebaDos);

        $posiciones = fn (Prueba $prueba) => $prueba->tmtNodos()->get()
            ->map(fn ($n) => "{$n->parte}-{$n->practica}-{$n->etiqueta}:{$n->pos_x},{$n->pos_y}")
            ->sort()
            ->values();

        $this->assertEquals($posiciones($pruebaUno)->all(), $posiciones($pruebaDos)->all());
    }

    public function test_estudiante_completa_flujo_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT', 'estado' => 'borrador']);
        app(TmtLayoutService::class)->generar($prueba);

        $this->actingAs($evaluador)->postJson("/api/pruebas/{$prueba->id}/publicar")->assertOk();

        $intentoId = $this->actingAs($estudiante)
            ->postJson('/api/intentos', ['prueba_id' => $prueba->id])
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
            ->getJson("/api/pruebas/{$prueba->id}/resultados")
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
