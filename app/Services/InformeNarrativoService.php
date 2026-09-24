<?php

namespace App\Services;

use App\Models\Intento;

/**
 * Arma el texto narrativo del informe visual (párrafos + recomendaciones), transcrito tal
 * cual del ejemplo oficial (docs/Ejemplo informe con todas las pruebas de 6 a 11.docx).
 *
 * El documento da un solo resultado por prueba, pero varias de nuestras pruebas guardan
 * más de un puntaje (TMT: partes A y B: Ansiedad: 3 subescalas; HEMA: 8 secciones). Donde
 * el documento no dice cómo combinarlos, se usa el criterio más conservador (el peor/más
 * alto de los sub-resultados), documentado en cada método.
 */
class InformeNarrativoService
{
    /**
     * @return ?array{titulo: string, parrafos: array<int, string>, recomendaciones: ?array<int, string>}
     */
    public function narrativaDe(Intento $intento): ?array
    {
        $intento->loadMissing(['estudiante', 'prueba.categorias', 'resultados.categoria', 'tmtResultados', 'rejillaResultados']);

        return match (true) {
            $intento->prueba->tipo === 'tmt' => $this->tmt($intento),
            $intento->prueba->tipo === 'rejilla' => $this->rejilla($intento),
            str_contains($intento->prueba->titulo, 'Ansiedad') => $this->ansiedad($intento),
            str_contains($intento->prueba->titulo, 'Procrastinación') => $this->procrastinacion($intento),
            str_contains($intento->prueba->titulo, 'CHASIDE') => $this->chaside($intento),
            str_contains($intento->prueba->titulo, 'Gardner') => $this->gardner($intento),
            str_contains($intento->prueba->titulo, 'PNL') => $this->vak($intento),
            str_contains($intento->prueba->titulo, 'HEMA') => $this->hema($intento),
            default => null,
        };
    }

    /**
     * Norma oficial por edad (docs/Ejemplo informe...): tiempo de referencia en segundos.
     * Por debajo del 80 % del tiempo de referencia = alto; hasta 120 % = medio; más = bajo
     * (a menor tiempo, mejor desempeño). Con las dos partes, se informa la más baja de las
     * dos (criterio conservador: se reporta la dificultad más marcada, no el promedio).
     */
    private function tmt(Intento $intento): array
    {
        $edad = $intento->estudiante->fecha_nacimiento?->age;
        $mayorDe15 = $edad !== null && $edad >= 15;
        $normas = ['A' => $mayorDe15 ? 45 : 29, 'B' => $mayorDe15 ? 101 : 50];

        $orden = ['alto' => 0, 'medio' => 1, 'bajo' => 2];
        $peor = 'alto';

        foreach ($intento->tmtResultados as $resultado) {
            $norma = $normas[$resultado->parte];
            $nivel = match (true) {
                $resultado->tiempo_segundos <= $norma * 0.8 => 'alto',
                $resultado->tiempo_segundos <= $norma * 1.2 => 'medio',
                default => 'bajo',
            };
            if ($orden[$nivel] > $orden[$peor]) {
                $peor = $nivel;
            }
        }

        $textos = [
            'alto' => 'El estudiante presentó un desempeño alto en el Trail Making Test (TMT), evidenciando una ejecución ágil y precisa de las tareas propuestas. Durante la aplicación logró mantener adecuadamente la secuencia de los estímulos, con un tiempo de respuesta favorable y un bajo número de errores. Este desempeño refleja un manejo eficiente de las demandas específicas de la actividad, relacionadas con atención visual, seguimiento secuencial, velocidad de procesamiento y flexibilidad cognitiva.',
            'medio' => 'El estudiante presentó un desempeño medio en el Trail Making Test (TMT), mostrando una ejecución funcional de las tareas propuestas. Se observó un manejo adecuado de la secuencia de los estímulos, aunque con un tiempo de ejecución y/o cantidad de errores ubicado en un rango intermedio respecto al grupo de referencia establecido para el estudio. Este resultado sugiere oportunidades de fortalecimiento en aspectos relacionados con la atención, velocidad de procesamiento, seguimiento de secuencias y flexibilidad cognitiva.',
            'bajo' => 'El estudiante presentó un desempeño bajo en el Trail Making Test (TMT), observándose un mayor tiempo de ejecución y/o dificultades en el seguimiento de la secuencia establecida, acompañadas de errores o pausas durante la actividad. Este resultado evidencia un desempeño menor en las condiciones específicas de la tarea y puede ser considerado como un elemento orientativo para fortalecer habilidades relacionadas con atención, seguimiento de instrucciones, organización y velocidad de ejecución.',
        ];

        return [
            'titulo' => 'Trail Making Test (TMT), partes A y B',
            'parrafos' => [$textos[$peor]],
            'recomendaciones' => $peor === 'bajo' ? [
                'Dar instrucciones cortas, claras y secuenciales, verificando la comprensión antes de iniciar la actividad.',
                'Dividir las actividades extensas en pasos pequeños, permitiendo que el estudiante complete una instrucción antes de pasar a la siguiente.',
                'Utilizar apoyos visuales, como listas de pasos, esquemas, ejemplos, pictogramas, colores o palabras clave para facilitar el seguimiento de secuencias.',
                'Proporcionar tiempo adicional para actividades que impliquen búsqueda visual, organización de información, seguimiento de secuencias o cambios entre diferentes criterios.',
                'Reducir distractores ambientales durante tareas que requieran concentración sostenida.',
                'Realizar ejercicios de atención y secuenciación, por ejemplo, ordenar números, letras, imágenes, instrucciones o acontecimientos.',
                'Fortalecer la flexibilidad cognitiva mediante actividades que impliquen cambiar de una regla a otra, clasificar objetos de diferentes maneras o alternar entre dos criterios.',
                'Favorecer la revisión del trabajo, enseñándole a comprobar los pasos realizados e identificar posibles errores antes de finalizar.',
                'Utilizar pausas breves y estructuradas en actividades de mayor duración para prevenir la fatiga atencional.',
                'Reforzar positivamente el esfuerzo y la estrategia utilizada, evitando centrar la retroalimentación únicamente en la rapidez.',
                'Evitar interpretar el resultado de manera aislada; se recomienda integrarlo con el desempeño académico, la observación en el aula y los demás resultados de la valoración.',
            ] : null,
        ];
    }

    private function rejilla(Intento $intento): array
    {
        return [
            'titulo' => 'Test de Rejilla',
            'parrafos' => $intento->rejillaResultados->map(fn ($r) => ($r->variante === 'caballo' ? 'Rejilla del caballo (Núñez Nieto): ' : 'Rejilla estándar (Harris y Harris): ').$r->nivel
            )->values()->all(),
            'recomendaciones' => null,
        ];
    }

    /**
     * 3 subescalas (Cognitivas/Fisiológicas/Motoras), cada una Bajo/Medio/Alto. Se informa
     * la más alta de las 3 (criterio conservador).
     */
    private function ansiedad(Intento $intento): array
    {
        $orden = ['Bajo' => 0, 'Medio' => 1, 'Alto' => 2];
        $peor = 'Bajo';
        foreach ($intento->resultados as $resultado) {
            $etiqueta = $resultado->etiqueta_interpretacion;
            if ($etiqueta && ($orden[$etiqueta] ?? 0) > $orden[$peor]) {
                $peor = $etiqueta;
            }
        }

        return match ($peor) {
            'Bajo' => [
                'titulo' => 'Cuestionario de Autoevaluación de la Ansiedad ante los Exámenes',
                'parrafos' => ['El estudiante presenta un nivel bajo de ansiedad ante los exámenes, lo que indica que, en general, logra afrontar las situaciones de evaluación con adecuada tranquilidad y control emocional. Puede experimentar cierto grado de preocupación o nerviosismo antes o durante una prueba, pero estas respuestas no parecen interferir significativamente en su concentración, desempeño académico o capacidad para responder a las actividades evaluativas.'],
                'recomendaciones' => null,
            ],
            'Medio' => [
                'titulo' => 'Cuestionario de Autoevaluación de la Ansiedad ante los Exámenes',
                'parrafos' => ['El estudiante presenta un nivel medio de ansiedad ante los exámenes, evidenciando algunas manifestaciones de preocupación, tensión o nerviosismo asociadas a las situaciones de evaluación. Estas respuestas pueden presentarse especialmente ante exámenes considerados importantes o de mayor dificultad y, en determinados momentos, podrían afectar parcialmente la concentración, la confianza o el desempeño académico. Se recomienda fortalecer estrategias de preparación, organización del tiempo y regulación emocional.'],
                'recomendaciones' => [
                    'Fortalecer hábitos y rutinas de estudio, distribuyendo la preparación de los exámenes con suficiente anticipación.',
                    'Enseñar estrategias de organización y planificación del tiempo para evitar la acumulación de actividades antes de las evaluaciones.',
                    'Practicar técnicas sencillas de respiración y relajación antes y durante los exámenes.',
                    'Favorecer ambientes de evaluación tranquilos, con instrucciones claras y verificando que el estudiante comprenda las consignas.',
                    'Promover pausas activas y ejercicios breves de regulación emocional durante jornadas académicas extensas.',
                    'Reforzar positivamente el esfuerzo y el proceso de aprendizaje, evitando centrar la retroalimentación exclusivamente en la calificación.',
                    'Realizar seguimiento al desempeño académico y a las manifestaciones de ansiedad para identificar posibles cambios o situaciones que requieran mayor apoyo.',
                ],
            ],
            default => [
                'titulo' => 'Cuestionario de Autoevaluación de la Ansiedad ante los Exámenes',
                'parrafos' => ['El estudiante presenta un nivel alto de ansiedad ante los exámenes, evidenciando una respuesta significativa de preocupación, tensión o nerviosismo frente a las situaciones de evaluación. Estas manifestaciones podrían interferir con procesos como la concentración, recuperación de información, confianza en las propias capacidades y desempeño académico. Se recomienda implementar estrategias de manejo de ansiedad, técnicas de respiración y relajación, planificación del estudio y acompañamiento pedagógico, realizando seguimiento a la evolución del estudiante.'],
                'recomendaciones' => [
                    'Implementar un acompañamiento pedagógico individualizado que permita identificar las situaciones académicas que generan mayor ansiedad.',
                    'Establecer un plan de preparación progresiva para los exámenes, evitando sesiones extensas de estudio de último momento.',
                    'Entrenar de manera frecuente técnicas de respiración diafragmática, relajación y autorregulación emocional.',
                    'Dividir las actividades evaluativas complejas en instrucciones o pasos claramente estructurados cuando sea pedagógicamente pertinente.',
                    'Brindar anticipadamente información sobre fechas, contenidos, criterios de evaluación y tipo de preguntas para disminuir la incertidumbre.',
                    'Favorecer estrategias de afrontamiento como autoinstrucciones positivas, pausas breves y lectura organizada de las preguntas.',
                    'Evitar comparaciones con otros estudiantes y fortalecer la percepción de competencia y confianza en sus propias capacidades.',
                ],
            ],
        };
    }

    private function procrastinacion(Intento $intento): array
    {
        $puntaje = (float) ($intento->resultados->first()?->puntaje ?? 0);

        $parrafo = match (true) {
            $puntaje >= 48 => 'El estudiante presenta un nivel alto de procrastinación, evidenciando una tendencia marcada a posponer el inicio o la finalización de actividades académicas, incluso cuando reconoce su importancia. Puede presentar dificultades para organizar el tiempo, establecer prioridades y mantener la constancia en sus responsabilidades, lo que podría favorecer la acumulación de tareas y generar presión frente a los plazos de entrega. Se recomienda implementar un plan estructurado de organización, establecer metas pequeñas y progresivas y realizar seguimiento periódico al cumplimiento de las actividades.',
            $puntaje >= 40 => 'El estudiante presenta un nivel regular de procrastinación, evidenciando algunas conductas de postergación de sus responsabilidades académicas, aunque estas no necesariamente se presentan de manera constante. Puede beneficiarse del fortalecimiento de estrategias de organización, planificación y priorización de tareas para favorecer un cumplimiento más oportuno y sostenido de sus actividades.',
            $puntaje >= 35 => 'El estudiante presenta un nivel bajo de procrastinación, lo que indica una adecuada tendencia a iniciar y desarrollar sus actividades académicas de manera oportuna. Generalmente logra organizar sus responsabilidades y cumplir con los tiempos establecidos. Se recomienda mantener las estrategias de planificación y organización que favorecen su desempeño académico.',
            default => 'El estudiante presenta un comportamiento caracterizado por la ausencia de procrastinación significativa, evidenciando disposición para iniciar y finalizar sus actividades académicas dentro de los tiempos establecidos. Se observa una adecuada organización, cumplimiento y responsabilidad frente a sus compromisos. Se recomienda conservar y fortalecer estos hábitos como recursos favorables para su proceso de aprendizaje y desempeño académico.',
        };

        return ['titulo' => 'Escala de Procrastinación Académica', 'parrafos' => [$parrafo], 'recomendaciones' => null];
    }

    /**
     * @var array<string, string>
     */
    private const RASGOS_CHASIDE = [
        'Área Administrativa' => 'organización, supervisión, orden, análisis y síntesis, cálculo y persuasión',
        'Área de Humanidades y Ciencias Sociales y Jurídicas' => 'precisión verbal, organización, relación de hechos, orden y sentido de la justicia',
        'Área Artística' => 'sensibilidad estética, creatividad, imaginación y percepción visual o auditiva',
        'Área de Ciencias de la Salud' => 'vocación de ayuda, paciencia, análisis y trato con personas',
        'Área de Enseñanzas Técnicas' => 'cálculo, exactitud, planificación y gusto por lo práctico',
        'Área de Defensa y Seguridad' => 'sentido de la justicia, espíritu de equipo, liderazgo y valentía',
        'Área de Ciencias Experimentales' => 'investigación, orden, análisis y observación metódica',
    ];

    /**
     * El documento llena 2 espacios con las áreas de mayor puntaje y describe intereses y
     * aptitudes; nuestra prueba solo guarda un puntaje por área (no separa intereses de
     * aptitudes), así que ambos espacios usan el área de mayor puntaje.
     */
    private function chaside(Intento $intento): array
    {
        $top = $intento->resultados->sortByDesc(fn ($r) => (float) $r->puntaje)->values();
        $nombre = fn ($r) => preg_replace('/^[A-Z] - /', '', $r->categoria->nombre);
        $primero = $top->get(0);
        $segundo = $top->get(1);

        if (! $primero) {
            return ['titulo' => 'Test de Orientación Vocacional CHASIDE', 'parrafos' => [], 'recomendaciones' => null];
        }

        $nombre1 = $nombre($primero);
        $nombre2 = $segundo ? $nombre($segundo) : $nombre1;
        $rasgos = self::RASGOS_CHASIDE[$nombre1] ?? 'sus fortalezas identificadas';

        return [
            'titulo' => 'Test de Orientación Vocacional CHASIDE',
            'parrafos' => [
                "El estudiante obtuvo sus mayores puntuaciones en las áreas de {$nombre1} y {$nombre2}, evidenciando una mayor afinidad hacia actividades, intereses y características relacionadas con estos campos. De acuerdo con el perfil obtenido, se identifican preferencias que pueden orientar la exploración de alternativas académicas y ocupacionales relacionadas con dichas áreas.",
                "En el componente de intereses, se observa mayor inclinación hacia {$nombre1}, mientras que en el componente de aptitudes se destacan características asociadas con {$rasgos}. Estos resultados constituyen un referente orientativo para el proceso de exploración vocacional y deben complementarse con los intereses personales, experiencias académicas, habilidades percibidas, expectativas y contexto del estudiante.",
            ],
            'recomendaciones' => null,
        ];
    }

    /**
     * @var array<string, array{definicion: string, ejercicios: array<int, string>}>
     */
    private const GARDNER = [
        'Verbal / Lingüística' => [
            'definicion' => 'Comprende la capacidad de emplear efectivamente las palabras ya sea en forma oral y escrita. La utilizamos cuando hablamos en una conversación formal o informal, cuando ponemos pensamientos por escrito, escribimos poemas, o escribimos una carta a un amigo. Es la capacidad de traducir en palabras adecuadas, pertinentes y exactas lo que piensa. Según Gardner este tipo de capacidad está en su forma más completa en los poetas.',
            'ejercicios' => [
                'Promover lectura, escritura de textos, narraciones y exposiciones orales.',
                'Utilizar debates, mesas redondas y actividades de argumentación.',
                'Solicitar que explique con sus propias palabras lo aprendido.',
                'Favorecer la elaboración de resúmenes, ensayos, cuentos o diarios de aprendizaje.',
            ],
        ],
        'Lógica / Matemática' => [
            'definicion' => 'Consiste en la capacidad para utilizar los números en forma efectiva y para razonar en forma lógica. Está a menudo asociada con lo que llamamos el pensamiento científico. Utilizamos esta inteligencia cuando podemos realizar patrones abstractos, como contar de 2 en 2 o saber si hemos recibido el vuelto correcto en el supermercado, también lo usamos para encontrar conexiones o ver relaciones entre trozos de información.',
            'ejercicios' => [
                'Incorporar problemas, ejercicios de razonamiento y situaciones de la vida cotidiana.',
                'Utilizar secuencias, clasificaciones, patrones y actividades de análisis.',
                'Plantear retos que requieran establecer relaciones, comparar y encontrar soluciones.',
                'Favorecer el uso de tablas, esquemas y organizadores de información.',
            ],
        ],
        'Visual / Espacial' => [
            'definicion' => 'Consiste en la capacidad de percibir el mundo visual espacial adecuadamente. Utilizamos esta inteligencia cuando hacemos un dibujo para expresar nuestros pensamientos o nuestras emociones, o cuando decoramos una pieza para crear cierta atmósfera, o cuando jugamos al ajedrez. Nos permite visualizar las cosas que queremos en nuestras vidas. Es la capacidad para formarse un modelo mental de un espacio y para maniobrar y operar usando ese modelo. Requieren de esta clase de inteligencia, de modo especial, los marinos, ingenieros, cirujanos, escultores, pintores.',
            'ejercicios' => [
                'Utilizar mapas conceptuales, diagramas, imágenes, gráficos y representaciones visuales.',
                'Proponer actividades de dibujo, diseño, construcción y organización espacial.',
                'Emplear colores y códigos visuales para facilitar la comprensión de contenidos.',
                'Permitir representar conceptos mediante imágenes o esquemas.',
            ],
        ],
        'Corporal / Cinestésica' => [
            'definicion' => 'Se encuentra en la capacidad para utilizar el cuerpo entero en expresar ideas y sentimientos. Esta inteligencia se vería cuando en el teclado se escribe una carta, si ando en bicicleta, si se está en un auto o mantener el equilibrio al caminar. Es la capacidad para resolver problemas o para elaborar productos empleando el cuerpo o parte del mismo. Muestran esta clase de inteligencia en un nivel superior, los bailarines, los atletas, los cirujanos y artesanos.',
            'ejercicios' => [
                'Incorporar actividades prácticas, experimentos, dramatizaciones y simulaciones.',
                'Favorecer el aprendizaje mediante movimiento y manipulación de materiales.',
                'Relacionar los contenidos académicos con situaciones prácticas.',
                'Alternar actividades de concentración con pausas activas breves.',
            ],
        ],
        'Musical / Rítmica' => [
            'definicion' => 'Es la capacidad que algunos poseen, a través de formas musicales, percibir, discriminar y juzgar, transformar y expresar. Utilizamos esta inteligencia cuando tocamos música, para calmarnos o estimularnos. Está muy presente cuando al escuchar alguna música la repetimos en la mente todo el día. Implica el aprecio por la música, el canto, el tocar un instrumento musical, etc. Entre ellos están los buenos cantantes, los canta-autores.',
            'ejercicios' => [
                'Utilizar canciones, ritmos, sonidos o recursos auditivos como apoyo al aprendizaje.',
                'Permitir crear rimas, canciones o recursos sonoros para recordar conceptos.',
                'Relacionar determinados contenidos con patrones rítmicos.',
                'Incorporar actividades que combinen escucha, ritmo y expresión.',
            ],
        ],
        'Intrapersonal' => [
            'definicion' => 'Es la capacidad para comprenderse a uno mismo y para actuar en forma autorreflexiva y de acostumbrarse a ello. También se llama inteligencia "introspectiva". Nos permite reflexionar acerca de nosotros mismos. Involucra el conocimiento y el darnos cuenta de los aspectos internos de la persona, tales como los sentimientos, el proceso pensante y la intuición acerca de realidades espirituales. Es la capacidad de auto-comprenderse, de conocerse bien, de saber cuáles son los lados brillantes de uno y cuáles son los lados opacos de la propia personalidad.',
            'ejercicios' => [
                'Promover actividades de autoevaluación y reflexión sobre el propio aprendizaje.',
                'Establecer metas académicas individuales y realizar seguimiento de avances.',
                'Utilizar diarios o registros de aprendizaje.',
                'Brindar espacios para identificar fortalezas, dificultades y estrategias personales.',
            ],
        ],
        'Interpersonal' => [
            'definicion' => 'Es la capacidad de captar y evaluar en forma rápida los estados de ánimo, intenciones, motivaciones, sentimientos de los demás. La experimentamos en forma más directa cuando formamos parte de un trabajo en equipo ya sea deportivo, en la iglesia o tarea comunitaria. Nos permite desarrollar un sentido de empatía y de preocupación por el tema. También nos permite mantener nuestra identidad individual. Capacidad de entender a las otras personas. Entre ellos están los ministros, los religiosos, los orientadores, los psicólogos, los buenos vendedores.',
            'ejercicios' => [
                'Favorecer trabajos colaborativos y proyectos grupales.',
                'Promover actividades de cooperación, diálogo y resolución de conflictos.',
                'Asignar roles dentro de los equipos de trabajo.',
                'Utilizar tutorías entre pares y espacios de intercambio de ideas.',
            ],
        ],
    ];

    private function gardner(Intento $intento): array
    {
        $top = $intento->resultados->sortByDesc(fn ($r) => (float) $r->puntaje)->first();

        if (! $top) {
            return ['titulo' => 'Test de las Inteligencias Múltiples (H. Gardner)', 'parrafos' => [], 'recomendaciones' => null];
        }

        $nombre = trim(preg_replace('/^[A-Z]\.\s*/', '', $top->categoria->nombre));
        $info = self::GARDNER[$nombre] ?? null;

        return [
            'titulo' => 'Test de las Inteligencias Múltiples (H. Gardner)',
            'parrafos' => $info ? ["Inteligencia predominante: {$nombre}.", $info['definicion']] : ["Inteligencia predominante: {$nombre}."],
            'recomendaciones' => $info['ejercicios'] ?? null,
        ];
    }

    /**
     * @var array<string, string>
     */
    private const VAK = [
        'Visual' => 'El estudiante presenta un estilo de aprendizaje predominantemente visual, mostrando una mayor tendencia a comprender, organizar y recordar la información cuando esta se presenta mediante imágenes, gráficos, esquemas, mapas conceptuales, diagramas, colores y recursos escritos. Puede beneficiarse de la información estructurada visualmente y de ejemplos que permitan representar de manera concreta los contenidos abordados.',
        'Auditivo' => 'El estudiante presenta un estilo de aprendizaje predominantemente auditivo, evidenciando una mayor tendencia a comprender y retener la información mediante explicaciones verbales, conversaciones, debates, lecturas en voz alta, instrucciones orales y actividades que impliquen escuchar y expresar ideas. Puede favorecerse de la interacción verbal y de la posibilidad de explicar oralmente lo aprendido.',
        'Cinestésico' => 'El estudiante presenta un estilo de aprendizaje predominantemente kinestésico, mostrando una mayor tendencia a favorecer el aprendizaje mediante la práctica, el movimiento, la experimentación, la manipulación de materiales y la participación activa en las actividades. Puede beneficiarse de experiencias prácticas, ejercicios aplicados, simulaciones, proyectos y actividades que permitan relacionar los contenidos con situaciones concretas.',
    ];

    private function vak(Intento $intento): array
    {
        $top = $intento->resultados->sortByDesc(fn ($r) => (float) $r->puntaje)->first();
        $nombre = $top?->categoria->nombre;
        $texto = self::VAK[$nombre] ?? null;

        return [
            'titulo' => 'Cuestionario de Estilos de Aprendizaje basado en el modelo PNL',
            'parrafos' => $texto ? [
                "Resultado — Estilo {$nombre}",
                $texto,
                'Nota: estos resultados describen una preferencia o tendencia en la forma de abordar las actividades de aprendizaje y no determinan de manera fija la capacidad académica del estudiante.',
            ] : [],
            'recomendaciones' => null,
        ];
    }

    /**
     * El documento da un solo párrafo general (no uno por sección); se usa la etiqueta
     * (Alto/Bajo) que predomina entre las 8 secciones.
     */
    private function hema(Intento $intento): array
    {
        $altos = $intento->resultados->filter(fn ($r) => $r->etiqueta_interpretacion === 'Alto')->count();
        $bajos = $intento->resultados->count() - $altos;

        if ($altos >= $bajos) {
            return [
                'titulo' => 'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)',
                'parrafos' => [
                    'Los resultados obtenidos en el Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA) evidencian condiciones favorables en las diferentes dimensiones evaluadas. Se observan recursos relacionados con la organización del tiempo, los métodos de estudio, la preparación para los exámenes, la búsqueda de información, la comunicación académica y la motivación para aprender. Estos resultados sugieren la presencia de hábitos y estrategias que pueden favorecer su adaptación y desempeño en el contexto académico.',
                    'Se recomienda mantener las estrategias que actualmente le resultan efectivas, revisarlas periódicamente y continuar fortaleciendo aquellas áreas que puedan contribuir a un aprendizaje autónomo, organizado y sostenible.',
                ],
                'recomendaciones' => null,
            ];
        }

        return [
            'titulo' => 'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)',
            'parrafos' => [
                'Los resultados obtenidos en el Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA) permiten identificar algunas áreas que requieren fortalecimiento para favorecer el desempeño académico. Los resultados no constituyen por sí mismos un diagnóstico, sino que orientan la identificación de hábitos, estrategias y condiciones que pueden estar influyendo en la experiencia de aprendizaje.',
                'Se recomienda establecer un plan de mejoramiento progresivo, priorizando inicialmente las dimensiones con menor puntuación. Para ello, puede ser útil:',
            ],
            'recomendaciones' => [
                'Organizar horarios de estudio.',
                'Establecer metas académicas concretas.',
                'Fortalecer las técnicas de aprendizaje.',
                'Reducir distractores y desarrollar estrategias para el manejo del estrés y la ansiedad ante las evaluaciones.',
            ],
        ];
    }
}
