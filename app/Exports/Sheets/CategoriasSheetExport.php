<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CategoriasSheetExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            ['Manifestaciones Cognitivas', 'PROMEDIO'],
            ['Manifestaciones Fisiológicas', 'PROMEDIO'],
            ['Manifestaciones Motoras', 'PROMEDIO'],
        ];
    }

    public function headings(): array
    {
        return ['nombre', 'tipo_puntuacion'];
    }

    public function title(): string
    {
        return 'Categorias';
    }
}
