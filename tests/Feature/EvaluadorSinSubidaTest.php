<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluadorSinSubidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_evaluador_ya_no_puede_descargar_la_plantilla(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $this->actingAs($evaluador)->get('/api/pruebas/plantilla')->assertNotFound();
    }

    public function test_el_evaluador_ya_no_puede_importar_una_prueba(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $this->actingAs($evaluador)->postJson('/api/pruebas/importar')->assertStatus(405);
    }

    public function test_el_evaluador_ya_no_puede_crear_un_tmt(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $this->actingAs($evaluador)->postJson('/api/pruebas/tmt', ['titulo' => 'TMT'])->assertStatus(405);
    }
}
