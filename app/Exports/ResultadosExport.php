<?php

namespace App\Exports;

use App\Models\Prueba;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ResultadosExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly Prueba $prueba) {}

    public function title(): string
    {
        return 'Resultados';
    }

    public function headings(): array
    {
        return $this->prueba->tipo === 'tmt'
            ? ['Estudiante', 'Correo', 'Parte', 'Tiempo (s)', 'Errores', 'Estado', 'Finalizado']
            : ['Estudiante', 'Correo', 'Categoría', 'Puntaje', 'Interpretación', 'Finalizado'];
    }

    public function collection(): Collection
    {
        $esTmt = $this->prueba->tipo === 'tmt';

        $intentos = $this->prueba->intentos()
            ->where('estado', 'finalizado')
            ->with($esTmt ? ['estudiante', 'tmtResultados'] : ['estudiante', 'resultados.categoria'])
            ->get();

        $filas = collect();

        foreach ($intentos as $intento) {
            $finalizado = optional($intento->finalizado_at)->format('d/m/Y H:i');

            if ($esTmt) {
                foreach ($intento->tmtResultados as $resultado) {
                    $filas->push([
                        $intento->estudiante->name,
                        $intento->estudiante->email,
                        $resultado->parte,
                        $resultado->tiempo_segundos,
                        $resultado->errores,
                        $resultado->completado ? 'Completada' : 'No superada',
                        $finalizado,
                    ]);
                }

                continue;
            }

            foreach ($intento->resultados as $resultado) {
                $filas->push([
                    $intento->estudiante->name,
                    $intento->estudiante->email,
                    $resultado->categoria->nombre,
                    $resultado->puntaje,
                    $resultado->etiqueta_interpretacion ?? '—',
                    $finalizado,
                ]);
            }
        }

        return $filas;
    }
}
