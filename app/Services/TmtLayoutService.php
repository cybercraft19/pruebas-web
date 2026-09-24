<?php

namespace App\Services;

use App\Models\Prueba;
use App\Models\TmtNodo;

class TmtLayoutService
{
    /**
     * Posiciones fijas (% del lienzo) calcadas al pixel de la hoja oficial del Trail
     * Making Test en español (docs/tmt nuevo.pdf). Son siempre las mismas para toda
     * prueba TMT, en vez de generarse al azar.
     *
     * @var array<string, array<string, array{x: float, y: float}>>
     */
    private const LAYOUTS = [
        'A_practica' => [
            '1' => ['x' => 48.3, 'y' => 47.8],
            '2' => ['x' => 76.8, 'y' => 11.4],
            '3' => ['x' => 89.4, 'y' => 56.6],
            '4' => ['x' => 73.5, 'y' => 39.3],
            '5' => ['x' => 11.6, 'y' => 85.0],
            '6' => ['x' => 76.0, 'y' => 82.4],
            '7' => ['x' => 11.6, 'y' => 26.1],
            '8' => ['x' => 41.7, 'y' => 23.7],
        ],
        'A_real' => [
            '1' => ['x' => 72.2, 'y' => 53.8],
            '2' => ['x' => 51.2, 'y' => 65.4],
            '3' => ['x' => 83.9, 'y' => 76.1],
            '4' => ['x' => 83.9, 'y' => 33.5],
            '5' => ['x' => 42.1, 'y' => 33.9],
            '6' => ['x' => 54.5, 'y' => 44.8],
            '7' => ['x' => 36.3, 'y' => 54.8],
            '8' => ['x' => 17.6, 'y' => 71.2],
            '9' => ['x' => 34.1, 'y' => 81.9],
            '10' => ['x' => 36.3, 'y' => 66.8],
            '11' => ['x' => 63.2, 'y' => 81.9],
            '12' => ['x' => 14.3, 'y' => 87.9],
            '13' => ['x' => 21.3, 'y' => 41.5],
            '14' => ['x' => 10.2, 'y' => 50.0],
            '15' => ['x' => 18.3, 'y' => 13.1],
        ],
        'B_practica' => [
            '1' => ['x' => 30.6, 'y' => 69.4],
            'A' => ['x' => 70.2, 'y' => 17.6],
            '2' => ['x' => 87.8, 'y' => 69.4],
            'B' => ['x' => 61.5, 'y' => 56.6],
            '3' => ['x' => 59.2, 'y' => 86.7],
            'C' => ['x' => 13.8, 'y' => 86.7],
            '4' => ['x' => 16.7, 'y' => 17.6],
            'D' => ['x' => 41.6, 'y' => 30.5],
        ],
        'B_real' => [
            '1' => ['x' => 53.7, 'y' => 46.6],
            'A' => ['x' => 80.4, 'y' => 76.0],
            '2' => ['x' => 33.0, 'y' => 76.9],
            'B' => ['x' => 42.1, 'y' => 20.3],
            '3' => ['x' => 42.5, 'y' => 34.1],
            'C' => ['x' => 72.5, 'y' => 58.4],
            '4' => ['x' => 63.0, 'y' => 15.6],
            'D' => ['x' => 84.0, 'y' => 8.1],
            '5' => ['x' => 89.7, 'y' => 45.2],
            'E' => ['x' => 93.0, 'y' => 87.8],
            '6' => ['x' => 53.7, 'y' => 69.6],
            'F' => ['x' => 17.5, 'y' => 88.9],
            '7' => ['x' => 29.9, 'y' => 47.0],
            'G' => ['x' => 18.9, 'y' => 62.7],
            '8' => ['x' => 16.1, 'y' => 11.9],
        ],
    ];

    /**
     * Genera y persiste los nodos de práctica y reales de las partes A y B para una prueba TMT,
     * usando siempre el mismo layout (el de la hoja oficial), no uno aleatorio por prueba.
     */
    public function generar(Prueba $prueba): void
    {
        $this->crearGrupo($prueba, 'A', true, ['1', '2', '3', '4', '5', '6', '7', '8'], self::LAYOUTS['A_practica']);
        $this->crearGrupo($prueba, 'A', false, array_map('strval', range(1, 15)), self::LAYOUTS['A_real']);
        $this->crearGrupo($prueba, 'B', true, ['1', 'A', '2', 'B', '3', 'C', '4', 'D'], self::LAYOUTS['B_practica']);
        $this->crearGrupo($prueba, 'B', false, $this->secuenciaParteB(), self::LAYOUTS['B_real']);
    }

    /**
     * @return array<int, string>
     */
    private function secuenciaParteB(): array
    {
        $secuencia = [];

        for ($numero = 1; $numero <= 8; $numero++) {
            $secuencia[] = (string) $numero;

            if ($numero < 8) {
                $secuencia[] = chr(64 + $numero); // A, B, C, ... G
            }
        }

        return $secuencia;
    }

    /**
     * @param  array<int, string>  $etiquetas
     * @param  array<string, array{x: float, y: float}>  $posiciones
     */
    private function crearGrupo(Prueba $prueba, string $parte, bool $practica, array $etiquetas, array $posiciones): void
    {
        foreach ($etiquetas as $indice => $etiqueta) {
            TmtNodo::create([
                'prueba_id' => $prueba->id,
                'parte' => $parte,
                'practica' => $practica,
                'orden' => $indice + 1,
                'etiqueta' => $etiqueta,
                'pos_x' => $posiciones[$etiqueta]['x'],
                'pos_y' => $posiciones[$etiqueta]['y'],
            ]);
        }
    }
}
