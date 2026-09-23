<?php

namespace App\Services;

use App\Models\Intento;

/**
 * Arma los datos de un intento finalizado, listos para que cualquier plantilla de informe
 * (todavía sin definir) los consuma directo, sin tener que volver a consultar la base ni
 * reimplementar el cálculo por tipo de prueba (cuestionario, TMT, rejilla).
 */
class InformeService
{
    /**
     * Payload completo para el informe: identifica al estudiante y a la prueba, y trae los
     * resultados en dos formas — "resumen" (filas simples, ya usadas en la vista del
     * evaluador) y "grafica" (con las bandas de interpretación completas, para dibujar dónde
     * cayó el puntaje dentro de la escala).
     *
     * @return array<string, mixed>
     */
    public function generar(Intento $intento): array
    {
        $intento->loadMissing([
            'estudiante', 'prueba.categorias.interpretaciones', 'resultados.categoria',
            'tmtResultados', 'rejillaResultados', 'firmante',
        ]);

        return [
            'estudiante' => $intento->estudiante->only([
                'id', 'name', 'email', 'cedula', 'fecha_nacimiento', 'acudiente_nombre', 'acudiente_telefono',
            ]),
            'prueba' => $intento->prueba->only(['id', 'titulo', 'tipo', 'instrucciones']),
            'finalizado_at' => $intento->finalizado_at,
            'firmado_at' => $intento->firmado_at,
            'firmado_por' => $intento->firmante?->only(['id', 'name']),
            'resumen' => $this->resumenDe($intento),
            'grafica' => $this->graficaDe($intento),
        ];
    }

    /**
     * Resultado de un intento como filas homogéneas (concepto, valor, interpretación,
     * recomendación), sin importar el tipo de prueba. Usado también en la vista del
     * evaluador (GET /api/estudiantes/{id}), que no necesita las bandas para graficar.
     *
     * @return array<int, array{concepto: string, valor: string, interpretacion: ?string, recomendacion: ?string}>
     */
    public function resumenDe(Intento $intento): array
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

    /**
     * Datos listos para graficar. En cuestionarios: el puntaje de cada categoría más todas
     * sus bandas (etiqueta + rango), para dibujar una barra/gauge que ubique el puntaje
     * dentro de la escala completa. En TMT/rejilla el resultado ya es pasa/no-pasa por
     * tiempo o umbral, así que se entrega el límite oficial en vez de bandas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function graficaDe(Intento $intento): array
    {
        return match ($intento->prueba->tipo) {
            'tmt' => $intento->tmtResultados->map(fn ($resultado) => [
                'concepto' => "Parte {$resultado->parte}",
                'valor' => $resultado->tiempo_segundos,
                'unidad' => 'segundos',
                'limite_oficial' => $resultado->parte === 'A' ? 100 : 300,
                'aprobado' => $resultado->completado,
            ])->values()->all(),
            'rejilla' => $intento->rejillaResultados->map(fn ($resultado) => [
                'concepto' => $resultado->variante === 'caballo' ? 'Rejilla del caballo (Núñez Nieto)' : 'Rejilla estándar (Harris y Harris)',
                'valor' => $resultado->aciertos,
                'unidad' => 'números señalados',
                'umbral_buen_nivel' => 20,
                'maximo' => 100,
                'aprobado' => $resultado->aciertos >= 20,
            ])->values()->all(),
            default => $intento->resultados->map(fn ($resultado) => [
                'concepto' => $resultado->categoria->nombre,
                'valor' => (float) $resultado->puntaje,
                'etiqueta' => $resultado->etiqueta_interpretacion,
                'bandas' => $resultado->categoria->interpretaciones->map(fn ($banda) => [
                    'etiqueta' => $banda->etiqueta,
                    'valor_min' => (float) $banda->valor_min,
                    'valor_max' => (float) $banda->valor_max,
                ])->values()->all(),
            ])->values()->all(),
        };
    }
}
