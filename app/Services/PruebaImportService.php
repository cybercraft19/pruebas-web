<?php

namespace App\Services;

use App\Imports\PruebaTemplateImport;
use App\Models\CategoriaEvaluacion;
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
        $sheets = Excel::toArray(new PruebaTemplateImport, $filePath);

        $pruebaRows = $sheets[0] ?? [];
        $categoriaRows = array_values(array_filter($sheets[1] ?? [], fn ($row) => ! empty(array_filter($row))));
        $preguntaRows = array_values(array_filter($sheets[2] ?? [], fn ($row) => ! empty(array_filter($row))));
        $opcionRows = array_values(array_filter($sheets[3] ?? [], fn ($row) => ! empty(array_filter($row))));

        $errores = $this->validar($pruebaRows, $categoriaRows, $preguntaRows, $opcionRows);

        if (! empty($errores)) {
            throw ValidationException::withMessages(['plantilla' => $errores]);
        }

        return $this->crear($pruebaRows[0], $categoriaRows, $preguntaRows, $opcionRows, $evaluadorId, $pdfReferenciaPath);
    }

    /**
     * @return array<int, string>
     */
    private function validar(array $pruebaRows, array $categoriaRows, array $preguntaRows, array $opcionRows): array
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

        return $errores;
    }

    private function crear(
        array $pruebaData,
        array $categoriaRows,
        array $preguntaRows,
        array $opcionRows,
        int $evaluadorId,
        ?string $pdfReferenciaPath,
    ): Prueba {
        return DB::transaction(function () use ($pruebaData, $categoriaRows, $preguntaRows, $opcionRows, $evaluadorId, $pdfReferenciaPath) {
            $prueba = Prueba::create([
                'creado_por' => $evaluadorId,
                'titulo' => trim((string) $pruebaData['titulo']),
                'instrucciones' => $pruebaData['instrucciones'] ?? null,
                'tiempo_max_minutos' => ! empty($pruebaData['tiempo_max_minutos']) ? (int) $pruebaData['tiempo_max_minutos'] : null,
                'estado' => 'borrador',
                'pdf_referencia_path' => $pdfReferenciaPath,
            ]);

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

            return $prueba->load(['categorias.interpretaciones', 'preguntas.opciones']);
        });
    }
}
