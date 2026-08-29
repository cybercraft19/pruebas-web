<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const MENSAJES_ESTADO = [
        Password::INVALID_TOKEN => 'El enlace de recuperación es inválido o ya fue usado.',
        Password::INVALID_USER => 'No encontramos una cuenta con ese correo.',
        Password::RESET_THROTTLED => 'Ya pediste un enlace hace poco. Esperá un minuto y volvé a intentar.',
    ];

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Mismo mensaje exista o no la cuenta, para no revelar qué correos están registrados.
        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [self::MENSAJES_ESTADO[$status] ?? 'No se pudo restablecer la contraseña.'],
            ]);
        }

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
