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
        ]);

        $pesoPorPregunta = $intento->respuestas->mapWithKeys(
            fn ($respuesta) => [$respuesta->pregunta_id => (float) $respuesta->opcion->peso]
        );

        return $intento->prueba->categorias->map(
            fn (CategoriaEvaluacion $categoria) => $this->calcularCategoria($intento, $categoria, $pesoPorPregunta)
        );
    }

    private function calcularCategoria(Intento $intento, CategoriaEvaluacion $categoria, Collection $pesoPorPregunta): ResultadoInforme
    {
        $preguntaIds = $categoria->preguntas->pluck('id');

        $suma = $preguntaIds->sum(fn ($id) => $pesoPorPregunta->get($id, 0));

        // CONTEO usa la misma suma que SUMA_PONDERADA: al cargar la plantilla con
        // peso 1 por opción "cuenta", sumar los pesos ya produce el conteo.
        $puntaje = $categoria->tipo_puntuacion === 'PROMEDIO' && $preguntaIds->isNotEmpty()
            ? round($suma / $preguntaIds->count(), 2)
            : round($suma, 2);

        $etiqueta = $categoria->interpretaciones
            ->first(fn ($interpretacion) => $puntaje >= $interpretacion->valor_min && $puntaje <= $interpretacion->valor_max)
            ?->etiqueta;

        return ResultadoInforme::updateOrCreate(
            ['intento_id' => $intento->id, 'categoria_evaluacion_id' => $categoria->id],
            ['puntaje' => $puntaje, 'etiqueta_interpretacion' => $etiqueta]
        );
    }
}
