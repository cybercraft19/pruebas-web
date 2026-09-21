<?php

namespace Tests\Feature;

use App\Models\InterpretacionCategoria;
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

    public function test_cada_seccion_tiene_tres_rangos_que_cubren_todo_el_puntaje_sin_huecos(): void
    {
        [, $hema] = $this->sembrar();

        foreach ($hema->categorias()->withCount('preguntas')->get() as $categoria) {
            $bandas = $categoria->interpretaciones()->get();

            $this->assertSame(['Por mejorar', 'Aceptable', 'Fortaleza'], $bandas->pluck('etiqueta')->all());
            $this->assertSame(0.0, (float) $bandas[0]->valor_min);
            $this->assertSame((float) $categoria->preguntas_count, (float) $bandas[2]->valor_max);

            // Cada puntaje entero posible cae en exactamente una banda.
            for ($puntaje = 0; $puntaje <= $categoria->preguntas_count; $puntaje++) {
                $coincidencias = $bandas->filter(fn ($b) => $puntaje >= $b->valor_min && $puntaje <= $b->valor_max);
                $this->assertCount(1, $coincidencias, "El puntaje {$puntaje} de {$categoria->nombre} no cae en una única banda.");
            }
        }
    }

    public function test_el_seeder_agrega_las_interpretaciones_a_un_hema_que_ya_existia_sin_ellas(): void
    {
        [, $hema] = $this->sembrar();
        InterpretacionCategoria::whereIn('categoria_evaluacion_id', $hema->categorias()->pluck('id'))->delete();

        $this->seed(PruebasHemaRejillaSeeder::class);

        $this->assertSame(1, Prueba::where('titulo', self::TITULO)->count());
        $this->assertSame(24, InterpretacionCategoria::whereIn('categoria_evaluacion_id', $hema->categorias()->pluck('id'))->count());
    }

    public function test_el_resultado_del_estudiante_trae_la_etiqueta_y_la_recomendacion(): void
    {
        [, $hema] = $this->sembrar();
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $hema->id])->json('id');

        // Todo "No": cada sección queda en 0 = "Por mejorar".
        foreach ($hema->preguntas()->with('opciones')->get() as $pregunta) {
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", [
                'pregunta_id' => $pregunta->id,
                'opcion_id' => $pregunta->opciones->firstWhere('texto', 'No')->id,
            ])->assertOk();
        }

        $resultados = collect($this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->json('resultados'));

        $this->assertCount(8, $resultados);
        $this->assertSame(['Por mejorar'], $resultados->pluck('etiqueta_interpretacion')->unique()->values()->all());
        $this->assertNotEmpty($resultados->first()['recomendacion']);
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
