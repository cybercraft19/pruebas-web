<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTabColor;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InstruccionesSheetExport implements FromArray, WithStyles, WithTabColor, WithTitle
{
    /** Filas de encabezado de sección, para ponerlas en negrita. */
    private const FILAS_SECCION = [5, 8, 14, 17, 20];

    /** Filas con párrafos largos, para darles más alto y que no se corten. */
    private const FILAS_PARRAFO_LARGO = [3, 6, 9, 15, 18, 21];

    public function array(): array
    {
        return [
            ['Cómo completar esta plantilla'],
            [''],
            ['Completá las hojas en el orden de las solapas de abajo. Cuando termines, guardá el archivo y subilo en "Subir cuestionario".'],
            [''],
            ['1) Prueba'],
            ['Una sola fila con el título, las instrucciones para el estudiante, y el tiempo máximo en minutos (dejalo vacío si no querés límite de tiempo).'],
            [''],
            ['2) Categorias'],
            ['Cada fila es un grupo de preguntas que se puntúa junto (ej. "Ansiedad Cognitiva"). La columna "tipo_puntuacion" define cómo se calcula el puntaje de esa categoría:'],
            ['   • SUMA_PONDERADA: suma los pesos de las respuestas elegidas.'],
            ['   • PROMEDIO: suma los pesos y divide por la cantidad de preguntas de la categoría.'],
            ['   • CONTEO: cuenta cuántas respuestas de peso 1 se eligieron (útil para preguntas Sí/No o Verdadero/Falso).'],
            [''],
            ['3) Preguntas'],
            ['Cada fila es una pregunta. La columna "categoria" tiene que escribirse EXACTAMENTE igual que en la hoja Categorias. La columna "tipo" puede ser: escala, opcion_multiple o verdadero_falso.'],
            [''],
            ['4) Opciones'],
            ['Cada fila es una opción de respuesta de una pregunta. "pregunta_numero" tiene que coincidir con el "numero" de la hoja Preguntas. "peso" es el valor numérico que suma esa opción al puntaje de su categoría.'],
            [''],
            ['5) Interpretaciones (opcional)'],
            ['Si la completás, cada categoría muestra una recomendación según el puntaje que saque el estudiante. "categoria" debe coincidir con el nombre de la hoja Categorias, y "valor_min"/"valor_max" definen el rango de puntaje de esa interpretación. Si dejás esta hoja vacía, la prueba funciona igual, solo que no muestra recomendación al finalizar.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(130);
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension(1)->setRowHeight(28);

        foreach (self::FILAS_PARRAFO_LARGO as $fila) {
            $sheet->getRowDimension($fila)->setRowHeight(30);
        }

        $estilos = [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '052A86']],
                'alignment' => ['vertical' => 'center'],
            ],
            "A1:A{$sheet->getHighestRow()}" => [
                'alignment' => ['wrapText' => true, 'vertical' => 'center'],
            ],
        ];

        foreach (self::FILAS_SECCION as $fila) {
            $estilos[$fila] = [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '052A86']],
            ];
        }

        return $estilos;
    }

    public function tabColor(): string
    {
        return 'E0B51A';
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}
