<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruebaEdicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_puede_editar_titulo_e_instrucciones(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Original', 'estado' => 'borrador']);

        $response = $this->actingAs($evaluador)->putJson("/api/pruebas/{$prueba->id}", [
            'titulo' => 'Título corregido',
            'instrucciones' => 'Nuevas instrucciones',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pruebas', [
            'id' => $prueba->id,
            'titulo' => 'Título corregido',
            'instrucciones' => 'Nuevas instrucciones',
        ]);
    }

    public function test_evaluador_no_puede_editar_prueba_ajena(): void
    {
        $dueno = User::factory()->create(['role' => 'evaluador']);
        $otro = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $dueno->id, 'titulo' => 'P', 'estado' => 'borrador']);

        $response = $this->actingAs($otro)->putJson("/api/pruebas/{$prueba->id}", ['titulo' => 'Hackeada']);

        $response->assertForbidden();
    }
}
