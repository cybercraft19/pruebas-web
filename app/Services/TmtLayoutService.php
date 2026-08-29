<?php

namespace App\Services;

use App\Models\Prueba;
use App\Models\TmtNodo;

class TmtLayoutService
{
    /**
     * Genera y persiste los nodos de práctica y reales de las partes A y B para una prueba TMT.
     */
    public function generar(Prueba $prueba): void
    {
        $this->crearGrupo($prueba, 'A', true, ['1', '2', '3', '4', '5', '6', '7', '8']);
        $this->crearGrupo($prueba, 'A', false, array_map('strval', range(1, 25)));
        $this->crearGrupo($prueba, 'B', true, ['1', 'A', '2', 'B', '3', 'C', '4', 'D']);
        $this->crearGrupo($prueba, 'B', false, $this->secuenciaParteB());
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
     */
    private function crearGrupo(Prueba $prueba, string $parte, bool $practica, array $etiquetas): void
    {
        $semilla = crc32($prueba->id.'-'.$parte.'-'.($practica ? 'practica' : 'real'));
        $posiciones = $this->posiciones(count($etiquetas), $semilla);

        foreach ($etiquetas as $indice => $etiqueta) {
            TmtNodo::create([
                'prueba_id' => $prueba->id,
                'parte' => $parte,
                'practica' => $practica,
                'orden' => $indice + 1,
                'etiqueta' => $etiqueta,
                'pos_x' => $posiciones[$indice]['x'],
                'pos_y' => $posiciones[$indice]['y'],
            ]);
        }
    }

    /**
     * Distribuye `$cantidad` puntos en una cuadrícula con jitter, evitando solapamiento,
     * usando una semilla fija para que el layout sea reproducible por prueba.
     *
     * @return array<int, array{x: float, y: float}>
     */
    private function posiciones(int $cantidad, int $semilla): array
    {
        $columnas = (int) ceil(sqrt($cantidad * 1.4));
        $filas = (int) ceil($cantidad / $columnas);

        $celdas = [];
        for ($fila = 0; $fila < $filas; $fila++) {
            for ($columna = 0; $columna < $columnas; $columna++) {
                $celdas[] = [$fila, $columna];
            }
        }

        mt_srand($semilla);
        shuffle($celdas);
        $celdas = array_slice($celdas, 0, $cantidad);

        $anchoCelda = 100 / $columnas;
        $altoCelda = 100 / $filas;

        return array_map(function (array $celda) use ($anchoCelda, $altoCelda) {
            [$fila, $columna] = $celda;

            return [
                'x' => round($columna * $anchoCelda + mt_rand(15, 85) / 100 * $anchoCelda, 2),
                'y' => round($fila * $altoCelda + mt_rand(15, 85) / 100 * $altoCelda, 2),
            ];
        }, $celdas);
    }
}
