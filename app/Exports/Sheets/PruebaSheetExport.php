<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PruebaSheetExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            ['Cuestionario de Ansiedad ante los Exámenes', 'Responde cada afirmación según la frecuencia con la que te ocurre.', 20],
        ];
    }

    public function headings(): array
    {
        return ['titulo', 'instrucciones', 'tiempo_max_minutos'];
    }

    public function title(): string
    {
        return 'Prueba';
    }
}
