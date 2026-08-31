<?php

namespace App\Exports\Sheets\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Encabezado en negrita sobre fondo azul de marca, columnas ajustadas al
 * contenido, primera fila fija al hacer scroll. Estilo compartido por todas
 * las hojas de datos de la plantilla de importación.
 */
trait EstiloEncabezado
{
    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '052A86']],
                'alignment' => ['vertical' => 'center'],
            ],
        ];
    }

    public function freezePane(): string
    {
        return 'A2';
    }

    public function tabColor(): string
    {
        return '052A86';
    }
}
