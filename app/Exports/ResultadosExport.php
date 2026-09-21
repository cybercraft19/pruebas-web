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
        return match ($this->prueba->tipo) {
            'tmt' => ['Estudiante', 'Correo', 'Parte', 'Tiempo (s)', 'Errores', 'Estado', 'Finalizado', 'Firmado'],
            'rejilla' => ['Estudiante', 'Correo', 'Variante', 'Aciertos', 'Errores', 'Nivel', 'Finalizado', 'Firmado'],
            default => ['Estudiante', 'Correo', 'Categoría', 'Puntaje', 'Interpretación', 'Finalizado', 'Firmado'],
        };
    }

    public function collection(): Collection
    {
        $esTmt = $this->prueba->tipo === 'tmt';
        $esRejilla = $this->prueba->tipo === 'rejilla';

        $intentos = $this->prueba->intentos()
            ->where('estado', 'finalizado')
            ->with(match (true) {
                $esTmt => ['estudiante', 'tmtResultados'],
                $esRejilla => ['estudiante', 'rejillaResultados'],
                default => ['estudiante', 'resultados.categoria'],
            })
            ->get();

        $filas = collect();

        foreach ($intentos as $intento) {
            $finalizado = optional($intento->finalizado_at)->format('d/m/Y H:i');
            $firmado = $intento->firmado_at ? 'Sí' : 'No';

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
                        $firmado,
                    ]);
                }

                continue;
            }

            if ($esRejilla) {
                foreach ($intento->rejillaResultados as $resultado) {
                    $filas->push([
                        $intento->estudiante->name,
                        $intento->estudiante->email,
                        $resultado->variante === 'caballo' ? 'Caballo (Núñez Nieto)' : 'Estándar (Harris y Harris)',
                        $resultado->aciertos,
                        $resultado->errores,
                        $resultado->nivel,
                        $finalizado,
                        $firmado,
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
                    $firmado,
                ]);
            }
        }

        return $filas;
    }
}
