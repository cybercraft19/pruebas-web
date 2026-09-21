<?php

namespace Tests\Feature;

use App\Models\Prueba;
use App\Models\RejillaResultado;
use App\Models\User;
use App\Services\RejillaLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejillaTest extends TestCase
{
    use RefreshDatabase;

    private function pruebaRejilla(User $evaluador, string $estado = 'publicada'): Prueba
    {
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'rejilla', 'titulo' => 'Rejilla', 'estado' => $estado]);
        app(RejillaLayoutService::class)->generar($prueba);

        return $prueba;
    }

    public function test_cada_grilla_tiene_los_numeros_del_00_al_99_sin_repetir(): void
    {
        $servicio = app(RejillaLayoutService::class);

        foreach ([RejillaLayoutService::VARIANTE_ESTANDAR, RejillaLayoutService::VARIANTE_CABALLO] as $variante) {
            $numeros = $servicio->grilla($variante);
            sort($numeros);

            $this->assertSame(range(0, 99), $numeros, "La grilla {$variante} no es una permutación de 00-99.");
        }
    }

    public function test_la_grilla_del_caballo_avanza_con_saltos_en_l_del_00_al_50(): void
    {
        $grilla = app(RejillaLayoutService::class)->grilla(RejillaLayoutService::VARIANTE_CABALLO);
        $posicion = array_flip($grilla);
        $columnas = RejillaLayoutService::COLUMNAS;

        for ($numero = 0; $numero < 50; $numero++) {
            $filaA = intdiv($posicion[$numero], $columnas);
            $colA = $posicion[$numero] % $columnas;
            $filaB = intdiv($posicion[$numero + 1], $columnas);
            $colB = $posicion[$numero + 1] % $columnas;

            $saltos = [abs($filaA - $filaB), abs($colA - $colB)];
            sort($saltos);

            $this->assertSame([1, 2], $saltos, "Del {$numero} al ".($numero + 1).' no hay un salto de caballo.');
        }
    }

    public function test_las_grillas_coinciden_con_celdas_conocidas_del_documento(): void
    {
        $servicio = app(RejillaLayoutService::class);
        $estandar = $servicio->grilla(RejillaLayoutService::VARIANTE_ESTANDAR);
        $caballo = $servicio->grilla(RejillaLayoutService::VARIANTE_CABALLO);

        $this->assertSame(84, $estandar[0]);
        $this->assertSame(0, $estandar[39]);
        $this->assertSame(63, $estandar[99]);
        $this->assertSame(10, $caballo[0]);
        $this->assertSame(0, $caballo[82]);
        $this->assertSame(24, $caballo[99]);
    }

    public function test_generar_guarda_100_celdas_por_variante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $prueba = $this->pruebaRejilla($evaluador);

        $this->assertSame(100, $prueba->rejillaCeldas()->where('variante', 'estandar')->count());
        $this->assertSame(100, $prueba->rejillaCeldas()->where('variante', 'caballo')->count());
    }

    public function test_el_nivel_depende_de_los_aciertos(): void
    {
        $this->assertSame('Necesita entrenar la atención', (new RejillaResultado(['aciertos' => 19]))->nivel);
        $this->assertSame('Buen nivel de concentración', (new RejillaResultado(['aciertos' => 20]))->nivel);
        $this->assertSame('Buen nivel de concentración', (new RejillaResultado(['aciertos' => 35]))->nivel);
    }

    public function test_estudiante_completa_el_flujo_y_el_evaluador_lo_firma(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = $this->pruebaRejilla($evaluador);

        $intento = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id]);
        $intento->assertCreated();
        $this->assertCount(200, $intento->json('prueba.rejilla_celdas'));
        $intentoId = $intento->json('id');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'estandar', 'aciertos' => 24, 'errores' => 2])
            ->assertOk()
            ->assertJsonPath('nivel', 'Buen nivel de concentración');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'caballo', 'aciertos' => 12, 'errores' => 5])
            ->assertOk()
            ->assertJsonPath('nivel', 'Necesita entrenar la atención');

        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")
            ->assertOk()
            ->assertJsonCount(2, 'rejilla_resultados');

        $resultados = $this->actingAs($evaluador)->getJson("/api/pruebas/{$prueba->id}/resultados");
        $resultados->assertOk();
        $this->assertFalse($resultados->json('0.firmado'));
        $this->assertCount(2, $resultados->json('0.rejilla_resultados'));

        $this->actingAs($evaluador)->postJson("/api/intentos/{$intentoId}/firmar")->assertOk();

        $this->assertTrue($this->actingAs($evaluador)->getJson("/api/pruebas/{$prueba->id}/resultados")->json('0.firmado'));
    }

    public function test_no_se_puede_finalizar_sin_completar_las_dos_variantes(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = $this->pruebaRejilla($evaluador);

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'estandar', 'aciertos' => 10, 'errores' => 0])
            ->assertOk();

        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertUnprocessable();
    }

    public function test_rechaza_datos_invalidos_al_registrar_una_variante(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = $this->pruebaRejilla($evaluador);
        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'otra', 'aciertos' => 10, 'errores' => 0])
            ->assertUnprocessable();

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'estandar', 'aciertos' => 101, 'errores' => 0])
            ->assertUnprocessable();
    }

    public function test_no_se_puede_registrar_una_rejilla_en_una_prueba_de_otro_tipo(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = Prueba::create(['creado_por' => $evaluador->id, 'tipo' => 'cuestionario', 'titulo' => 'Q', 'estado' => 'publicada']);
        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $this->actingAs($estudiante)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'estandar', 'aciertos' => 10, 'errores' => 0])
            ->assertUnprocessable();
    }

    public function test_otro_estudiante_no_puede_registrar_en_un_intento_ajeno(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $dueno = User::factory()->create(['role' => 'estudiante']);
        $otro = User::factory()->create(['role' => 'estudiante']);
        $prueba = $this->pruebaRejilla($evaluador);
        $intentoId = $this->actingAs($dueno)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');

        $this->actingAs($otro)
            ->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => 'estandar', 'aciertos' => 10, 'errores' => 0])
            ->assertForbidden();
    }

    public function test_el_evaluador_puede_ver_el_detalle_y_exportar_una_prueba_de_rejilla(): void
    {
        $evaluador = User::factory()->create(['role' => 'evaluador']);
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        $prueba = $this->pruebaRejilla($evaluador);

        $this->actingAs($evaluador)->getJson("/api/pruebas/{$prueba->id}")
            ->assertOk()
            ->assertJsonCount(200, 'rejilla_celdas');

        $intentoId = $this->actingAs($estudiante)->postJson('/api/intentos', ['prueba_id' => $prueba->id])->json('id');
        foreach (['estandar', 'caballo'] as $variante) {
            $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/rejilla", ['variante' => $variante, 'aciertos' => 22, 'errores' => 1])->assertOk();
        }
        $this->actingAs($estudiante)->postJson("/api/intentos/{$intentoId}/finalizar")->assertOk();

        $this->actingAs($evaluador)->get("/api/pruebas/{$prueba->id}/resultados/exportar")->assertOk();
    }
}
