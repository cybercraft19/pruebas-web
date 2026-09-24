<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruebaGestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_puede_archivar_su_prueba(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $response = $this->actingAs($evaluador)->postJson("/api/pruebas/{$prueba->id}/archivar");

        $response->assertOk();
        $this->assertDatabaseHas('pruebas', ['id' => $prueba->id, 'estado' => 'archivada']);
    }

    public function test_evaluador_no_puede_archivar_prueba_ajena(): void
    {
        $dueno = User::factory()->create(['role' => 'evaluador']);
        $otro = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $dueno->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $response = $this->actingAs($otro)->postJson("/api/pruebas/{$prueba->id}/archivar");

        $response->assertForbidden();
        $this->assertDatabaseHas('pruebas', ['id' => $prueba->id, 'estado' => 'publicada']);
    }

    public function test_prueba_archivada_no_aparece_como_disponible_para_estudiantes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $this->actingAs($evaluador)->postJson("/api/pruebas/{$prueba->id}/archivar")->assertOk();

        $response = $this->actingAs($estudiante)->getJson('/api/pruebas-publicadas');

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_un_estudiante_de_primaria_no_ve_las_pruebas_de_bachillerato(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'grado' => 4]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'TMT', 'estado' => 'publicada', 'requiere_bachillerato' => false]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'CHASIDE', 'estado' => 'publicada', 'requiere_bachillerato' => true]);

        $titulos = $this->actingAs($estudiante)->getJson('/api/pruebas-publicadas')->json('*.titulo');

        $this->assertSame(['TMT'], $titulos);
    }

    public function test_un_estudiante_de_bachillerato_ve_todas_las_pruebas(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'grado' => 9]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'TMT', 'estado' => 'publicada', 'requiere_bachillerato' => false]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'CHASIDE', 'estado' => 'publicada', 'requiere_bachillerato' => true]);

        $titulos = $this->actingAs($estudiante)->getJson('/api/pruebas-publicadas')->json('*.titulo');

        $this->assertCount(2, $titulos);
    }

    public function test_un_estudiante_sin_grado_registrado_ve_todas_las_pruebas(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'grado' => null]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'TMT', 'estado' => 'publicada', 'requiere_bachillerato' => false]);
        Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'CHASIDE', 'estado' => 'publicada', 'requiere_bachillerato' => true]);

        $titulos = $this->actingAs($estudiante)->getJson('/api/pruebas-publicadas')->json('*.titulo');

        $this->assertCount(2, $titulos);
    }
}
