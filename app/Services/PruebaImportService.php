<?php

namespace App\Services;

use App\Imports\PruebaTemplateImport;
use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PruebaImportService
{
    private const TIPOS_PUNTUACION = ['SUMA_PONDERADA', 'PROMEDIO', 'CONTEO'];

    private const TIPOS_PREGUNTA = ['escala', 'opcion_multiple', 'verdadero_falso'];

    public function importar(string $filePath, int $evaluadorId, ?string $pdfReferenciaPath = null): Prueba
    {
        [$pruebaData, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows] = $this->leerYValidar($filePath);

        return DB::transaction(function () use ($pruebaData, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows, $evaluadorId, $pdfReferenciaPath) {
            $prueba = Prueba::create([
                'creado_por' => $evaluadorId,
                'titulo' => trim((string) $pruebaData['titulo']),
                'instrucciones' => $pruebaData['instrucciones'] ?? null,
                'tiempo_max_minutos' => ! empty($pruebaData['tiempo_max_minutos']) ? (int) $pruebaData['tiempo_max_minutos'] : null,
                'estado' => 'borrador',
                'pdf_referencia_path' => $pdfReferenciaPath,
            ]);

            $this->poblar($prueba, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows);

            return $prueba->load(['categorias.interpretaciones', 'preguntas.opciones']);
        });
    }

    /**
     * Reemplaza por completo las categorías/preguntas/opciones de una prueba existente
     * a partir de un nuevo archivo. Solo debe usarse cuando la prueba todavía no tiene
     * intentos (el llamador es responsable de esa verificación).
     */
    public function reemplazar(Prueba $prueba, string $filePath, ?string $pdfReferenciaPath = null): Prueba
    {
        [$pruebaData, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows] = $this->leerYValidar($filePath);

        return DB::transaction(function () use ($prueba, $pruebaData, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows, $pdfReferenciaPath) {
            $preguntaIds = $prueba->preguntas()->pluck('id');
            OpcionRespuesta::whereIn('pregunta_id', $preguntaIds)->delete();
            Pregunta::where('prueba_id', $prueba->id)->delete();
            CategoriaEvaluacion::where('prueba_id', $prueba->id)->delete();

            $prueba->update([
                'titulo' => trim((string) $pruebaData['titulo']),
                'instrucciones' => $pruebaData['instrucciones'] ?? null,
                'tiempo_max_minutos' => ! empty($pruebaData['tiempo_max_minutos']) ? (int) $pruebaData['tiempo_max_minutos'] : null,
                'pdf_referencia_path' => $pdfReferenciaPath ?? $prueba->pdf_referencia_path,
            ]);

            $this->poblar($prueba, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows);

            return $prueba->load(['categorias.interpretaciones', 'preguntas.opciones']);
        });
    }

    /**
     * La hoja 0 ("Instrucciones") es solo texto de ayuda para quien completa
     * la plantilla y no se lee acá. Los datos empiezan en la hoja 1.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>, 3: array<int, array<string, mixed>>, 4: array<int, array<string, mixed>>}
     */
    private function leerYValidar(string $filePath): array
    {
        $sheets = Excel::toArray(new PruebaTemplateImport, $filePath);

        $pruebaRows = $sheets[1] ?? [];
        $categoriaRows = array_values(array_filter($sheets[2] ?? [], fn ($row) => ! empty(array_filter($row))));
        $preguntaRows = array_values(array_filter($sheets[3] ?? [], fn ($row) => ! empty(array_filter($row))));
        $opcionRows = array_values(array_filter($sheets[4] ?? [], fn ($row) => ! empty(array_filter($row))));
        $interpretacionRows = array_values(array_filter($sheets[5] ?? [], fn ($row) => ! empty(array_filter($row))));

        $errores = $this->validar($pruebaRows, $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows);

        if (! empty($errores)) {
            throw ValidationException::withMessages(['plantilla' => $errores]);
        }

        return [$pruebaRows[0], $categoriaRows, $preguntaRows, $opcionRows, $interpretacionRows];
    }

    /**
     * @return array<int, string>
     */
    private function validar(array $pruebaRows, array $categoriaRows, array $preguntaRows, array $opcionRows, array $interpretacionRows): array
    {
        $errores = [];

        if (empty($pruebaRows[0]['titulo'] ?? null)) {
            $errores[] = 'La hoja "Prueba" debe tener una fila con al menos el campo "titulo".';
        }

        if (empty($categoriaRows)) {
            $errores[] = 'La hoja "Categorias" no tiene filas.';
        }

        foreach ($categoriaRows as $i => $row) {
            $n = $i + 2;
            if (empty($row['nombre'])) {
                $errores[] = "Categorias fila {$n}: falta 'nombre'.";
            }
            if (empty($row['tipo_puntuacion']) || ! in_array(strtoupper((string) $row['tipo_puntuacion']), self::TIPOS_PUNTUACION, true)) {
                $errores[] = "Categorias fila {$n}: 'tipo_puntuacion' debe ser uno de: ".implode(', ', self::TIPOS_PUNTUACION).'.';
            }
        }

        if (empty($preguntaRows)) {
            $errores[] = 'La hoja "Preguntas" no tiene filas.';
        }

        $categoriaNombres = collect($categoriaRows)
            ->pluck('nombre')
            ->filter()
            ->map(fn ($nombre) => mb_strtolower(trim((string) $nombre)))
            ->all();

        foreach ($preguntaRows as $i => $row) {
            $n = $i + 2;
            if (empty($row['numero']) && $row['numero'] !== 0) {
                $errores[] = "Preguntas fila {$n}: falta 'numero'.";
            }
            if (empty($row['texto'])) {
                $errores[] = "Preguntas fila {$n}: falta 'texto'.";
            }
            if (empty($row['tipo']) || ! in_array((string) $row['tipo'], self::TIPOS_PREGUNTA, true)) {
                $errores[] = "Preguntas fila {$n}: 'tipo' debe ser uno de: ".implode(', ', self::TIPOS_PREGUNTA).'.';
            }
            if (! empty($row['categoria']) && ! in_array(mb_strtolower(trim((string) $row['categoria'])), $categoriaNombres, true)) {
                $errores[] = "Preguntas fila {$n}: la categoria '{$row['categoria']}' no existe en la hoja Categorias.";
            }
        }

        if (empty($opcionRows)) {
            $errores[] = 'La hoja "Opciones" no tiene filas.';
        }

        $preguntaNumeros = collect($preguntaRows)
            ->pluck('numero')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->all();

        foreach ($opcionRows as $i => $row) {
            $n = $i + 2;
            if (empty($row['pregunta_numero']) || ! in_array((string) $row['pregunta_numero'], $preguntaNumeros, true)) {
                $errores[] = "Opciones fila {$n}: 'pregunta_numero' no corresponde a ninguna pregunta de la hoja Preguntas.";
            }
            if (! isset($row['texto']) || $row['texto'] === '') {
                $errores[] = "Opciones fila {$n}: falta 'texto'.";
            }
            if (! isset($row['peso']) || ! is_numeric($row['peso'])) {
                $errores[] = "Opciones fila {$n}: 'peso' debe ser numerico.";
            }
        }

        foreach ($interpretacionRows as $i => $row) {
            $n = $i + 2;
            if (empty($row['categoria']) || ! in_array(mb_strtolower(trim((string) $row['categoria'])), $categoriaNombres, true)) {
                $errores[] = "Interpretaciones fila {$n}: la categoria '".($row['categoria'] ?? '')."' no existe en la hoja Categorias.";
            }
            if (! isset($row['valor_min']) || ! is_numeric($row['valor_min'])) {
                $errores[] = "Interpretaciones fila {$n}: 'valor_min' debe ser numerico.";
            }
            if (! isset($row['valor_max']) || ! is_numeric($row['valor_max'])) {
                $errores[] = "Interpretaciones fila {$n}: 'valor_max' debe ser numerico.";
            }
            if (empty($row['etiqueta'])) {
                $errores[] = "Interpretaciones fila {$n}: falta 'etiqueta'.";
            }
        }

        return $errores;
    }

    private function poblar(Prueba $prueba, array $categoriaRows, array $preguntaRows, array $opcionRows, array $interpretacionRows = []): void
    {
        $categoriaIdsPorNombre = [];
        foreach ($categoriaRows as $orden => $row) {
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => trim((string) $row['nombre']),
                'tipo_puntuacion' => strtoupper((string) $row['tipo_puntuacion']),
                'orden' => $orden + 1,
            ]);

            $categoriaIdsPorNombre[mb_strtolower(trim((string) $row['nombre']))] = $categoria->id;
        }

        $preguntaIdsPorNumero = [];
        foreach ($preguntaRows as $row) {
            $categoriaId = ! empty($row['categoria'])
                ? ($categoriaIdsPorNombre[mb_strtolower(trim((string) $row['categoria']))] ?? null)
                : null;

            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => $categoriaId,
                'texto' => trim((string) $row['texto']),
                'tipo' => $row['tipo'],
                'orden' => (int) $row['numero'],
            ]);

            $preguntaIdsPorNumero[(string) $row['numero']] = $pregunta->id;
        }

        foreach ($opcionRows as $orden => $row) {
            OpcionRespuesta::create([
                'pregunta_id' => $preguntaIdsPorNumero[(string) $row['pregunta_numero']],
                'texto' => (string) $row['texto'],
                'peso' => (float) $row['peso'],
                'orden' => isset($row['orden']) && $row['orden'] !== '' ? (int) $row['orden'] : $orden + 1,
            ]);
        }

        foreach ($interpretacionRows as $row) {
            $categoriaId = $categoriaIdsPorNombre[mb_strtolower(trim((string) $row['categoria']))] ?? null;

            if (! $categoriaId) {
                continue;
            }

            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoriaId,
                'valor_min' => (float) $row['valor_min'],
                'valor_max' => (float) $row['valor_max'],
                'etiqueta' => trim((string) $row['etiqueta']),
                'recomendacion' => ! empty($row['recomendacion']) ? trim((string) $row['recomendacion']) : null,
            ]);
        }
    }
}
