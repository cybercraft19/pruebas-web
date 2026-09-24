<?php

namespace Tests\Feature;

use App\Models\Intento;
use App\Models\Prueba;
use App\Models\User;
use App\Services\InformeNarrativoService;
use Database\Seeders\PruebasReferenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstiloAprendizajeTest extends TestCase
{
    use RefreshDatabase;

    private const TITULO = 'Test de Estilo de Aprendizaje (Modelo PNL)';

    public function test_el_seeder_crea_40_preguntas_con_3_opciones_cada_una(): void
    {
        User::factory()->create(['role' => 'evaluador']);
        $this->seed(PruebasReferenciaSeeder::class);

        $prueba = Prueba::where('titulo', self::TITULO)->firstOrFail();

        $this->assertSame(40, $prueba->preguntas()->count());
        foreach ($prueba->preguntas as $pregunta) {
            $this->assertSame(3, $pregunta->opciones()->count());
        }
        $this->assertSame(['Visual', 'Auditivo', 'Cinestésico'], $prueba->categorias()->orderBy('orden')->pluck('nombre')->all());
    }

    public function test_responder_todo_visual_da_como_ganador_el_canal_visual_y_el_informe_lo_refleja(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $this->seed(PruebasReferenciaSeeder::class);
        $prueba = Prueba::where('titulo', self::TITULO)->firstOrFail();
        $estudiante = User::factory()->create(['role' => 'estudiante', 'creado_por' => $evaluador->id]);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        foreach ($prueba->preguntas()->with('opciones.categoria')->get() as $pregunta) {
            $opcionVisual = $pregunta->opciones->first(fn ($o) => $o->categoria->nombre === 'Visual');
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/respuestas", [
                'pregunta_id' => $pregunta->id,
                'opcion_id' => $opcionVisual->id,
            ])->assertOk();
        }

        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();
        $this->actingAs($evaluador)->postJson("/api/intentos/{$intentoId}/firmar")->assertOk();

        $intento = Intento::with(['resultados.categoria', 'estudiante'])->findOrFail($intentoId);
        $puntajes = $intento->resultados->pluck('puntaje', 'categoria_evaluacion_id');
        $visual = $intento->resultados->first(fn ($r) => $r->categoria->nombre === 'Visual');

        $this->assertSame(40.0, (float) $visual->puntaje);
        $this->assertSame('Predominante', $visual->etiqueta_interpretacion);

        $narrativa = app(InformeNarrativoService::class)->narrativaDe($intento);
        $this->assertSame('Resultado — Estilo Visual', $narrativa['parrafos'][0]);
        $this->assertStringContainsString('predominantemente visual', $narrativa['parrafos'][1]);
    }
}
