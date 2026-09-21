<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\User;
use Database\Seeders\PruebasHemaRejillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HemaTest extends TestCase
{
    use RefreshDatabase;

    private const TITULO = 'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)';

    private function sembrar(): array
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $this->seed(PruebasHemaRejillaSeeder::class);

        return [$evaluador, Prueba::where('titulo', self::TITULO)->firstOrFail()];
    }

    public function test_el_seeder_crea_hema_con_8_secciones_y_79_preguntas(): void
    {
        [, $hema] = $this->sembrar();

        $this->assertSame('cuestionario', $hema->tipo);
        $this->assertSame(79, $hema->preguntas()->count());
        $this->assertSame(
            [10, 9, 10, 10, 10, 10, 10, 10],
            $hema->categorias()->withCount('preguntas')->get()->pluck('preguntas_count')->all()
        );
        $this->assertSame(['CONTEO'], $hema->categorias()->pluck('tipo_puntuacion')->unique()->values()->all());
    }

    public function test_cada_pregunta_es_si_no_y_solo_el_si_suma(): void
    {
        [, $hema] = $this->sembrar();

        foreach ($hema->preguntas()->with('opciones')->get() as $pregunta) {
            $this->assertSame('verdadero_falso', $pregunta->tipo);
            $this->assertSame(['Sí' => 1.0, 'No' => 0.0], $pregunta->opciones->pluck('peso', 'texto')->map(fn ($p) => (float) $p)->all());
        }
    }

    public function test_el_seeder_tambien_crea_la_rejilla_y_no_duplica_al_correr_dos_veces(): void
    {
        $this->sembrar();
        $this->seed(PruebasHemaRejillaSeeder::class);

        $this->assertSame(1, Prueba::where('titulo', self::TITULO)->count());
        $this->assertSame(1, Prueba::where('tipo', 'rejilla')->count());
        $this->assertSame(200, Prueba::where('tipo', 'rejilla')->firstOrFail()->rejillaCeldas()->count());
    }

    public function test_el_puntaje_de_cada_seccion_es_la_cantidad_de_si(): void
    {
        [, $hema] = $this->sembrar();
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $hema->id])->assertCreated()->json('id');

        $categorias = $hema->categorias()->orderBy('orden')->get();
        $primera = $categorias->first();
        $siPorCategoria = [];

        foreach ($hema->preguntas()->with('opciones')->get() as $pregunta) {
            // Sección 1: solo las 3 primeras con Sí. Resto de secciones: todo Sí.
            $esPrimera = $pregunta->categoria_evaluacion_id === $primera->id;
            $contadas = $siPorCategoria[$pregunta->categoria_evaluacion_id] ?? 0;
            $respondeSi = ! $esPrimera || $contadas < 3;

            $opcion = $pregunta->opciones->firstWhere('texto', $respondeSi ? 'Sí' : 'No');
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", [
                'pregunta_id' => $pregunta->id,
                'opcion_id' => $opcion->id,
            ])->assertOk();

            if ($respondeSi) {
                $siPorCategoria[$pregunta->categoria_evaluacion_id] = $contadas + 1;
            }
        }

        $response = $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();
        $puntajes = collect($response->json('resultados'))->pluck('puntaje', 'categoria_evaluacion_id')->map(fn ($p) => (float) $p);

        $this->assertSame(3.0, $puntajes[$primera->id]);
        $this->assertSame(9.0, $puntajes[$categorias[1]->id]);
        $this->assertSame(10.0, $puntajes[$categorias[7]->id]);
    }
}
