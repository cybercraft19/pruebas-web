<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OpcionesSheetExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            [1, 'Nunca o casi nunca', 1, 1],
            [1, 'Rara vez', 2, 2],
            [1, 'A veces', 3, 3],
            [1, 'Frecuentemente', 4, 4],
            [1, 'Siempre o casi siempre', 5, 5],
        ];
    }

    public function headings(): array
    {
        return ['pregunta_numero', 'texto', 'peso', 'orden'];
    }

    public function title(): string
    {
        return 'Opciones';
    }
}
