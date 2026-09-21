<?php

namespace App\Services;

use App\Models\Prueba;
use App\Models\RejillaCelda;

class RejillaLayoutService
{
    public const VARIANTE_ESTANDAR = 'estandar';

    public const VARIANTE_CABALLO = 'caballo';

    public const COLUMNAS = 10;

    /**
     * Grillas exactas del documento "Test rejilla" (docs/Test rejilla.docx): Anexo B
     * (Harris & Harris) y Anexo C (adaptación de Núñez Nieto). Cada una son 100 números,
     * fila por fila, de 10 columnas.
     *
     * @var array<string, array<int, int>>
     */
    private const GRILLAS = [
        self::VARIANTE_ESTANDAR => [
            84, 27, 51, 78, 59, 52, 13, 85, 61, 55,
            28, 60, 92, 4, 97, 90, 31, 57, 29, 33,
            32, 96, 65, 39, 80, 77, 49, 86, 18, 70,
            76, 87, 71, 95, 98, 81, 1, 46, 88, 0,
            48, 82, 89, 47, 35, 17, 10, 42, 62, 34,
            44, 67, 93, 11, 7, 43, 72, 94, 69, 56,
            53, 79, 5, 22, 54, 74, 58, 14, 91, 2,
            6, 68, 99, 75, 26, 15, 41, 66, 20, 40,
            50, 9, 64, 8, 38, 30, 36, 45, 83, 24,
            3, 73, 21, 23, 16, 37, 25, 19, 12, 63,
        ],
        self::VARIANTE_CABALLO => [
            10, 68, 38, 90, 8, 94, 6, 65, 98, 55,
            39, 84, 9, 62, 81, 36, 43, 71, 5, 93,
            54, 11, 76, 37, 52, 7, 88, 35, 44, 61,
            80, 40, 58, 96, 69, 42, 21, 4, 83, 78,
            70, 85, 12, 41, 20, 64, 34, 67, 50, 45,
            15, 79, 19, 28, 13, 51, 3, 22, 33, 75,
            74, 29, 14, 1, 18, 31, 53, 59, 46, 49,
            63, 16, 89, 30, 27, 2, 47, 32, 23, 86,
            92, 87, 0, 17, 66, 72, 57, 25, 48, 77,
            56, 99, 82, 60, 97, 26, 95, 73, 91, 24,
        ],
    ];

    /**
     * @return array<int, int>
     */
    public function grilla(string $variante): array
    {
        return self::GRILLAS[$variante];
    }

    /**
     * Persiste las dos grillas (estándar y caballo) de una prueba de tipo rejilla.
     */
    public function generar(Prueba $prueba): void
    {
        foreach (self::GRILLAS as $variante => $numeros) {
            foreach ($numeros as $posicion => $numero) {
                RejillaCelda::create([
                    'prueba_id' => $prueba->id,
                    'variante' => $variante,
                    'posicion' => $posicion,
                    'numero' => $numero,
                ]);
            }
        }
    }
}
