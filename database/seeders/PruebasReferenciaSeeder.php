<?php

namespace Database\Seeders;

use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use App\Services\TmtLayoutService;
use Illuminate\Database\Seeder;

/**
 * Siembra pruebas reales (tomadas de docs/) para poder ver la UI con contenido real
 * en lugar de datos vacíos. Ver los PDF de referencia en docs/.
 */
class PruebasReferenciaSeeder extends Seeder
{
    public function run(): void
    {
        $evaluador = User::where('role', 'evaluador')->first();

        if (! $evaluador) {
            return;
        }

        $this->crearCuestionarioAnsiedad($evaluador);
        $this->crearInteligenciasMultiples($evaluador);
        $this->crearTmt($evaluador);
        $this->crearProcrastinacionAcademica($evaluador);
        $this->crearChaside($evaluador);
        $this->crearEstilosAprendizaje($evaluador);
    }

    private function crearTmt(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'tmt',
            'titulo' => 'Trail Making Test (TMT)',
            'instrucciones' => 'Une los círculos en el orden indicado lo más rápido que puedas, sin levantar el dedo del recorrido.',
            'estado' => 'publicada',
        ]);

        app(TmtLayoutService::class)->generar($prueba);
    }

    private function crearCuestionarioAnsiedad(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => 'Cuestionario de Autoevaluación de la Ansiedad ante los Exámenes',
            'instrucciones' => 'Indica con qué frecuencia te ocurre cada situación: 1 = nunca o casi nunca, 5 = siempre o casi siempre.',
            'estado' => 'publicada',
        ]);

        $categoriasPorNumero = [
            1 => 'Cognitivas', 4 => 'Cognitivas', 8 => 'Cognitivas', 11 => 'Cognitivas', 14 => 'Cognitivas',
            2 => 'Fisiológicas', 5 => 'Fisiológicas', 7 => 'Fisiológicas', 10 => 'Fisiológicas', 12 => 'Fisiológicas', 13 => 'Fisiológicas',
            3 => 'Motoras', 6 => 'Motoras', 9 => 'Motoras', 15 => 'Motoras',
        ];

        $categorias = [];
        foreach (['Cognitivas' => 1, 'Fisiológicas' => 2, 'Motoras' => 3] as $nombre => $orden) {
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => "Manifestaciones {$nombre}",
                'tipo_puntuacion' => 'PROMEDIO',
                'orden' => $orden,
            ]);

            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 1, 'valor_max' => 3, 'etiqueta' => 'Bajo',
                'recomendacion' => 'Tu nivel de ansiedad ante los exámenes está dentro de un rango saludable. Mantené tus hábitos de estudio y descanso.',
            ]);
            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 3.01, 'valor_max' => 4, 'etiqueta' => 'Medio',
                'recomendacion' => 'Notás algo de ansiedad ante los exámenes. Organizar el estudio con anticipación y practicar técnicas de respiración puede ayudarte.',
            ]);
            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 4.01, 'valor_max' => 5, 'etiqueta' => 'Alto',
                'recomendacion' => 'Tu nivel de ansiedad es alto. Te recomendamos hablar con un orientador o psicólogo escolar, y practicar técnicas de relajación antes de rendir.',
            ]);

            $categorias[$nombre] = $categoria;
        }

        $textos = [
            1 => 'Estoy muy preocupado por los exámenes.',
            2 => 'Tengo palpitaciones, opresión en el pecho, me falta el aire, respiro muy rápido...',
            3 => 'Me siento entumecido, torpe, rígido, agarrotado...',
            4 => 'Siento miedo, estoy inquieto. Duermo mal: no me puedo dormir, tengo un sueño irregular.',
            5 => 'Siento molestias en el estómago: náuseas, mareo. Tengo diarrea.',
            6 => 'Como y bebo a deshoras, continuamente o demasiado. Fumo a todas horas, más de lo habitual en mí.',
            7 => 'Se me "cierra el estómago": no puedo comer.',
            8 => 'Me asaltan pensamientos como: voy a suspender, no sé nada, me voy a quedar en blanco...',
            9 => 'Tartamudeo, me cuesta explicarme.',
            10 => 'Me tiemblan las manos, tengo hormigueos por los brazos y piernas.',
            11 => 'Me siento inseguro: no me acuerdo de nada, no me vienen las palabras... "Quizás no deba ir al examen."',
            12 => 'Tengo la boca seca, no puedo tragar...',
            13 => 'Sudo. Siento escalofríos, tengo sofocos.',
            14 => 'Estoy triste, tengo ganas de llorar.',
            15 => 'Hago movimientos repetidos con algunas partes de mi cuerpo. Tengo tics nerviosos.',
        ];

        $etiquetasEscala = [1 => 'Nunca o casi nunca', 2 => 'Rara vez', 3 => 'A veces', 4 => 'Frecuentemente', 5 => 'Siempre o casi siempre'];

        for ($numero = 1; $numero <= 15; $numero++) {
            $categoria = $categorias[$categoriasPorNumero[$numero]];

            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => $categoria->id,
                'texto' => $textos[$numero],
                'tipo' => 'escala',
                'orden' => $numero,
            ]);

            foreach ($etiquetasEscala as $valor => $etiqueta) {
                OpcionRespuesta::create([
                    'pregunta_id' => $pregunta->id,
                    'texto' => "{$valor} - {$etiqueta}",
                    'peso' => $valor,
                    'orden' => $valor,
                ]);
            }
        }
    }

    private function crearInteligenciasMultiples(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => 'Test de las Inteligencias Múltiples (H. Gardner)',
            'instrucciones' => 'Marca "Verdadero" si la afirmación refleja una característica tuya, o "Falso" si no la refleja.',
            'estado' => 'publicada',
        ]);

        $categoriasInfo = [
            'A' => ['nombre' => 'Verbal / Lingüística', 'preguntas' => [9, 10, 17, 22, 30]],
            'B' => ['nombre' => 'Lógica / Matemática', 'preguntas' => [5, 7, 15, 20, 25]],
            'C' => ['nombre' => 'Visual / Espacial', 'preguntas' => [1, 11, 14, 23, 27]],
            'D' => ['nombre' => 'Corporal / Cinestésica', 'preguntas' => [8, 16, 19, 21, 29]],
            'E' => ['nombre' => 'Musical / Rítmica', 'preguntas' => [3, 4, 13, 24, 28]],
            'F' => ['nombre' => 'Intrapersonal', 'preguntas' => [2, 6, 26, 31, 33]],
            'G' => ['nombre' => 'Interpersonal', 'preguntas' => [12, 18, 32, 34, 35]],
        ];

        $categorias = [];
        $categoriaPorNumero = [];
        $orden = 0;
        foreach ($categoriasInfo as $letra => $info) {
            $orden++;
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => "{$letra}. {$info['nombre']}",
                'tipo_puntuacion' => 'CONTEO',
                'orden' => $orden,
            ]);

            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 3, 'etiqueta' => 'Estándar',
                'recomendacion' => "No es una de tus inteligencias más marcadas todavía, pero podés desarrollarla con práctica si te interesa la {$info['nombre']}.",
            ]);
            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 4, 'valor_max' => 5, 'etiqueta' => 'Fortaleza',
                'recomendacion' => "Esta es una de tus fortalezas: {$info['nombre']}. Aprovechala en cómo elegís estudiar y en tus proyectos.",
            ]);

            $categorias[$letra] = $categoria;
            foreach ($info['preguntas'] as $numero) {
                $categoriaPorNumero[$numero] = $letra;
            }
        }

        $textos = [
            1 => 'Prefiero hacer un mapa que explicarle a alguien cómo tiene que llegar a un lugar determinado.',
            2 => 'Si estoy enojado o contento generalmente sé la razón exacta de por qué es así.',
            3 => 'Sé tocar, o antes sabía, un instrumento musical.',
            4 => 'Asocio la música con mis estados de ánimo.',
            5 => 'Puedo sumar o multiplicar mentalmente con mucha rapidez.',
            6 => 'Puedo ayudar a un amigo(a) a manejar y controlar sus sentimientos, porque yo lo pude hacer antes en relación a sentimientos parecidos.',
            7 => 'Me gusta trabajar con calculadora y computadoras.',
            8 => 'Aprendo rápidamente a bailar un baile nuevo.',
            9 => 'No me es difícil decir lo que pienso durante una discusión o debate.',
            10 => '¿Disfruto de una buena charla, prédica o sermón?',
            11 => 'Siempre distingo el Norte del Sur, esté donde esté.',
            12 => 'Me gusta reunir grupos de personas en una fiesta o evento especial.',
            13 => 'Realmente la vida me parece vacía sin música.',
            14 => 'Siempre entiendo los gráficos que vienen en las instrucciones de equipos o instrumentos.',
            15 => 'Me gusta resolver puzzles y entretenerme con juegos electrónicos.',
            16 => 'Me fue fácil aprender a andar en bicicleta o patines.',
            17 => 'Me enojo cuando escucho una discusión o una afirmación que me parece ilógica o absurda.',
            18 => 'Soy capaz de convencer a otros que sigan mis planes o ideas.',
            19 => 'Tengo buen sentido del equilibrio y de coordinación.',
            20 => 'A menudo puedo captar relaciones entre números con mayor rapidez y facilidad que algunos de mis compañeros.',
            21 => 'Me gusta construir modelos, maquetas o hacer esculturas.',
            22 => 'Soy bueno para encontrar el significado preciso de las palabras.',
            23 => 'Puedo mirar un objeto de una manera y con la misma facilidad verlo dado vuelta o al revés.',
            24 => 'Con frecuencia establezco la relación que puede haber entre una música o canción y algo que haya ocurrido en mi vida.',
            25 => 'Me gusta trabajar con números y figuras.',
            26 => 'Me gusta sentarme muy callado y pensar, reflexionar sobre mis sentimientos más íntimos.',
            27 => 'Solamente con mirar las formas de las construcciones y estructuras me siento a gusto.',
            28 => 'Cuando estoy en la ducha, o cuando estoy solo me gusta tararear, cantar o silbar.',
            29 => 'Soy bueno para el atletismo.',
            30 => 'Me gusta escribir cartas largas a mis amigos.',
            31 => 'Generalmente me doy cuenta de la expresión o gestos que tengo en la cara.',
            32 => 'Muchas veces me doy cuenta de las expresiones o gestos en la cara de las otras personas.',
            33 => 'Reconozco mis estados de ánimo, no me cuesta identificarlos.',
            34 => 'Me doy cuenta de los estados de ánimo de las personas con quienes me encuentro.',
            35 => 'Me doy cuenta bastante bien de lo que los otros piensan de mí.',
        ];

        for ($numero = 1; $numero <= 35; $numero++) {
            $categoria = $categorias[$categoriaPorNumero[$numero]];

            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => $categoria->id,
                'texto' => $textos[$numero],
                'tipo' => 'verdadero_falso',
                'orden' => $numero,
            ]);

            OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'Verdadero', 'peso' => 1, 'orden' => 1]);
            OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'Falso', 'peso' => 0, 'orden' => 2]);
        }
    }

    private function crearProcrastinacionAcademica(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => 'Escala de Procrastinación Académica',
            'instrucciones' => 'Lee cada afirmación sobre tu forma de estudiar y elegí la opción que más se ajuste a vos.',
            'estado' => 'publicada',
        ]);

        $categoria = CategoriaEvaluacion::create([
            'prueba_id' => $prueba->id,
            'nombre' => 'Procrastinación',
            'tipo_puntuacion' => 'SUMA_PONDERADA',
            'orden' => 1,
        ]);

        InterpretacionCategoria::create([
            'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 34, 'etiqueta' => 'No procrastina',
            'recomendacion' => 'Manejás bien tus tiempos de estudio. Seguí organizándote con anticipación.',
        ]);
        InterpretacionCategoria::create([
            'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 35, 'valor_max' => 39, 'etiqueta' => 'Baja procrastinación',
            'recomendacion' => 'De vez en cuando dejás tareas para último momento. Anotar fechas límite con anticipación puede ayudarte.',
        ]);
        InterpretacionCategoria::create([
            'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 40, 'valor_max' => 47, 'etiqueta' => 'Nivel regular',
            'recomendacion' => 'Postergás tareas con cierta frecuencia. Probá dividir los trabajos grandes en partes más chicas con plazos propios.',
        ]);
        InterpretacionCategoria::create([
            'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 48, 'valor_max' => 53, 'etiqueta' => 'Alta procrastinación',
            'recomendacion' => 'Sueles dejar las tareas para último momento con frecuencia. Armar un cronograma semanal y pedir ayuda a un docente o tutor puede servirte.',
        ]);
        InterpretacionCategoria::create([
            'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 54, 'valor_max' => 80, 'etiqueta' => 'Muy alta procrastinación',
            'recomendacion' => 'Postergás tus tareas con mucha frecuencia y eso probablemente te esté generando estrés. Te recomendamos hablar con un orientador o psicólogo escolar para trabajar en tus hábitos de estudio.',
        ]);

        $textos = [
            1 => 'Cuando tengo que hacer una tarea, normalmente la dejo para el último minuto.',
            2 => 'Generalmente me preparo por adelantado para los exámenes.',
            3 => 'Cuando me asignan lecturas, las leo la noche anterior.',
            4 => 'Cuando me asignan lecturas, las reviso el mismo día de clase.',
            5 => 'Cuando tengo problemas para entender algo, inmediatamente trato de buscar ayuda.',
            6 => 'Asisto regularmente a clases.',
            7 => 'Trato de completar el trabajo asignado lo más pronto posible.',
            8 => 'Postergo los trabajos de los cursos que no me gustan.',
            9 => 'Postergo las lecturas de los cursos que no me gustan.',
            10 => 'Constantemente intento mejorar mis hábitos de estudio.',
            11 => 'Invierto el tiempo necesario en estudiar aun cuando el tema sea aburrido.',
            12 => 'Trato de motivarme para mantener mi ritmo de estudio.',
            13 => 'Trato de terminar mis trabajos importantes con tiempo de sobra.',
            14 => 'Me tomo el tiempo de revisar mis tareas antes de entregarlas.',
            15 => 'Raramente dejo para mañana lo que puedo hacer hoy.',
            16 => 'Disfruto la mezcla de desafío y emoción de esperar hasta el último minuto para completar una tarea.',
        ];

        $etiquetasEscala = [5 => 'Siempre', 4 => 'Casi siempre', 3 => 'A veces', 2 => 'Casi nunca', 1 => 'Nunca'];

        foreach ($textos as $numero => $texto) {
            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => $categoria->id,
                'texto' => $texto,
                'tipo' => 'escala',
                'orden' => $numero,
            ]);

            foreach ($etiquetasEscala as $valor => $etiqueta) {
                OpcionRespuesta::create([
                    'pregunta_id' => $pregunta->id,
                    'texto' => $etiqueta,
                    'peso' => $valor,
                    'orden' => 6 - $valor,
                ]);
            }
        }
    }

    private function crearChaside(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => 'Test de Orientación Vocacional (CHASIDE)',
            'instrucciones' => 'Respondé Sí o No pensando en el tipo de profesión o la actitud que implica cada pregunta. No omitas ninguna.',
            'estado' => 'publicada',
        ]);

        $areasInfo = [
            'C' => [
                'nombre' => 'Área Administrativa',
                'rasgos' => 'organización, supervisión, orden, análisis y síntesis, cálculo y persuasión',
                'items' => [1, 2, 12, 15, 20, 46, 51, 53, 64, 71, 78, 85, 91, 98],
            ],
            'H' => [
                'nombre' => 'Área de Humanidades y Ciencias Sociales y Jurídicas',
                'rasgos' => 'precisión verbal, organización, relación de hechos, orden y sentido de la justicia',
                'items' => [9, 25, 30, 34, 41, 56, 63, 67, 72, 74, 80, 86, 89, 95],
            ],
            'A' => [
                'nombre' => 'Área Artística',
                'rasgos' => 'sensibilidad estética, creatividad, imaginación y percepción visual o auditiva',
                'items' => [3, 11, 21, 22, 28, 36, 39, 45, 50, 57, 76, 81, 82, 96],
            ],
            'S' => [
                'nombre' => 'Área de Ciencias de la Salud',
                'rasgos' => 'vocación de ayuda, paciencia, análisis y trato con personas',
                'items' => [4, 8, 16, 23, 29, 33, 40, 44, 52, 62, 69, 70, 87, 92],
            ],
            'I' => [
                'nombre' => 'Área de Enseñanzas Técnicas',
                'rasgos' => 'cálculo, exactitud, planificación y gusto por lo práctico',
                'items' => [6, 10, 19, 26, 27, 38, 47, 54, 59, 60, 75, 83, 90, 97],
            ],
            'D' => [
                'nombre' => 'Área de Defensa y Seguridad',
                'rasgos' => 'sentido de la justicia, espíritu de equipo, liderazgo y valentía',
                'items' => [5, 13, 14, 18, 24, 31, 37, 43, 48, 58, 65, 66, 73, 84],
            ],
            'E' => [
                'nombre' => 'Área de Ciencias Experimentales',
                'rasgos' => 'investigación, orden, análisis y observación metódica',
                'items' => [7, 17, 32, 35, 42, 49, 55, 61, 68, 77, 79, 88, 93, 94],
            ],
        ];

        $categorias = [];
        $categoriaPorNumero = [];
        $orden = 0;
        foreach ($areasInfo as $letra => $info) {
            $orden++;
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => "{$letra} - {$info['nombre']}",
                'tipo_puntuacion' => 'CONTEO',
                'orden' => $orden,
            ]);

            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 6, 'etiqueta' => 'Bajo interés',
                'recomendacion' => "No parece ser una de tus áreas más marcadas por ahora, aunque si te interesa igual podés explorar carreras relacionadas con el {$info['nombre']}.",
            ]);
            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 7, 'valor_max' => 14, 'etiqueta' => 'Área de interés',
                'recomendacion' => "Mostrás interés marcado por el {$info['nombre']}, con rasgos como {$info['rasgos']}. Vale la pena que explores carreras afines.",
            ]);

            $categorias[$letra] = $categoria;
            foreach ($info['items'] as $numero) {
                $categoriaPorNumero[$numero] = $letra;
            }
        }

        $textos = [
            1 => '¿Aceptarías trabajar escribiendo artículos en la sección económica de un diario?',
            2 => '¿Te ofrecerías para organizar la despedida de soltero/a de un amigo/a?',
            3 => '¿Te gustaría dirigir o crear un proyecto de urbanización en tu provincia?',
            4 => '¿A una frustración siempre le oponés un pensamiento positivo?',
            5 => '¿Te dedicarías a socorrer a personas accidentadas o atacadas por asaltantes?',
            6 => '¿Cuando eras chico/a, te interesaba saber cómo estaban construidos tus juguetes?',
            7 => '¿Te interesan más los misterios de la naturaleza que los secretos de la tecnología?',
            8 => '¿Escuchás atentamente los problemas que te plantean tus amigos?',
            9 => '¿Te ofrecerías para explicarles a tus compañeros un tema que no entendieron?',
            10 => '¿Sos exigente y crítico/a con tu equipo de trabajo?',
            11 => '¿Te atrae armar rompecabezas o puzzles?',
            12 => '¿Te gustaría conocer la diferencia entre macroeconomía y microeconomía?',
            13 => '¿Usar uniforme te hace sentir distinto/a, importante?',
            14 => '¿Participarías como profesional en un espectáculo de acrobacia aérea?',
            15 => '¿Organizás tu dinero de manera que te alcance hasta el próximo cobro?',
            16 => '¿Convencés fácilmente a otras personas sobre la validez de tus argumentos?',
            17 => '¿Te gustaría estar informado sobre los nuevos descubrimientos sobre el origen del universo?',
            18 => '¿Ante una situación de emergencia, actuás rápidamente?',
            19 => '¿Cuando tenés que resolver un problema matemático, perseverás hasta encontrar la solución?',
            20 => '¿Si te convocara tu club preferido para planificar, organizar y dirigir un campo de deportes, aceptarías?',
            21 => '¿Sos quien pone un toque de alegría en las fiestas?',
            22 => '¿Creés que los detalles son tan importantes como el todo?',
            23 => '¿Te sentirías a gusto trabajando en un ámbito hospitalario?',
            24 => '¿Te gustaría participar para mantener el orden ante grandes desórdenes y catástrofes?',
            25 => '¿Pasarías varias horas leyendo algún libro de tu interés?',
            26 => '¿Planificás detalladamente tus trabajos antes de empezar?',
            27 => '¿Entablás una relación casi personal con tu computadora?',
            28 => '¿Disfrutás modelando con arcilla?',
            29 => '¿Ayudás habitualmente a quien lo necesite, como a una persona no vidente a cruzar la calle?',
            30 => '¿Considerás importante que desde la secundaria se fomente la actitud crítica y la participación activa?',
            31 => '¿Aceptarías que las mujeres formen parte de las fuerzas armadas bajo las mismas normas que los hombres?',
            32 => '¿Te gustaría crear nuevas técnicas para descubrir patologías a través del microscopio?',
            33 => '¿Participarías en una campaña de prevención contra alguna enfermedad?',
            34 => '¿Te interesan los temas relacionados al pasado y a la evolución del hombre?',
            35 => '¿Te incluirías en un proyecto de investigación sobre movimientos sísmicos?',
            36 => '¿Fuera del horario escolar, dedicás algún día de la semana a actividades corporales?',
            37 => '¿Te interesan las actividades de mucha acción y reacción rápida ante situaciones imprevistas?',
            38 => '¿Te ofrecerías como voluntario/a para colaborar en proyectos espaciales?',
            39 => '¿Te gusta más el trabajo manual que el trabajo intelectual?',
            40 => '¿Estarías dispuesto/a a renunciar a un momento placentero para ofrecer tu ayuda como profesional?',
            41 => '¿Participarías de una investigación sobre la violencia en el deporte?',
            42 => '¿Te gustaría trabajar en un laboratorio mientras estudiás?',
            43 => '¿Arriesgarías tu vida para salvar la vida de alguien que no conocés?',
            44 => '¿Te agradaría hacer un curso de primeros auxilios?',
            45 => '¿Tolerarías empezar algo tantas veces como fuera necesario hasta lograrlo?',
            46 => '¿Distribuís tus horarios del día adecuadamente para hacer todo lo planeado?',
            47 => '¿Harías un curso para aprender a fabricar instrumentos o piezas de máquinas?',
            48 => '¿Elegirías una profesión que implique estar meses alejado/a de tu familia, como la de marino/a?',
            49 => '¿Te radicarías en una zona agrícola-ganadera para desarrollar tu profesión?',
            50 => '¿Cuando trabajás en grupo, te entusiasma proponer ideas originales y que sean tenidas en cuenta?',
            51 => '¿Te resulta fácil coordinar un grupo de trabajo?',
            52 => '¿Te resultó interesante el estudio de las ciencias biológicas?',
            53 => '¿Si una empresa buscara un gerente de comercialización, te sentirías a gusto en ese rol?',
            54 => '¿Te incluirías en un proyecto de desarrollo de la principal fuente de recursos de tu provincia?',
            55 => '¿Te interesa saber las causas de ciertos fenómenos, aunque no altere tu vida?',
            56 => '¿Descubriste algún filósofo o escritor que haya expresado tus mismas ideas?',
            57 => '¿Desearías que te regalen un instrumento musical para tu cumpleaños?',
            58 => '¿Aceptarías colaborar con el cumplimiento de las normas en lugares públicos?',
            59 => '¿Creés que tus ideas son importantes y hacés lo posible por ponerlas en práctica?',
            60 => '¿Cuando se descompone un artefacto en tu casa, te disponés a repararlo?',
            61 => '¿Formarías parte de un equipo orientado a preservar flora y fauna en extinción?',
            62 => '¿Leerías revistas sobre los últimos avances científicos y tecnológicos en salud?',
            63 => '¿Te parece importante preservar las raíces culturales de tu país?',
            64 => '¿Te gustaría investigar algo que ayude a una distribución más justa de la riqueza?',
            65 => '¿Te gustaría hacer tareas auxiliares en una nave, como pintura o conservación del casco?',
            66 => '¿Creés que un país debe tener la más alta tecnología armamentista a cualquier precio?',
            67 => '¿La libertad y la justicia son valores fundamentales en tu vida?',
            68 => '¿Aceptarías una práctica en control de calidad de una industria de alimentos?',
            69 => '¿Considerás que la salud pública debe ser prioritaria, gratuita y eficiente para todos?',
            70 => '¿Te interesaría investigar sobre alguna nueva vacuna?',
            71 => '¿En un equipo de trabajo, preferís el rol de coordinador/a?',
            72 => '¿En una discusión entre amigos, te ofrecés como mediador/a?',
            73 => '¿Estás de acuerdo con la formación de un cuerpo de soldados profesionales?',
            74 => '¿Lucharías por una causa justa hasta las últimas consecuencias?',
            75 => '¿Te gustaría investigar científicamente sobre cultivos agrícolas?',
            76 => '¿Harías el nuevo diseño de una prenda pasada de moda para presentarlo en una reunión?',
            77 => '¿Visitarías un observatorio astronómico para ver en acción el funcionamiento de los aparatos?',
            78 => '¿Dirigirías el área de importación y exportación de una empresa?',
            79 => '¿Te cohíbes al entrar a un lugar nuevo con gente desconocida?',
            80 => '¿Te gratificaría trabajar con niños?',
            81 => '¿Harías el diseño de un cartel para una campaña de prevención de salud?',
            82 => '¿Dirigirías un grupo de teatro independiente?',
            83 => '¿Enviarías tu currículum a una empresa que busca gerente de producción?',
            84 => '¿Participarías en un grupo de defensa internacional dentro de alguna fuerza armada?',
            85 => '¿Te costearías los estudios trabajando en una auditoría (revisión de cuentas)?',
            86 => '¿Sos de los que defienden causas perdidas?',
            87 => '¿Ante una emergencia epidémica, participarías en una campaña brindando ayuda?',
            88 => '¿Sabrías responder qué significa ADN o ARN?',
            89 => '¿Elegirías una carrera cuya herramienta de trabajo fuera un idioma extranjero?',
            90 => '¿Trabajar con objetos o máquinas te resulta más gratificante que trabajar con personas?',
            91 => '¿Te resultaría gratificante ser asesor/a contable en una empresa reconocida?',
            92 => '¿Ante un llamado solidario, te ofrecerías para cuidar a una persona enferma?',
            93 => '¿Te atrae investigar sobre los misterios del universo, como los agujeros negros?',
            94 => '¿El trabajo individual te resulta más rápido y efectivo que el trabajo grupal?',
            95 => '¿Dedicarías parte de tu tiempo a ayudar a personas con carencias o necesidades?',
            96 => '¿Cuando elegís tu ropa o decorás un ambiente, tenés en cuenta la combinación de colores y estilos?',
            97 => '¿Te gustaría trabajar dirigiendo la construcción de una empresa hidroeléctrica?',
            98 => '¿Sabés qué es el PIB? Es un concepto económico. ¿Te gusta este tipo de tema?',
        ];

        foreach ($textos as $numero => $texto) {
            $letra = $categoriaPorNumero[$numero];
            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => $categorias[$letra]->id,
                'texto' => $texto,
                'tipo' => 'verdadero_falso',
                'orden' => $numero,
            ]);

            OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'Sí', 'peso' => 1, 'orden' => 1]);
            OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'No', 'peso' => 0, 'orden' => 2]);
        }
    }

    private function crearEstilosAprendizaje(User $evaluador): void
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => 'Test de Estilo de Aprendizaje (Modelo PNL)',
            'instrucciones' => 'Elegí, de cada pregunta, la opción con la que más te identifiques.',
            'estado' => 'publicada',
        ]);

        $canalesInfo = [
            'visual' => [
                'nombre' => 'Visual',
                'recomendacion' => 'Aprendés mejor viendo: usá esquemas, colores, subrayados y videos para estudiar.',
            ],
            'auditivo' => [
                'nombre' => 'Auditivo',
                'recomendacion' => 'Aprendés mejor escuchando: leer en voz alta, grabarte explicando el tema o escuchar clases grabadas puede ayudarte.',
            ],
            'cinestesico' => [
                'nombre' => 'Cinestésico',
                'recomendacion' => 'Aprendés mejor haciendo: practicar con ejercicios, maquetas o moverte mientras estudiás puede ayudarte a retener mejor.',
            ],
        ];

        $categorias = [];
        $orden = 0;
        foreach ($canalesInfo as $clave => $info) {
            $orden++;
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => $info['nombre'],
                'tipo_puntuacion' => 'CONTEO',
                'orden' => $orden,
            ]);

            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 0, 'valor_max' => 13, 'etiqueta' => 'No predominante',
                'recomendacion' => "El canal {$info['nombre']} no es tu preferido para aprender, pero igual puede servirte combinarlo con otras técnicas de estudio.",
            ]);
            InterpretacionCategoria::create([
                'categoria_evaluacion_id' => $categoria->id, 'valor_min' => 14, 'valor_max' => 40, 'etiqueta' => 'Predominante',
                'recomendacion' => $info['recomendacion'],
            ]);

            $categorias[$clave] = $categoria;
        }

        // Cada fila: [texto de la pregunta, [texto opción a, texto opción b, texto opción c], [canal de a, canal de b, canal de c]]
        $preguntas = [
            ['¿Cuál de las siguientes actividades disfrutás más?', ['Escuchar música', 'Ver películas', 'Bailar con buena música'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Qué programa de televisión preferís?', ['Reportajes de descubrimientos y lugares', 'Cómico y de entretenimiento', 'Noticias del mundo'], ['visual', 'cinestesico', 'auditivo']],
            ['Cuando conversás con otra persona, vos:', ['La escuchás atentamente', 'La observás', 'Tendés a tocarla'], ['auditivo', 'visual', 'cinestesico']],
            ['Si pudieras adquirir uno de los siguientes artículos, ¿cuál elegirías?', ['Un jacuzzi', 'Un estéreo', 'Un televisor'], ['cinestesico', 'auditivo', 'visual']],
            ['¿Qué preferís hacer un sábado por la tarde?', ['Quedarte en casa', 'Ir a un concierto', 'Ir al cine'], ['cinestesico', 'auditivo', 'visual']],
            ['¿Qué tipo de exámenes se te facilitan más?', ['Examen oral', 'Examen escrito', 'Examen de opción múltiple'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Cómo te orientás más fácilmente?', ['Mediante el uso de un mapa', 'Pidiendo indicaciones', 'A través de la intuición'], ['visual', 'auditivo', 'cinestesico']],
            ['¿En qué preferís ocupar tu tiempo en un lugar de descanso?', ['Pensar', 'Caminar por los alrededores', 'Descansar'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Qué te halaga más?', ['Que te digan que tenés buen aspecto', 'Que te digan que tenés un trato agradable', 'Que te digan que tenés una conversación interesante'], ['visual', 'cinestesico', 'auditivo']],
            ['¿Cuál de estos ambientes te atrae más?', ['Uno con un clima agradable', 'Uno donde se escuchen las olas del mar', 'Uno con una hermosa vista al océano'], ['cinestesico', 'auditivo', 'visual']],
            ['¿De qué manera se te facilita aprender algo?', ['Repitiendo en voz alta', 'Escribiéndolo varias veces', 'Relacionándolo con algo divertido'], ['auditivo', 'visual', 'cinestesico']],
            ['¿A qué evento preferirías asistir?', ['A una reunión social', 'A una exposición de arte', 'A una conferencia'], ['cinestesico', 'visual', 'auditivo']],
            ['¿De qué manera te formás una opinión de otras personas?', ['Por la sinceridad en su voz', 'Por la forma de estrecharte la mano', 'Por su aspecto'], ['auditivo', 'cinestesico', 'visual']],
            ['¿Cómo te considerás?', ['Atlético/a', 'Intelectual', 'Sociable'], ['visual', 'auditivo', 'cinestesico']],
            ['¿Qué tipo de películas te gustan más?', ['Clásicas', 'De acción', 'De amor'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Cómo preferís mantenerte en contacto con otra persona?', ['Por correo electrónico', 'Tomando un café juntos', 'Por teléfono'], ['visual', 'cinestesico', 'auditivo']],
            ['¿Cuál de las siguientes frases se identifica más con vos?', ['Me gusta que mi auto se sienta bien al conducirlo', 'Percibo hasta el más ligero ruido que hace mi auto', 'Es importante que mi auto esté limpio por dentro y por fuera'], ['cinestesico', 'auditivo', 'visual']],
            ['¿Cómo preferís pasar el tiempo con tu pareja?', ['Conversando', 'Acariciándose', 'Mirando algo juntos'], ['auditivo', 'cinestesico', 'visual']],
            ['Si no encontrás las llaves en una bolsa:', ['La buscás mirando', 'Sacudís la bolsa para oír el ruido', 'Buscás al tacto'], ['visual', 'auditivo', 'cinestesico']],
            ['Cuando tratás de recordar algo, ¿cómo lo hacés?', ['A través de imágenes', 'A través de emociones', 'A través de sonidos'], ['visual', 'cinestesico', 'auditivo']],
            ['Si tuvieras dinero, ¿qué harías?', ['Comprar una casa', 'Viajar y conocer el mundo', 'Adquirir un estudio de grabación'], ['cinestesico', 'visual', 'auditivo']],
            ['¿Con qué frase te identificás más?', ['Reconozco a las personas por su voz', 'No recuerdo el aspecto de la gente', 'Recuerdo el aspecto de alguien, pero no su nombre'], ['auditivo', 'cinestesico', 'visual']],
            ['Si tuvieras que quedarte en una isla desierta, ¿qué preferirías llevar?', ['Algunos buenos libros', 'Un radio portátil', 'Golosinas y comida enlatada'], ['visual', 'auditivo', 'cinestesico']],
            ['¿Cuál de los siguientes entretenimientos preferís?', ['Tocar un instrumento musical', 'Sacar fotografías', 'Actividades manuales'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Cómo es tu forma de vestir?', ['Impecable', 'Informal', 'Muy informal'], ['visual', 'auditivo', 'cinestesico']],
            ['¿Qué es lo que más te gusta de una fogata nocturna?', ['El calor del fuego y los bombones asados', 'El sonido del fuego quemando la leña', 'Mirar el fuego y las estrellas'], ['cinestesico', 'auditivo', 'visual']],
            ['¿Cómo se te facilita entender algo?', ['Cuando te lo explican verbalmente', 'Cuando usan medios visuales', 'Cuando se hace a través de una actividad'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Por qué te distinguís?', ['Por tener gran intuición', 'Por ser buen/a conversador/a', 'Por ser buen/a observador/a'], ['cinestesico', 'auditivo', 'visual']],
            ['¿Qué es lo que más disfrutás de un amanecer?', ['La emoción de vivir un nuevo día', 'Las tonalidades del cielo', 'El canto de las aves'], ['cinestesico', 'visual', 'auditivo']],
            ['Si pudieras elegir, ¿qué preferirías ser?', ['Un gran médico', 'Un gran músico', 'Un gran pintor'], ['cinestesico', 'auditivo', 'visual']],
            ['Cuando elegís tu ropa, ¿qué es lo más importante para vos?', ['Que sea adecuada', 'Que luzca bien', 'Que sea cómoda'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Qué es lo que más disfrutás de una habitación?', ['Que sea silenciosa', 'Que sea confortable', 'Que esté limpia y ordenada'], ['auditivo', 'cinestesico', 'visual']],
            ['¿Qué es más sexy para vos?', ['Una iluminación tenue', 'El perfume', 'Cierto tipo de música'], ['visual', 'cinestesico', 'auditivo']],
            ['¿A qué tipo de espectáculo preferirías asistir?', ['A un concierto de música', 'A un espectáculo de magia', 'A una muestra gastronómica'], ['auditivo', 'visual', 'cinestesico']],
            ['¿Qué te atrae más de una persona?', ['Su trato y forma de ser', 'Su aspecto físico', 'Su conversación'], ['cinestesico', 'visual', 'auditivo']],
            ['Cuando vas de compras, ¿en dónde pasás mucho tiempo?', ['En una librería', 'En una perfumería', 'En una tienda de discos'], ['visual', 'cinestesico', 'auditivo']],
            ['¿Cuál es tu idea de una noche romántica?', ['A la luz de las velas', 'Con música romántica', 'Bailando tranquilamente'], ['visual', 'auditivo', 'cinestesico']],
            ['¿Qué es lo que más disfrutás de viajar?', ['Conocer personas y hacer nuevos amigos', 'Conocer lugares nuevos', 'Aprender sobre otras costumbres'], ['cinestesico', 'visual', 'auditivo']],
            ['Cuando estás en la ciudad, ¿qué es lo que más extrañás del campo?', ['El aire limpio y refrescante', 'Los paisajes', 'La tranquilidad'], ['cinestesico', 'visual', 'auditivo']],
            ['Si te ofrecieran uno de estos empleos, ¿cuál elegirías?', ['Director de una estación de radio', 'Director de un club deportivo', 'Director de una revista'], ['auditivo', 'cinestesico', 'visual']],
        ];

        foreach ($preguntas as $numero0 => [$texto, $opciones, $canales]) {
            $numero = $numero0 + 1;
            $pregunta = Pregunta::create([
                'prueba_id' => $prueba->id,
                'categoria_evaluacion_id' => null,
                'texto' => $texto,
                'tipo' => 'opcion_multiple',
                'orden' => $numero,
            ]);

            foreach ($opciones as $i => $textoOpcion) {
                OpcionRespuesta::create([
                    'pregunta_id' => $pregunta->id,
                    'categoria_evaluacion_id' => $categorias[$canales[$i]]->id,
                    'texto' => $textoOpcion,
                    'peso' => 1,
                    'orden' => $i + 1,
                ]);
            }
        }
    }
}
