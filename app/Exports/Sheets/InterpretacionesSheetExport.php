<?php

namespace App\Exports\Sheets;

use App\Exports\Sheets\Concerns\EstiloEncabezado;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithFreezePane;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTabColor;
use Maatwebsite\Excel\Concerns\WithTitle;

class InterpretacionesSheetExport implements FromArray, ShouldAutoSize, WithFreezePane, WithHeadings, WithStyles, WithTabColor, WithTitle
{
    use EstiloEncabezado;

    public function array(): array
    {
        return [
            ['Manifestaciones Cognitivas', 1, 3, 'Bajo', 'Tu nivel está dentro de un rango saludable.'],
            ['Manifestaciones Cognitivas', 3.01, 5, 'Alto', 'Te recomendamos hablar con un orientador o psicólogo escolar.'],
        ];
    }

    public function headings(): array
    {
        return ['categoria', 'valor_min', 'valor_max', 'etiqueta', 'recomendacion'];
    }

    public function title(): string
    {
        return 'Interpretaciones';
    }
}
