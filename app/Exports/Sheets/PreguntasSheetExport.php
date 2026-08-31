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

class PreguntasSheetExport implements FromArray, ShouldAutoSize, WithFreezePane, WithHeadings, WithStyles, WithTabColor, WithTitle
{
    use EstiloEncabezado;

    public function array(): array
    {
        return [
            [1, 'Estoy muy preocupado por los exámenes', 'escala', 'Manifestaciones Cognitivas'],
            [2, 'Tengo palpitaciones, opresión en el pecho, me falta el aire', 'escala', 'Manifestaciones Fisiológicas'],
            [3, 'Me siento entumecido, torpe, rígido, agarrotado', 'escala', 'Manifestaciones Motoras'],
        ];
    }

    public function headings(): array
    {
        return ['numero', 'texto', 'tipo', 'categoria'];
    }

    public function title(): string
    {
        return 'Preguntas';
    }
}
