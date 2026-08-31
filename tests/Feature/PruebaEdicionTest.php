<?php

namespace Tests\Feature;

use App\Exports\PruebaTemplateExport;
use App\Imports\PruebaTemplateImport;
use App\Models\CategoriaEvaluacion;
use App\Models\Pregunta;
use App\Models\Prueba;
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

class PruebaEdicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluador_puede_editar_titulo_e_instrucciones(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Original', 'estado' => 'borrador']);

        $response = $this->actingAs($evaluador)->putJson("/api/pruebas/{$prueba->id}", [
            'titulo' => 'Título corregido',
            'instrucciones' => 'Nuevas instrucciones',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pruebas', [
            'id' => $prueba->id,
            'titulo' => 'Título corregido',
            'instrucciones' => 'Nuevas instrucciones',
        ]);
    }

    public function test_evaluador_no_puede_editar_prueba_ajena(): void
    {
        $dueno = User::factory()->create(['role' => 'evaluador']);
        $otro = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $dueno->id, 'titulo' => 'P', 'estado' => 'borrador']);

        $response = $this->actingAs($otro)->putJson("/api/pruebas/{$prueba->id}", ['titulo' => 'Hackeada']);

        $response->assertForbidden();
    }

    public function test_evaluador_puede_reemplazar_contenido_de_prueba_en_borrador_sin_intentos(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'Vieja', 'estado' => 'borrador']);
        CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'Vieja categoria', 'tipo_puntuacion' => 'PROMEDIO', 'orden' => 1]);

        $file = $this->buildTemplateUploadFile(function (array &$sheets) {
            $sheets[1][0]['titulo'] = 'Prueba Reemplazada';
        });

        $response = $this->actingAs($evaluador)->post("/api/pruebas/{$prueba->id}/reimportar", [
            'archivo' => $file,
        ]);

        $response->assertOk();
        $prueba->refresh();
        $this->assertSame('Prueba Reemplazada', $prueba->titulo);
        $this->assertDatabaseMissing('categorias_evaluacion', ['prueba_id' => $prueba->id, 'nombre' => 'Vieja categoria']);
        $this->assertDatabaseHas('categorias_evaluacion', ['prueba_id' => $prueba->id, 'nombre' => 'Manifestaciones Cognitivas']);
    }

    public function test_no_se_puede_reemplazar_contenido_si_ya_hay_intentos(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);
        $categoria = CategoriaEvaluacion::create(['prueba_id' => $prueba->id, 'nombre' => 'C', 'tipo_puntuacion' => 'PROMEDIO', 'orden' => 1]);
        Pregunta::create(['prueba_id' => $prueba->id, 'categoria_evaluacion_id' => $categoria->id, 'texto' => 'P1', 'tipo' => 'escala', 'orden' => 1]);

        $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->assertCreated();
        $prueba->update(['estado' => 'borrador']);

        $file = $this->buildTemplateUploadFile();
        $response = $this->actingAs($evaluador)->post("/api/pruebas/{$prueba->id}/reimportar", ['archivo' => $file]);

        $response->assertUnprocessable();
    }

    public function test_no_se_puede_reemplazar_contenido_de_prueba_publicada(): void
    {
        Storage::fake('local');

        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'titulo' => 'P', 'estado' => 'publicada']);

        $file = $this->buildTemplateUploadFile();
        $response = $this->actingAs($evaluador)->post("/api/pruebas/{$prueba->id}/reimportar", ['archivo' => $file]);

        $response->assertUnprocessable();
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
                $titles = ['Instrucciones', 'Prueba', 'Categorias', 'Preguntas', 'Opciones', 'Interpretaciones'];

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
