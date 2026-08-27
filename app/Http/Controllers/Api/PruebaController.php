<?php

namespace App\Http\Controllers\Api;

use App\Exports\PruebaTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Prueba;
use App\Services\PruebaImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return $prueba->load(['categorias.interpretaciones', 'preguntas.opciones']);
    }

    public function plantilla()
    {
        return Excel::download(new PruebaTemplateExport, 'plantilla-prueba.xlsx');
    }

    public function importar(Request $request, PruebaImportService $importService)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
            'pdf_referencia' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $pdfPath = null;
        if ($request->hasFile('pdf_referencia')) {
            $pdfPath = $request->file('pdf_referencia')->store('pruebas-referencia', 'local');
        }

        $prueba = $importService->importar(
            $data['archivo']->getRealPath(),
            Auth::id(),
            $pdfPath,
        );

        return response()->json($prueba, 201);
    }

    public function publicar(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        $prueba->update(['estado' => 'publicada']);

        return $prueba;
    }

    public function publicadas()
    {
        return Prueba::query()
            ->where('estado', 'publicada')
            ->withCount('preguntas')
            ->get(['id', 'titulo', 'instrucciones', 'tiempo_max_minutos']);
    }

    public function resultados(Prueba $prueba)
    {
        $this->authorizeAcceso($prueba);

        return $prueba->intentos()
            ->where('estado', 'finalizado')
            ->with(['estudiante:id,name,email', 'resultados.categoria'])
            ->get()
            ->map(fn ($intento) => [
                'intento_id' => $intento->id,
                'estudiante' => $intento->estudiante->only(['id', 'name', 'email']),
                'finalizado_at' => $intento->finalizado_at,
                'resultados' => $intento->resultados->map(fn ($resultado) => [
                    'categoria' => $resultado->categoria->nombre,
                    'puntaje' => $resultado->puntaje,
                    'etiqueta' => $resultado->etiqueta_interpretacion,
                ]),
            ]);
    }

    private function authorizeAcceso(Prueba $prueba): void
    {
        abort_unless($prueba->creado_por === Auth::id(), 403);
    }
}
