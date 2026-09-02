<?php

namespace App\Services;

use App\Models\Prueba;
use App\Models\TmtNodo;

class TmtLayoutService
{
    /**
     * Posiciones fijas (% del lienzo) calcadas de la hoja oficial del Trail Making Test
     * en español (docs/SPANISH_TMT.pdf). Son siempre las mismas para toda prueba TMT,
     * en vez de generarse al azar.
     *
     * @var array<string, array<string, array{x: float, y: float}>>
     */
    private const LAYOUTS = [
        'A_practica' => [
            '1' => ['x' => 46, 'y' => 48],
            '2' => ['x' => 64, 'y' => 15],
            '3' => ['x' => 78, 'y' => 50],
            '4' => ['x' => 62, 'y' => 40],
            '5' => ['x' => 62, 'y' => 78],
            '6' => ['x' => 16, 'y' => 78],
            '7' => ['x' => 14, 'y' => 35],
            '8' => ['x' => 46, 'y' => 20],
        ],
        'A_real' => [
            '1' => ['x' => 66, 'y' => 54],
            '2' => ['x' => 42, 'y' => 65],
            '3' => ['x' => 74, 'y' => 71],
            '4' => ['x' => 68, 'y' => 32],
            '5' => ['x' => 36, 'y' => 38],
            '6' => ['x' => 54, 'y' => 45],
            '7' => ['x' => 36, 'y' => 54],
            '8' => ['x' => 22, 'y' => 68],
            '9' => ['x' => 27, 'y' => 79],
            '10' => ['x' => 35, 'y' => 64],
            '11' => ['x' => 63, 'y' => 83],
            '12' => ['x' => 13, 'y' => 85],
            '13' => ['x' => 20, 'y' => 45],
            '14' => ['x' => 10, 'y' => 58],
            '15' => ['x' => 6, 'y' => 8],
            '16' => ['x' => 20, 'y' => 22],
            '17' => ['x' => 48, 'y' => 6],
            '18' => ['x' => 45, 'y' => 25],
            '19' => ['x' => 72, 'y' => 15],
            '20' => ['x' => 58, 'y' => 14],
            '21' => ['x' => 85, 'y' => 7],
            '22' => ['x' => 82, 'y' => 32],
            '23' => ['x' => 85, 'y' => 86],
            '24' => ['x' => 78, 'y' => 53],
            '25' => ['x' => 76, 'y' => 83],
        ],
        'B_practica' => [
            '1' => ['x' => 44, 'y' => 48],
            'A' => ['x' => 68, 'y' => 13],
            '2' => ['x' => 82, 'y' => 48],
            'B' => ['x' => 62, 'y' => 42],
            '3' => ['x' => 62, 'y' => 78],
            'C' => ['x' => 20, 'y' => 78],
            '4' => ['x' => 16, 'y' => 15],
            'D' => ['x' => 44, 'y' => 18],
        ],
        'B_real' => [
            '1' => ['x' => 50, 'y' => 44],
            'A' => ['x' => 64, 'y' => 68],
            '2' => ['x' => 25, 'y' => 78],
            'B' => ['x' => 44, 'y' => 20],
            '3' => ['x' => 40, 'y' => 32],
            'C' => ['x' => 62, 'y' => 54],
            '4' => ['x' => 54, 'y' => 18],
            'D' => ['x' => 76, 'y' => 14],
            '5' => ['x' => 76, 'y' => 48],
            'E' => ['x' => 78, 'y' => 85],
            '6' => ['x' => 40, 'y' => 78],
            'F' => ['x' => 20, 'y' => 90],
            '7' => ['x' => 30, 'y' => 42],
            'G' => ['x' => 17, 'y' => 62],
            '8' => ['x' => 15, 'y' => 12],
            'H' => ['x' => 18, 'y' => 51],
            '9' => ['x' => 30, 'y' => 13],
            'I' => ['x' => 62, 'y' => 13],
            '10' => ['x' => 88, 'y' => 9],
            'J' => ['x' => 78, 'y' => 68],
            '11' => ['x' => 85, 'y' => 92],
            'K' => ['x' => 8, 'y' => 92],
            '12' => ['x' => 6, 'y' => 57],
            'L' => ['x' => 13, 'y' => 82],
            '13' => ['x' => 6, 'y' => 6],
        ],
    ];

    /**
     * Genera y persiste los nodos de práctica y reales de las partes A y B para una prueba TMT,
     * usando siempre el mismo layout (el de la hoja oficial), no uno aleatorio por prueba.
     */
    public function generar(Prueba $prueba): void
    {
        $this->crearGrupo($prueba, 'A', true, ['1', '2', '3', '4', '5', '6', '7', '8'], self::LAYOUTS['A_practica']);
        $this->crearGrupo($prueba, 'A', false, array_map('strval', range(1, 25)), self::LAYOUTS['A_real']);
        $this->crearGrupo($prueba, 'B', true, ['1', 'A', '2', 'B', '3', 'C', '4', 'D'], self::LAYOUTS['B_practica']);
        $this->crearGrupo($prueba, 'B', false, $this->secuenciaParteB(), self::LAYOUTS['B_real']);
    }

    /**
     * @return array<int, string>
     */
    private function secuenciaParteB(): array
    {
        $secuencia = [];

        for ($numero = 1; $numero <= 13; $numero++) {
            $secuencia[] = (string) $numero;

            if ($numero < 13) {
                $secuencia[] = chr(64 + $numero); // A, B, C, ... L
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
