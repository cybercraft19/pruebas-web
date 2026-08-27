<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
