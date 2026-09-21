<?php

namespace App\Http\Controllers\Api;

use App\Exports\EstudiantesExport;
use App\Http\Controllers\Controller;
use App\Models\Intento;
use App\Models\Prueba;
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

    public function show(User $estudiante)
    {
        $this->authorizeAcceso($estudiante);

        $intentos = $estudiante->intentos()
            ->whereHas('prueba', fn ($query) => $query->where('creado_por', Auth::id()))
            ->with(['prueba:id,titulo,tipo', 'resultados.categoria', 'tmtResultados', 'rejillaResultados'])
            ->latest('id')
            ->get();

        $pendientes = Prueba::query()
            ->where('creado_por', Auth::id())
            ->where('estado', 'publicada')
            ->whereNotIn('id', $intentos->pluck('prueba_id'))
            ->orderBy('id')
            ->get(['id', 'titulo', 'tipo']);

        return [
            'estudiante' => $estudiante->only([
                'id', 'name', 'email', 'cedula', 'telefono', 'fecha_nacimiento',
                'acudiente_nombre', 'acudiente_telefono', 'consentimiento_at', 'created_at',
            ]),
            'intentos' => $intentos->map(fn (Intento $intento) => [
                'id' => $intento->id,
                'prueba' => $intento->prueba->only(['id', 'titulo', 'tipo']),
                'estado' => $intento->estado,
                'iniciado_at' => $intento->iniciado_at,
                'finalizado_at' => $intento->finalizado_at,
                'firmado' => $intento->firmado_at !== null,
                'firmado_at' => $intento->firmado_at,
                'resumen' => $intento->estado === 'finalizado' ? $this->resumenDe($intento) : [],
            ]),
            'pendientes' => $pendientes,
        ];
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

    /**
     * Resultado de un intento como filas homogéneas (concepto, valor, interpretación,
     * recomendación), sin importar si la prueba es cuestionario, TMT o rejilla.
     *
     * @return array<int, array{concepto: string, valor: string, interpretacion: ?string, recomendacion: ?string}>
     */
    private function resumenDe(Intento $intento): array
    {
        return match ($intento->prueba->tipo) {
            'tmt' => $intento->tmtResultados->map(fn ($resultado) => [
                'concepto' => "Parte {$resultado->parte}",
                'valor' => "{$resultado->tiempo_segundos} s · {$resultado->errores} errores",
                'interpretacion' => $resultado->completado ? 'Completada' : 'No superada',
                'recomendacion' => null,
            ])->values()->all(),
            'rejilla' => $intento->rejillaResultados->map(fn ($resultado) => [
                'concepto' => $resultado->variante === 'caballo' ? 'Rejilla del caballo (Núñez Nieto)' : 'Rejilla estándar (Harris y Harris)',
                'valor' => "{$resultado->aciertos} números · {$resultado->errores} errores",
                'interpretacion' => $resultado->nivel,
                'recomendacion' => null,
            ])->values()->all(),
            default => $intento->resultados->map(fn ($resultado) => [
                'concepto' => $resultado->categoria->nombre,
                'valor' => (string) round((float) $resultado->puntaje, 2),
                'interpretacion' => $resultado->etiqueta_interpretacion,
                'recomendacion' => $resultado->recomendacion,
            ])->values()->all(),
        };
    }

    private function authorizeAcceso(User $estudiante): void
    {
        abort_unless($estudiante->role === 'estudiante', 404);
        abort_unless($estudiante->creado_por === Auth::id(), 403);
    }
}
