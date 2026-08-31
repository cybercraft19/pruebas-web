<?php

namespace App\Services;

use App\Models\CategoriaEvaluacion;
use App\Models\Intento;
use App\Models\ResultadoInforme;
use Illuminate\Support\Collection;

class ScoringService
{
    /**
     * @return Collection<int, ResultadoInforme>
     */
    public function calcular(Intento $intento): Collection
    {
        $intento->loadMissing([
            'prueba.categorias.preguntas',
            'prueba.categorias.interpretaciones',
            'respuestas.opcion',
            'respuestas.pregunta',
        ]);

        $pesoPorPregunta = $intento->respuestas->mapWithKeys(
            fn ($respuesta) => [$respuesta->pregunta_id => (float) $respuesta->opcion->peso]
        );

        // Preguntas "compartidas" (sin categoría propia): la opción elegida
        // decide a qué categoría suma, no la pregunta. Se usa en tests tipo
        // VAK donde cada pregunta puede aportar a canales distintos según
        // la respuesta (ej. estilos de aprendizaje).
        $sumaCompartidaPorCategoria = $intento->respuestas
            ->filter(fn ($respuesta) => $respuesta->pregunta->categoria_evaluacion_id === null && $respuesta->opcion->categoria_evaluacion_id !== null)
            ->groupBy('opcion.categoria_evaluacion_id')
            ->map(fn ($grupo) => $grupo->sum(fn ($respuesta) => (float) $respuesta->opcion->peso));

        return $intento->prueba->categorias->map(
            fn (CategoriaEvaluacion $categoria) => $this->calcularCategoria($intento, $categoria, $pesoPorPregunta, (float) $sumaCompartidaPorCategoria->get($categoria->id, 0))
        );
    }

    private function calcularCategoria(Intento $intento, CategoriaEvaluacion $categoria, Collection $pesoPorPregunta, float $sumaCompartida = 0): ResultadoInforme
    {
        $preguntaIds = $categoria->preguntas->pluck('id');

        $suma = $preguntaIds->sum(fn ($id) => $pesoPorPregunta->get($id, 0)) + $sumaCompartida;

        // CONTEO usa la misma suma que SUMA_PONDERADA: al cargar la plantilla con
        // peso 1 por opción "cuenta", sumar los pesos ya produce el conteo.
        $puntaje = $categoria->tipo_puntuacion === 'PROMEDIO' && $preguntaIds->isNotEmpty()
            ? round($suma / $preguntaIds->count(), 2)
            : round($suma, 2);

        $interpretacion = $categoria->interpretaciones
            ->first(fn ($interpretacion) => $puntaje >= $interpretacion->valor_min && $puntaje <= $interpretacion->valor_max);

        return ResultadoInforme::updateOrCreate(
            ['intento_id' => $intento->id, 'categoria_evaluacion_id' => $categoria->id],
            [
                'puntaje' => $puntaje,
                'etiqueta_interpretacion' => $interpretacion?->etiqueta,
                'recomendacion' => $interpretacion?->recomendacion,
            ]
        );
    }
}
