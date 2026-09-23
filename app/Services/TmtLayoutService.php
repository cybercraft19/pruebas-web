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
            '1' => ['x' => 50.3, 'y' => 59.8],
            '2' => ['x' => 73.0, 'y' => 15.1],
            '3' => ['x' => 90.1, 'y' => 63.8],
            '4' => ['x' => 71.4, 'y' => 48.7],
            '5' => ['x' => 76.9, 'y' => 86.0],
            '6' => ['x' => 16.8, 'y' => 87.4],
            '7' => ['x' => 14.2, 'y' => 33.9],
            '8' => ['x' => 44.4, 'y' => 29.2],
        ],
        'A_real' => [
            '1' => ['x' => 72.1, 'y' => 54.0],
            '2' => ['x' => 49.2, 'y' => 65.7],
            '3' => ['x' => 79.1, 'y' => 71.3],
            '4' => ['x' => 76.7, 'y' => 33.1],
            '5' => ['x' => 42.3, 'y' => 35.6],
            '6' => ['x' => 60.6, 'y' => 44.0],
            '7' => ['x' => 41.2, 'y' => 53.4],
            '8' => ['x' => 26.5, 'y' => 69.2],
            '9' => ['x' => 31.5, 'y' => 80.8],
            '10' => ['x' => 39.2, 'y' => 68.1],
            '11' => ['x' => 66.5, 'y' => 83.5],
            '12' => ['x' => 15.4, 'y' => 87.0],
            '13' => ['x' => 24.2, 'y' => 44.4],
            '14' => ['x' => 12.9, 'y' => 57.3],
            '15' => ['x' => 7.8, 'y' => 6.3],
            '16' => ['x' => 24.2, 'y' => 22.0],
            '17' => ['x' => 53.5, 'y' => 4.3],
            '18' => ['x' => 49.6, 'y' => 25.0],
            '19' => ['x' => 80.3, 'y' => 13.3],
            '20' => ['x' => 63.2, 'y' => 12.2],
            '21' => ['x' => 88.3, 'y' => 4.3],
            '22' => ['x' => 90.1, 'y' => 32.6],
            '23' => ['x' => 93.6, 'y' => 86.6],
            '24' => ['x' => 85.7, 'y' => 52.1],
            '25' => ['x' => 82.8, 'y' => 82.7],
        ],
        'B_practica' => [
            '1' => ['x' => 45.6, 'y' => 54.8],
            'A' => ['x' => 70.3, 'y' => 14.5],
            '2' => ['x' => 90.3, 'y' => 58.7],
            'B' => ['x' => 68.8, 'y' => 45.3],
            '3' => ['x' => 75.9, 'y' => 86.6],
            'C' => ['x' => 20.5, 'y' => 84.1],
            '4' => ['x' => 12.1, 'y' => 17.3],
            'D' => ['x' => 42.0, 'y' => 21.8],
        ],
        'B_real' => [
            '1' => ['x' => 49.3, 'y' => 42.6],
            'A' => ['x' => 67.6, 'y' => 69.7],
            '2' => ['x' => 25.6, 'y' => 77.3],
            'B' => ['x' => 42.9, 'y' => 18.3],
            '3' => ['x' => 42.9, 'y' => 30.0],
            'C' => ['x' => 64.1, 'y' => 54.5],
            '4' => ['x' => 54.6, 'y' => 16.4],
            'D' => ['x' => 80.3, 'y' => 12.9],
            '5' => ['x' => 78.3, 'y' => 47.3],
            'E' => ['x' => 80.3, 'y' => 85.4],
            '6' => ['x' => 41.3, 'y' => 78.4],
            'F' => ['x' => 15.8, 'y' => 86.6],
            '7' => ['x' => 28.9, 'y' => 40.1],
            'G' => ['x' => 16.8, 'y' => 61.6],
            '8' => ['x' => 13.0, 'y' => 11.4],
            'H' => ['x' => 17.8, 'y' => 48.7],
            '9' => ['x' => 29.7, 'y' => 11.6],
            'I' => ['x' => 65.3, 'y' => 12.1],
            '10' => ['x' => 92.4, 'y' => 7.2],
            'J' => ['x' => 82.1, 'y' => 71.0],
            '11' => ['x' => 87.8, 'y' => 92.8],
            'K' => ['x' => 6.4, 'y' => 91.2],
            '12' => ['x' => 6.0, 'y' => 57.1],
            'L' => ['x' => 12.2, 'y' => 79.7],
            '13' => ['x' => 6.7, 'y' => 4.4],
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
