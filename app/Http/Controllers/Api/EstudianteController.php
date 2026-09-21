<?php

namespace App\Http\Controllers\Api;

use App\Exports\EstudiantesExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EstudianteController extends Controller
{
    public function index()
    {
        return User::query()
            ->where('role', 'estudiante')
            ->where('creado_por', Auth::id())
            ->get(['id', 'name', 'email', 'cedula', 'telefono', 'fecha_nacimiento', 'acudiente_nombre', 'acudiente_telefono', 'created_at']);
    }

    public function exportar()
    {
        return Excel::download(new EstudiantesExport(Auth::id()), 'estudiantes.xlsx');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cedula' => ['nullable', 'string', 'max:50', 'unique:users,cedula'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'acudiente_nombre' => ['nullable', 'string', 'max:255'],
            'acudiente_telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $estudiante = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'estudiante',
            'creado_por' => Auth::id(),
        ]);

        return response()->json($estudiante, 201);
    }

    public function update(Request $request, User $estudiante)
    {
        $this->authorizeAcceso($estudiante);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($estudiante->id)],
            'cedula' => ['nullable', 'string', 'max:50', Rule::unique('users', 'cedula')->ignore($estudiante->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'acudiente_nombre' => ['nullable', 'string', 'max:255'],
            'acudiente_telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $estudiante->update($data);

        return $estudiante;
    }

    public function resetPassword(Request $request, User $estudiante)
    {
        $this->authorizeAcceso($estudiante);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $estudiante->update(['password' => Hash::make($data['password'])]);

        return response()->noContent();
    }

    public function destroy(User $estudiante)
    {
        $this->authorizeAcceso($estudiante);

        $estudiante->delete();

        return response()->noContent();
    }

    private function authorizeAcceso(User $estudiante): void
    {
        abort_unless($estudiante->role === 'estudiante', 404);
        abort_unless($estudiante->creado_por === Auth::id(), 403);
    }
}
