<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroEstudianteTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Pérez',
            'email' => 'juan@correo.com',
            'password' => 'password123',
            'cedula' => '1234567890',
            'telefono' => '3001234567',
            'fecha_nacimiento' => '2012-05-10',
            'acudiente_nombre' => 'María Pérez',
            'acudiente_telefono' => '3007654321',
        ], $overrides);
    }

    public function test_un_estudiante_puede_autoregistrarse_y_queda_asociado_al_evaluador_existente(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/registro', $this->datosValidos());

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'estudiante');
        $response->assertJsonPath('user.creado_por', $evaluador->id);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@correo.com',
            'cedula' => '1234567890',
            'role' => 'estudiante',
            'creado_por' => $evaluador->id,
        ]);
    }

    public function test_el_estudiante_queda_autenticado_despues_de_registrarse(): void
    {
        User::factory()->create(['role' => 'evaluador']);

        $this->withHeader('Origin', 'http://localhost')->postJson('/api/registro', $this->datosValidos())->assertCreated();

        $this->assertAuthenticatedAs(User::where('email', 'juan@correo.com')->firstOrFail());
    }

    public function test_falla_si_no_hay_ningun_evaluador_en_el_sistema(): void
    {
        $response = $this->postJson('/api/registro', $this->datosValidos());

        $response->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_falla_si_falta_algun_dato_obligatorio(): void
    {
        User::factory()->create(['role' => 'evaluador']);

        $response = $this->postJson('/api/registro', $this->datosValidos(['cedula' => '']));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cedula');
    }

    public function test_no_se_puede_registrar_dos_veces_con_la_misma_cedula(): void
    {
        User::factory()->create(['role' => 'evaluador']);
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/registro', $this->datosValidos())->assertCreated();

        $response = $this->postJson('/api/registro', $this->datosValidos(['email' => 'otro@correo.com']));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cedula');
    }

    public function test_el_evaluador_puede_crear_un_estudiante_con_los_datos_personales_opcionales(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->postJson('/api/estudiantes', [
            'name' => 'Ana Gómez',
            'email' => 'ana@correo.com',
            'password' => 'password123',
            'cedula' => '999',
            'telefono' => '3009999999',
            'acudiente_nombre' => 'Luis Gómez',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'ana@correo.com', 'cedula' => '999', 'acudiente_nombre' => 'Luis Gómez']);
    }

    public function test_el_evaluador_puede_crear_un_estudiante_sin_los_datos_personales(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->postJson('/api/estudiantes', [
            'name' => 'Ana Gómez',
            'email' => 'ana@correo.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();
    }
}
