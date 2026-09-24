<?php

namespace App\Services;

use App\Models\Intento;
use App\Models\User;

/**
 * Arma los datos de un intento finalizado, listos para que cualquier plantilla de informe
 * (todavía sin definir) los consuma directo, sin tener que volver a consultar la base ni
 * reimplementar el cálculo por tipo de prueba (cuestionario, TMT, rejilla).
 */
class InformeService
{
    /**
     * Párrafos fijos de introducción institucional, transcritos tal cual del ejemplo de
     * informe oficial (docs/Ejemplo informe con todas las pruebas de 6 a 11.docx). Van
     * siempre iguales, sin importar el estudiante ni los resultados.
     *
     * @var array<int, string>
     */
    public const INTRODUCCION = [
        'En el marco del proceso de caracterización y fortalecimiento de los procesos de aprendizaje, se llevó a cabo la aplicación de una batería de instrumentos de carácter investigativo, pedagógico y orientativo a los estudiantes con el propósito de obtener información general sobre diferentes aspectos relacionados con su desempeño educativo, procesos cognitivos, hábitos y estrategias de estudio, motivación académica, intereses y preferencias vocacionales.',
        'Es fundamental precisar que los instrumentos aplicados no constituyen una evaluación clínica, diagnóstica ni psicopatológica, por lo que los resultados obtenidos no permiten establecer diagnósticos psicológicos, psiquiátricos, neuropsicológicos ni determinar la presencia de trastornos del aprendizaje, trastornos emocionales u otras condiciones clínicas. La información obtenida debe comprenderse exclusivamente como un insumo de carácter educativo e investigativo, orientado a identificar tendencias, fortalezas y oportunidades de mejora que puedan contribuir al diseño de estrategias pedagógicas y de acompañamiento institucional.',
        'La batería contempló, de acuerdo con el ciclo escolar y las características de los estudiantes, diferentes áreas relacionadas con atención y concentración, velocidad de procesamiento, flexibilidad cognitiva, hábitos de estudio, motivación para el aprendizaje, estilos y preferencias de aprendizaje, intereses y aptitudes vocacionales, ansiedad ante las evaluaciones y procrastinación académica, entre otras dimensiones relevantes para el contexto educativo.',
        'Los resultados individuales tienen un carácter orientativo y confidencial y deben ser interpretados considerando la edad, grado escolar, contexto educativo, condiciones particulares de aplicación y demás factores que pueden influir en el desempeño de un estudiante. Por esta razón, un resultado obtenido en un instrumento específico no debe interpretarse de manera aislada ni utilizarse para etiquetar, clasificar o tomar decisiones que afecten al estudiante.',
        'En el ámbito institucional, el análisis de los resultados se realizará principalmente de manera global y agregada, con el propósito de reconocer tendencias generales de la población evaluada. Esta información permitirá identificar áreas que requieren fortalecimiento y orientar la construcción de estrategias pedagógicas, programas de acompañamiento y acciones de promoción del aprendizaje, procurando que las intervenciones respondan a las necesidades observadas en los diferentes niveles escolares.',
        'A continuación, se describen de manera general los instrumentos utilizados en el presente estudio y el propósito que cumplió cada uno dentro del proceso de caracterización educativa. La selección y aplicación de los instrumentos se realizó de acuerdo con el nivel escolar y las características de los estudiantes participantes.',
    ];

    /**
     * Listado fijo de los 8 instrumentos, con su área explorada y objetivo dentro del
     * estudio. Se muestran solo los que el estudiante efectivamente presentó.
     *
     * @var array<string, array{area: string, objetivo: string}>
     */
    public const INSTRUMENTOS = [
        'Trail Making Test (TMT)' => [
            'area' => 'atención visual, velocidad de procesamiento, seguimiento secuencial y flexibilidad cognitiva.',
            'objetivo' => 'aportar información sobre el desempeño de los estudiantes en tareas que requieren mantener la atención, seguir una secuencia, realizar asociaciones visuales y alternar entre diferentes criterios de respuesta. Esta información permite reconocer tendencias que pueden ser consideradas al momento de diseñar estrategias pedagógicas relacionadas con la organización, seguimiento de instrucciones y ejecución de actividades académicas.',
        ],
        'Test de Orientación Vocacional (CHASIDE)' => [
            'area' => 'intereses y preferencias relacionadas con diferentes áreas ocupacionales y académicas.',
            'objetivo' => 'proporcionar información orientativa que permita a los estudiantes reconocer áreas de interés y preferencias relacionadas con posibles campos de formación académica y ocupacional. Los resultados pueden utilizarse como insumo para procesos de orientación vocacional y construcción del proyecto de vida.',
        ],
        'Test de Estilo de Aprendizaje (Modelo PNL)' => [
            'area' => 'preferencias declaradas frente a determinadas formas de recibir, procesar y abordar información.',
            'objetivo' => 'identificar tendencias en las preferencias de los estudiantes frente a diferentes formas de aproximarse a las actividades de aprendizaje, con el propósito de generar información que pueda ser considerada en el diseño de actividades pedagógicas variadas.',
        ],
        'Test de las Inteligencias Múltiples (H. Gardner)' => [
            'area' => 'fortalezas y preferencias asociadas con diferentes dominios de desempeño, tales como lingüístico, lógico-matemático, espacial, musical, corporal-cinestésico, interpersonal e intrapersonal.',
            'objetivo' => 'reconocer tendencias y áreas en las que los estudiantes manifiestan mayores preferencias o fortalezas percibidas, con el propósito de aportar elementos para promover experiencias educativas diversas y favorecer el desarrollo integral.',
        ],
        'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)' => [
            'area' => 'hábitos y estrategias de estudio, organización académica, manejo del tiempo y aspectos relacionados con la motivación hacia el aprendizaje.',
            'objetivo' => 'identificar fortalezas y oportunidades de mejora relacionadas con las prácticas de estudio de los estudiantes, con el fin de orientar acciones educativas dirigidas al fortalecimiento de la planificación, organización, autorregulación y establecimiento de estrategias académicas.',
        ],
        'Test de la Rejilla (Harris y Harris)' => [
            'area' => 'atención visual, búsqueda visual, concentración, velocidad y precisión en la ejecución de una tarea.',
            'objetivo' => 'obtener información sobre el desempeño de los estudiantes en una actividad estructurada de búsqueda y localización de estímulos dentro de una matriz, considerando variables como cantidad de elementos procesados, precisión y errores durante el tiempo establecido.',
        ],
        'Cuestionario de Autoevaluación de la Ansiedad ante los Exámenes' => [
            'area' => 'percepción de respuestas cognitivas, emocionales, fisiológicas y conductuales asociadas con las situaciones de evaluación académica.',
            'objetivo' => 'conocer la percepción que tienen los estudiantes sobre sus experiencias frente a los exámenes y reconocer tendencias relacionadas con preocupación, tensión, pensamientos asociados a la evaluación y otras respuestas que podrían interferir con su experiencia académica. La información puede contribuir al diseño de estrategias educativas de manejo de situaciones de evaluación, preparación académica y afrontamiento del estrés, sin constituir un diagnóstico de un trastorno de ansiedad.',
        ],
        'Escala de Procrastinación Académica' => [
            'area' => 'tendencia a postergar actividades académicas, organización del tiempo y autorregulación frente a las responsabilidades escolares.',
            'objetivo' => 'identificar tendencias relacionadas con la postergación de tareas y dificultades percibidas en la organización y gestión del tiempo académico, con el propósito de orientar estrategias de planificación, establecimiento de metas, organización de actividades y fortalecimiento de hábitos de estudio.',
        ],
    ];

    public const FIRMA = [
        'nombre' => 'Maira Yohana Carmona Valencia',
        'cargo' => 'Psicóloga',
        'titulo' => 'Magíster en neuropsicología y educación',
        'tarjeta_profesional' => 'T.P 116533',
    ];

    public function __construct(private readonly InformeNarrativoService $narrativoService) {}

    /**
     * Informe completo de un estudiante: intro fija + un bloque por cada prueba que ya
     * finalizó Y fue firmada (las que solo finalizó pero no se firmaron todavía no se
     * incluyen, igual que el resto del sitio no libera resultados sin firma).
     *
     * @return array<string, mixed>
     */
    public function completoDe(User $estudiante): array
    {
        $intentos = $estudiante->intentos()
            ->where('estado', 'finalizado')
            ->whereNotNull('firmado_at')
            ->with(['prueba', 'estudiante', 'resultados.categoria', 'tmtResultados', 'rejillaResultados'])
            ->get()
            ->sortBy(fn ($i) => array_search($i->prueba->titulo, array_keys(self::INSTRUMENTOS), true) ?: 99);

        return [
            'estudiante' => [
                'nombre' => $estudiante->name,
                'edad' => $estudiante->fecha_nacimiento?->age,
                'grado' => $estudiante->grado,
                'genero' => $estudiante->genero,
                'fecha_nacimiento' => $estudiante->fecha_nacimiento,
            ],
            'introduccion' => self::INTRODUCCION,
            'instrumentos' => collect(self::INSTRUMENTOS)
                ->only($intentos->pluck('prueba.titulo')->all())
                ->map(fn ($info, $titulo) => ['titulo' => $titulo, ...$info])
                ->values()->all(),
            'resultados' => $intentos->map(fn ($intento) => [
                'prueba' => $intento->prueba->only(['id', 'titulo', 'tipo']),
                'grafica' => $this->graficaDe($intento),
                'narrativa' => $this->narrativoService->narrativaDe($intento),
                'firmado_at' => $intento->firmado_at,
            ])->values()->all(),
            'firma' => self::FIRMA,
        ];
    }

    /**
     * Payload completo para el informe: identifica al estudiante y a la prueba, y trae los
     * resultados en dos formas — "resumen" (filas simples, ya usadas en la vista del
     * evaluador) y "grafica" (con las bandas de interpretación completas, para dibujar dónde
     * cayó el puntaje dentro de la escala).
     *
     * @return array<string, mixed>
     */
    public function generar(Intento $intento): array
    {
        $intento->loadMissing([
            'estudiante', 'prueba.categorias.interpretaciones', 'resultados.categoria',
            'tmtResultados', 'rejillaResultados', 'firmante',
        ]);

        return [
            'estudiante' => $intento->estudiante->only([
                'id', 'name', 'email', 'cedula', 'fecha_nacimiento', 'acudiente_nombre', 'acudiente_telefono',
            ]),
            'prueba' => $intento->prueba->only(['id', 'titulo', 'tipo', 'instrucciones']),
            'finalizado_at' => $intento->finalizado_at,
            'firmado_at' => $intento->firmado_at,
            'firmado_por' => $intento->firmante?->only(['id', 'name']),
            'resumen' => $this->resumenDe($intento),
            'grafica' => $this->graficaDe($intento),
        ];
    }

    /**
     * Resultado de un intento como filas homogéneas (concepto, valor, interpretación,
     * recomendación), sin importar el tipo de prueba. Usado también en la vista del
     * evaluador (GET /api/estudiantes/{id}), que no necesita las bandas para graficar.
     *
     * @return array<int, array{concepto: string, valor: string, interpretacion: ?string, recomendacion: ?string}>
     */
    public function resumenDe(Intento $intento): array
    {
        return match ($intento->prueba->tipo) {
            'tmt' => $intento->tmtResultados->map(fn ($resultado) => [
                'concepto' => "Parte {$resultado->parte}",
                'valor' => "{$resultado->tiempo_segundos} s · {$resultado->errores} errores",
                'interpretacion' => $resultado->completado ? 'Completada' : 'No superada',
                'recomendacion' => null,
            ])->values()->all(),
            'rejilla' => $intento->rejillaResultados->map(fn ($resultado) => [
                'concepto' => $resultado->variante === 'caballo' ? 'Rejilla del caballo (Núñez Nieto)' : 'Rejilla estándar (Harris y Harris)',
                'valor' => "{$resultado->aciertos} números · {$resultado->errores} errores",
                'interpretacion' => $resultado->nivel,
                'recomendacion' => null,
            ])->values()->all(),
            default => $intento->resultados->map(fn ($resultado) => [
                'concepto' => $resultado->categoria->nombre,
                'valor' => (string) round((float) $resultado->puntaje, 2),
                'interpretacion' => $resultado->etiqueta_interpretacion,
                'recomendacion' => $resultado->recomendacion,
            ])->values()->all(),
        };
    }

    /**
     * Datos listos para graficar. En cuestionarios: el puntaje de cada categoría más todas
     * sus bandas (etiqueta + rango), para dibujar una barra/gauge que ubique el puntaje
     * dentro de la escala completa. En TMT/rejilla el resultado ya es pasa/no-pasa por
     * tiempo o umbral, así que se entrega el límite oficial en vez de bandas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function graficaDe(Intento $intento): array
    {
        return match ($intento->prueba->tipo) {
            'tmt' => $intento->tmtResultados->map(fn ($resultado) => [
                'concepto' => "Parte {$resultado->parte}",
                'valor' => $resultado->tiempo_segundos,
                'unidad' => 'segundos',
                'limite_oficial' => $resultado->parte === 'A' ? 100 : 300,
                'aprobado' => $resultado->completado,
            ])->values()->all(),
            'rejilla' => $intento->rejillaResultados->map(fn ($resultado) => [
                'concepto' => $resultado->variante === 'caballo' ? 'Rejilla del caballo (Núñez Nieto)' : 'Rejilla estándar (Harris y Harris)',
                'valor' => $resultado->aciertos,
                'unidad' => 'números señalados',
                'maximo' => 100,
                'nivel' => $resultado->nivel,
                'bandas' => $resultado->bandasAplicables(),
            ])->values()->all(),
            default => $intento->resultados->map(fn ($resultado) => [
                'concepto' => $resultado->categoria->nombre,
                'valor' => (float) $resultado->puntaje,
                'etiqueta' => $resultado->etiqueta_interpretacion,
                'bandas' => $resultado->categoria->interpretaciones->map(fn ($banda) => [
                    'etiqueta' => $banda->etiqueta,
                    'valor_min' => (float) $banda->valor_min,
                    'valor_max' => (float) $banda->valor_max,
                ])->values()->all(),
            ])->values()->all(),
        };
    }
}
