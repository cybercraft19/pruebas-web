<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['intento_id', 'variante', 'aciertos', 'errores'])]
class RejillaResultado extends Model
{
    /**
     * Usado solo si no se conoce la edad del estudiante (fecha_nacimiento sin cargar).
     */
    public const ACIERTOS_BUEN_NIVEL = 20;

    /**
     * Bandas de interpretación por edad, transcritas del ejemplo de informe oficial
     * (docs/Ejemplo informe con todas las pruebas de 6 a 11.docx). Cada tabla es una
     * lista de [tope_maximo_de_la_banda, etiqueta], en orden ascendente; la última
     * banda de cada tabla no tiene tope (cubre "más de X").
     *
     * @var array<int, array{edad_max: int, bandas: array<int, array{0: int, 1: string}>}>
     */
    private const BANDAS_POR_EDAD = [
        ['edad_max' => 8, 'bandas' => [
            [7, 'Deficiente / Alerta: ritmo significativamente lento, alta tendencia a la distracción o problemas de seguimiento visual.'],
            [12, 'Normal-Bajo: le cuesta mantener el foco de forma constante bajo presión de tiempo.'],
            [18, 'Promedio / Normal: rendimiento esperado para su etapa de maduración escolar.'],
            [PHP_INT_MAX, 'Alto / Excelente: gran agilidad visual y madurez en mecanismos de concentración.'],
        ]],
        ['edad_max' => 12, 'bandas' => [
            [11, 'Deficiente / Bajo: dificultad para filtrar estímulos irrelevantes o velocidad de procesamiento lenta.'],
            [17, 'Normal-Bajo: concentración intermitente; se satura con facilidad ante la densidad de la rejilla.'],
            [25, 'Promedio / Normal: capacidad focal óptima para su edad. Nivel estándar.'],
            [32, 'Alto: buena resistencia a la fatiga escolar inmediata.'],
            [PHP_INT_MAX, 'Excelente / Superior: excelente velocidad psicomotriz y control de la atención selectiva.'],
        ]],
        ['edad_max' => PHP_INT_MAX, 'bandas' => [
            [17, 'Bajo: fatiga cognitiva temprana o problemas de atención sostenida bajo presión.'],
            [23, 'Normal-Bajo: ejecución promedio-baja, ritmo pausado.'],
            [30, 'Promedio / Normal: es el estándar esperado para adolescentes y adultos jóvenes en tareas administrativas o académicas.'],
            [38, 'Alto: destacada velocidad visual y foco libre de distracciones.'],
            [PHP_INT_MAX, 'Excelente: nivel superior (común en estudiantes de alto rendimiento o atletas jóvenes).'],
        ]],
    ];

    protected $appends = ['nivel'];

    /**
     * @return Attribute<string, never>
     */
    protected function nivel(): Attribute
    {
        return Attribute::get(function () {
            if ($this->edad() === null) {
                return $this->aciertos >= self::ACIERTOS_BUEN_NIVEL
                    ? 'Buen nivel de concentración'
                    : 'Necesita entrenar la atención';
            }

            foreach ($this->tablaAplicable() as [$tope, $etiqueta]) {
                if ($this->aciertos <= $tope) {
                    return $etiqueta;
                }
            }

            return null; // inalcanzable: la última banda siempre tiene tope PHP_INT_MAX
        });
    }

    /**
     * Bandas [valor_min, valor_max, etiqueta] de la tabla de la edad del estudiante, para
     * graficar dónde cayó el puntaje dentro de la escala (igual que un cuestionario). Null
     * si no se conoce la edad.
     *
     * @return ?array<int, array{valor_min: int, valor_max: int, etiqueta: string}>
     */
    public function bandasAplicables(): ?array
    {
        if ($this->edad() === null) {
            return null;
        }

        $anterior = -1;

        return collect($this->tablaAplicable())->map(function ($banda) use (&$anterior) {
            [$tope, $etiqueta] = $banda;
            $fila = ['valor_min' => $anterior + 1, 'valor_max' => $tope, 'etiqueta' => $etiqueta];
            $anterior = $tope;

            return $fila;
        })->all();
    }

    private function edad(): ?int
    {
        return $this->intento?->estudiante?->fecha_nacimiento?->age;
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    private function tablaAplicable(): array
    {
        $edad = $this->edad();

        $tabla = collect(self::BANDAS_POR_EDAD)->first(fn ($t) => $edad <= $t['edad_max']) ?? end(self::BANDAS_POR_EDAD);

        return $tabla['bandas'];
    }

    /**
     * @return BelongsTo<Intento, $this>
     */
    public function intento(): BelongsTo
    {
        return $this->belongsTo(Intento::class);
    }
}
