<?php

namespace Tests\Feature;

use App\Models\CategoriaEvaluacion;
use App\Models\Prueba;
use App\Models\User;
use App\Services\TmtLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultadosExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_puede_exportar_resultados_de_cuestionario(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Cuestionario X', 'estado' => 'publicada']);
        CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'C', 'tipo_puntuacion' => 'PROMEDIO', 'orden' => 1]);

        $response = $this->actingAs($evaluador)->get("/api/pruebas/{$prueba->id}/resultados/exportar");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_evaluador_puede_exportar_resultados_de_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'tmt', 'titulo' => 'TMT X', 'estado' => 'publicada']);
        app(TmtLayoutService::class)->generar($prueba);

        $response = $this->actingAs($evaluador)->get("/api/pruebas/{$prueba->id}/resultados/exportar");

        $response->assertOk();
    }

    public function test_evaluador_no_puede_exportar_resultados_de_prueba_ajena(): void
    {
        $dueno = User::factory()->create(['role' => 'evaluador']);
        $otro = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $dueno->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $response = $this->actingAs($otro)->get("/api/pruebas/{$prueba->id}/resultados/exportar");

        $response->assertForbidden();
    }

    public function test_estudiante_no_puede_exportar_resultados(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $response = $this->actingAs($estudiante)->get("/api/pruebas/{$prueba->id}/resultados/exportar");

        $response->assertForbidden();
    }
}
