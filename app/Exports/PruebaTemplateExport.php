<?php

namespace App\Exports;

use App\Exports\Sheets\CategoriasSheetExport;
use App\Exports\Sheets\InterpretacionesSheetExport;
use App\Exports\Sheets\OpcionesSheetExport;
use App\Exports\Sheets\PreguntasSheetExport;
use App\Exports\Sheets\PruebaSheetExport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PruebaTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PruebaSheetExport,
            new CategoriasSheetExport,
            new PreguntasSheetExport,
            new OpcionesSheetExport,
            new InterpretacionesSheetExport,
        ];
    }
}
