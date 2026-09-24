<?php

namespace App\Http\Controllers\Api;

use App\Exports\ResultadosExport;
use App\Http\Controllers\Controller;
use App\Models\Prueba;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PruebaController extends Controller
{
    public function index()
    {
        return Prueba::query()
            ->where('creado_por', Auth::id())
            ->withCount(['preguntas', 'categorias'])
            ->latest()
            ->get();
    }

    public function show(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        return match ($prueba->tipo) {
            'tmt' => $prueba->load('tmtNodos'),
            'rejilla' => $prueba->load('rejillaCeldas'),
            default => $prueba->load(['categorias.interpretaciones', 'preguntas.opciones']),
        };
    }

    public function update(Request $request, Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'instrucciones' => ['nullable', 'string'],
        ]);

        $prueba->update($data);

        return $prueba;
    }

    public function publicar(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        $prueba->update(['estado' => 'publicada']);

        return $prueba;
    }

    public function archivar(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        $prueba->update(['estado' => 'archivada']);

        return $prueba;
    }

    public function publicadas()
    {
        $grado = Auth::user()->grado;

        return Prueba::query()
            ->where('estado', 'publicada')
            // Sin grado registrado (cuentas antiguas creadas antes de este campo): se
            // muestran todas, para no bloquear a nadie por datos que no llegó a cargar.
            ->when($grado !== null && $grado < 6, fn ($query) => $query->where('requiere_bachillerato', false))
            ->withCount('preguntas')
            ->with(['intentos' => fn ($query) => $query->where('estudiante_id', Auth::id())])
            ->get(['id', 'tipo', 'titulo', 'instrucciones', 'tiempo_max_minutos'])
            ->map(function (Prueba $prueba) {
                $intento = $prueba->intentos->first();
                $prueba->unsetRelation('intentos');
                $prueba->mi_intento = $intento ? ['id' => $intento->id, 'estado' => $intento->estado] : null;

                return $prueba;
            });
    }

    public function resultados(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        if ($prueba->tipo === 'tmt') {
            return $prueba->intentos()
                ->where('estado', 'finalizado')
                ->with(['estudiante:id,name,email', 'tmtResultados'])
                ->get()
                ->map(fn ($intento) => [
                    'intento_id' => $intento->id,
                    'estudiante' => $intento->estudiante->only(['id', 'name', 'email']),
                    'finalizado_at' => $intento->finalizado_at,
                    'firmado' => $intento->firmado_at !== null,
                    'firmado_at' => $intento->firmado_at,
                    'tmt_resultados' => $intento->tmtResultados->map(fn ($resultado) => [
                        'parte' => $resultado->parte,
                        'tiempo_segundos' => $resultado->tiempo_segundos,
                        'errores' => $resultado->errores,
                        'completado' => $resultado->completado,
                    ]),
                ]);
        }

        if ($prueba->tipo === 'rejilla') {
            return $prueba->intentos()
                ->where('estado', 'finalizado')
                ->with(['estudiante:id,name,email', 'rejillaResultados'])
                ->get()
                ->map(fn ($intento) => [
                    'intento_id' => $intento->id,
                    'estudiante' => $intento->estudiante->only(['id', 'name', 'email']),
                    'finalizado_at' => $intento->finalizado_at,
                    'firmado' => $intento->firmado_at !== null,
                    'firmado_at' => $intento->firmado_at,
                    'rejilla_resultados' => $intento->rejillaResultados->map(fn ($resultado) => [
                        'variante' => $resultado->variante,
                        'aciertos' => $resultado->aciertos,
                        'errores' => $resultado->errores,
                        'nivel' => $resultado->nivel,
                    ]),
                ]);
        }

        return $prueba->intentos()
            ->where('estado', 'finalizado')
            ->with(['estudiante:id,name,email', 'resultados.categoria'])
            ->get()
            ->map(fn ($intento) => [
                'intento_id' => $intento->id,
                'estudiante' => $intento->estudiante->only(['id', 'name', 'email']),
                'finalizado_at' => $intento->finalizado_at,
                'firmado' => $intento->firmado_at !== null,
                'firmado_at' => $intento->firmado_at,
                'resultados' => $intento->resultados->map(fn ($resultado) => [
                    'categoria' => $resultado->categoria->nombre,
                    'puntaje' => $resultado->puntaje,
                    'etiqueta' => $resultado->etiqueta_interpretacion,
                    'recomendacion' => $resultado->recomendacion,
                ]),
            ]);
    }

    public function exportarResultados(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        $nombreArchivo = Str::slug($prueba->titulo).'-resultados.xlsx';

        return Excel::download(new ResultadosExport($prueba), $nombreArchivo);
    }

    private function authorizeAcceso(Prueba $prueba): void
    {
        abort_unless($prueba->creado_por === Auth::id(), 403);
    }
}
