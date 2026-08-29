<?php

namespace App\Http\Controllers\Api;

use App\Exports\EstudiantesExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class EstudianteController extends Controller
{
    public function index()
    {
        return User::query()->where('role', 'estudiante')->get(['id', 'name', 'email', 'created_at']);
    }

    public function exportar()
    {
        return Excel::download(new EstudiantesExport, 'estudiantes.xlsx');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $estudiante = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'estudiante',
        ]);

        return response()->json($estudiante, 201);
    }

    public function destroy(User $estudiante)
    {
        abort_unless($estudiante->role === 'estudiante', 404);

        $estudiante->delete();

        return response()->noContent();
    }
}
