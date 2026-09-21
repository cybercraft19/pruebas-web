<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_can_login_with_valid_credentials(): void
    {
        $evaluador = User::factory()->create([
            'role' => 'evaluador',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
            'email' => $evaluador->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($evaluador);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $evaluador = User::factory()->create([
            'role' => 'evaluador',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
            'email' => $evaluador->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/logout');

        $response->assertNoContent();

        // auth:sanctum switches the default guard to "sanctum" for the remainder of
        // the process, so the "web" guard must be asserted explicitly here.
        $this->assertGuest('web');
    }

    public function test_evaluador_can_manage_estudiantes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->postJson('/api/estudiantes', [
            'name' => 'Estudiante Uno',
            'email' => 'estudiante1@test.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'estudiante1@test.com',
            'role' => 'estudiante',
        ]);
    }

    public function test_evaluador_puede_exportar_estudiantes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);

        $response = $this->actingAs($evaluador)->get('/api/estudiantes/exportar');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_evaluador_puede_eliminar_su_estudiante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);

        $response = $this->actingAs($evaluador)->deleteJson("/api/estudiantes/{$estudiante->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $estudiante->id]);
    }

    public function test_evaluador_no_puede_eliminar_a_otro_evaluador_via_endpoint_de_estudiantes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $otroEvaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->deleteJson("/api/estudiantes/{$otroEvaluador->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $otroEvaluador->id]);
    }

    public function test_evaluador_no_ve_ni_puede_eliminar_estudiantes_de_otro_evaluador(): void
    {
        $evaluadorA = User::factory()->create(['role' => 'evaluador']);
        $evaluadorB = User::factory()->create(['role' => 'evaluador']);
        $estudianteDeB = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluadorB->id]);

        $listado = $this->actingAs($evaluadorA)->getJson('/api/estudiantes');
        $listado->assertOk();
        $this->assertFalse(collect($listado->json())->contains('id', $estudianteDeB->id));

        $borrado = $this->actingAs($evaluadorA)->deleteJson("/api/estudiantes/{$estudianteDeB->id}");
        $borrado->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $estudianteDeB->id]);
    }

    public function test_evaluador_puede_editar_los_datos_de_su_estudiante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id, 'name' => 'Nombre Viejo']);

        $response = $this->actingAs($evaluador)->putJson("/api/estudiantes/{$estudiante->id}", [
            'name' => 'Nombre Corregido',
            'email' => $estudiante->email,
            'cedula' => '123456',
            'telefono' => '3001112233',
            'acudiente_nombre' => 'Tutor Corregido',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $estudiante->id,
            'name' => 'Nombre Corregido',
            'cedula' => '123456',
            'telefono' => '3001112233',
            'acudiente_nombre' => 'Tutor Corregido',
        ]);
    }

    public function test_evaluador_no_puede_editar_estudiante_de_otro_evaluador(): void
    {
        $evaluadorA = User::factory()->create(['role' => 'evaluador']);
        $evaluadorB = User::factory()->create(['role' => 'evaluador']);
        $estudianteDeB = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluadorB->id]);

        $response = $this->actingAs($evaluadorA)->putJson("/api/estudiantes/{$estudianteDeB->id}", [
            'name' => 'Hackeado',
            'email' => $estudianteDeB->email,
        ]);

        $response->assertForbidden();
    }

    public function test_no_se_puede_editar_un_estudiante_con_la_cedula_de_otro(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id, 'cedula' => '999']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id, 'cedula' => '111']);

        $response = $this->actingAs($evaluador)->putJson("/api/estudiantes/{$estudiante->id}", [
            'name' => $estudiante->name,
            'email' => $estudiante->email,
            'cedula' => '999',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cedula');
    }

    public function test_evaluador_puede_resetear_password_de_su_estudiante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id, 'password' => bcrypt('vieja12345')]);

        $response = $this->actingAs($evaluador)->postJson("/api/estudiantes/{$estudiante->id}/reset-password", [
            'password' => 'nueva12345',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('nueva12345', $estudiante->fresh()->password));
    }

    public function test_estudiante_cannot_manage_estudiantes(): void
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $response = $this->actingAs($estudiante)->getJson('/api/estudiantes');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_un_curso_entero_puede_iniciar_sesion_desde_la_misma_ip(): void
    {
        $estudiantes = User::factory()->count(20)->create(['role' => 'estudiante', 'password' => bcrypt('password123')]);

        foreach ($estudiantes as $estudiante) {
            $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
                'email' => $estudiante->email,
                'password' => 'password123',
            ])->assertOk();
        }
    }

    public function test_los_intentos_fallidos_de_una_cuenta_no_bloquean_a_las_demas(): void
    {
        $victima = User::factory()->create(['role' => 'estudiante', 'password' => bcrypt('password123')]);
        $otro = User::factory()->create(['role' => 'estudiante', 'password' => bcrypt('password123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
                'email' => $victima->email,
                'password' => 'incorrecta',
            ])->assertUnprocessable();
        }

        $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
            'email' => $victima->email,
            'password' => 'password123',
        ])->assertStatus(429);

        $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
            'email' => $otro->email,
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $evaluador = User::factory()->create([
            'role' => 'evaluador',
            'password' => bcrypt('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
                'email' => $evaluador->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/login', [
            'email' => $evaluador->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
