<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registrarEstudiante(Request $request)
    {
        $evaluador = User::where('role', 'evaluador')->first();
        abort_unless($evaluador, 422, 'No hay ningún evaluador configurado en el sistema todavía.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cedula' => ['required', 'string', 'max:50', 'unique:users,cedula'],
            'telefono' => ['required', 'string', 'max:30'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'acudiente_nombre' => ['required', 'string', 'max:255'],
            'acudiente_telefono' => ['required', 'string', 'max:30'],
        ]);

        $estudiante = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'estudiante',
            'creado_por' => $evaluador->id,
        ]);

        Auth::login($estudiante);
        $request->session()->regenerate();

        return response()->json(['user' => $estudiante], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
