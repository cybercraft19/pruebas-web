<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_link_pointing_to_the_frontend(): void
    {
        Notification::fake();

        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->postJson('/api/forgot-password', ['email' => $evaluador->email]);

        $response->assertOk();

        Notification::assertSentTo($evaluador, ResetPassword::class, function (ResetPassword $notification) use ($evaluador) {
            $url = $notification->toMail($evaluador)->actionUrl;

            return str_contains($url, '/app/reset-password.html')
                && str_contains($url, 'token=')
                && str_contains($url, 'email='.urlencode($evaluador->email));
        });
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        $response = $this->postJson('/api/forgot-password', ['email' => 'no-existe@demo.com']);

        $response->assertOk();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $token = Password::createToken($evaluador);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $evaluador->email,
            'password' => 'nueva-password-123',
            'password_confirmation' => 'nueva-password-123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('nueva-password-123', $evaluador->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'token-invalido',
            'email' => $evaluador->email,
            'password' => 'nueva-password-123',
            'password_confirmation' => 'nueva-password-123',
        ]);

        $response->assertUnprocessable();
    }
}
