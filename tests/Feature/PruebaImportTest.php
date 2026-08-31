<?php

namespace Tests\Feature;

use App\Exports\PruebaTemplateExport;
use App\Imports\PruebaTemplateImport;
use App\Models\CategoriaEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PruebaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_can_download_the_template(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);

        $response = $this->actingAs($evaluador)->get('/api/pruebas/plantilla');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_evaluador_can_import_a_valid_template(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $file = $this->buildTemplateUploadFile();

        $response = $this->actingAs($evaluador)->post('/api/pruebas/importar', [
            'archivo' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('titulo', 'Cuestionario de Ansiedad ante los Exámenes');
        $response->assertJsonPath('estado', 'borrador');
        $response->assertJsonCount(3, 'categorias');
        $response->assertJsonCount(3, 'preguntas');

        $this->assertDatabaseHas('pruebas', [
            'titulo' => 'Cuestionario de Ansiedad ante los Exámenes',
            'creado_por' => $evaluador->id,
        ]);
        $this->assertDatabaseHas('categorias_evaluacion', ['nombre' => 'Manifestaciones Cognitivas', 'tipo_puntuacion' => 'PROMEDIO']);
        $this->assertDatabaseHas('preguntas', ['texto' => 'Estoy muy preocupado por los exámenes', 'orden' => 1]);
        $this->assertDatabaseCount('opciones_respuesta', 5);
    }

    public function test_import_creates_interpretaciones_from_template(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $file = $this->buildTemplateUploadFile();

        $response = $this->actingAs($evaluador)->post('/api/pruebas/importar', [
            'archivo' => $file,
        ]);

        $response->assertCreated();

        $categoria = CategoriaEvaluacion::where('nombre', 'Manifestaciones Cognitivas')->firstOrFail();

        $this->assertDatabaseHas('interpretaciones_categoria', [
            'categoria_evaluacion_id' => $categoria->id,
            'etiqueta' => 'Bajo',
            'recomendacion' => 'Tu nivel está dentro de un rango saludable.',
        ]);
        $this->assertDatabaseHas('interpretaciones_categoria', [
            'categoria_evaluacion_id' => $categoria->id,
            'etiqueta' => 'Alto',
        ]);
    }

    public function test_import_fails_with_unknown_category_reference(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $file = $this->buildTemplateUploadFile(function (array &$sheets) {
            $sheets[2][0]['categoria'] = 'Categoria Inexistente';
        });

        $response = $this->actingAs($evaluador)->post('/api/pruebas/importar', [
            'archivo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('plantilla');

        $this->assertDatabaseCount('pruebas', 0);
    }

    public function test_estudiante_cannot_import_pruebas(): void
    {
        Storage::fake('local');

        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $file = $this->buildTemplateUploadFile();

        $response = $this->actingAs($estudiante)->post('/api/pruebas/importar', [
            'archivo' => $file,
        ]);

        $response->assertForbidden();
    }

    /**
     * Builds a real .xlsx file (from our own template export) to use as the
     * uploaded file in tests, optionally mutating the sheet data first.
     */
    private function buildTemplateUploadFile(?callable $mutate = null): UploadedFile
    {
        $sheets = Excel::toArray(new PruebaTemplateImport, (function () {
            Excel::store(new PruebaTemplateExport, 'base-template.xlsx', 'local');

            return Storage::disk('local')->path('base-template.xlsx');
        })());

        if ($mutate) {
            $mutate($sheets);
        }

        $export = new class($sheets) implements Export, WithMultipleSheets
        {
            public function __construct(private array $sheets) {}

            public function sheets(): array
            {
                $titles = ['Prueba', 'Categorias', 'Preguntas', 'Opciones', 'Interpretaciones'];

                return array_map(
                    fn ($rows, $i) => new class($rows, $titles[$i]) implements FromArray, WithHeadings, WithTitle
                    {
                        public function __construct(private array $rows, private string $sheetTitle) {}

                        public function array(): array
                        {
                            return array_map('array_values', $this->rows);
                        }

                        public function headings(): array
                        {
                            return array_keys($this->rows[0] ?? []);
                        }

                        public function title(): string
                        {
                            return $this->sheetTitle;
                        }
                    },
                    $this->sheets,
                    array_keys($this->sheets),
                );
            }
        };

        Excel::store($export, 'mutated-template.xlsx', 'local');
        $path = Storage::disk('local')->path('mutated-template.xlsx');

        return new UploadedFile(
            $path,
            'plantilla.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
