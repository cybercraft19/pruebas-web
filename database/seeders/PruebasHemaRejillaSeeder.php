<?php

namespace Database\Seeders;

use App\Models\CategoriaEvaluacion;
use App\Models\InterpretacionCategoria;
use App\Models\OpcionRespuesta;
use App\Models\Pregunta;
use App\Models\Prueba;
use App\Models\User;
use App\Services\RejillaLayoutService;
use Illuminate\Database\Seeder;

/**
 * Siembra el cuestionario HEMA (docs/Copia_1S03T-RA1-PDF.pdf) y el Test de la Rejilla
 * (docs/Test rejilla.docx). No duplica: si una prueba con el mismo título ya existe, la
 * salta, así se puede correr solo en producción con
 * `php artisan db:seed --class=PruebasHemaRejillaSeeder --force`.
 */
class PruebasHemaRejillaSeeder extends Seeder
{
    private const TITULO_HEMA = 'Cuestionario sobre Hábitos de Estudio y Motivación para el Aprendizaje (HEMA)';

    private const TITULO_REJILLA = 'Test de la Rejilla (Harris y Harris)';

    public function run(): void
    {
        $evaluador = User::where('role', 'evaluador')->first();

        if (! $evaluador) {
            return;
        }

        $hema = Prueba::where('creado_por', $evaluador->id)->where('titulo', self::TITULO_HEMA)->first()
            ?? $this->crearHema($evaluador);

        $this->sembrarInterpretacionesHema($hema);
        $this->crearRejilla($evaluador);
    }

    private function crearRejilla(User $evaluador): void
    {
        if (Prueba::where('creado_por', $evaluador->id)->where('titulo', self::TITULO_REJILLA)->exists()) {
            return;
        }

        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'rejilla',
            'titulo' => self::TITULO_REJILLA,
            'instrucciones' => 'Toque los números en orden ascendente, de menor a mayor, empezando por el 00. Tiene un minuto por cada rejilla: señale todos los que pueda.',
            'estado' => 'publicada',
        ]);

        app(RejillaLayoutService::class)->generar($prueba);
    }

    private function crearHema(User $evaluador): Prueba
    {
        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => self::TITULO_HEMA,
            'instrucciones' => 'Responda Sí o No a cada pregunta. Al final se cuenta cuántas respuestas afirmativas tuvo en cada sección (el máximo es de 10 puntos por sección, y 9 en Salud física y emocional).',
            'estado' => 'publicada',
            'requiere_bachillerato' => true,
        ]);

        $numero = 0;

        foreach ($this->secciones() as $orden => [$nombre, $preguntas]) {
            $categoria = CategoriaEvaluacion::create([
                'prueba_id' => $prueba->id,
                'nombre' => $nombre,
                'descripcion' => 'Puntaje máximo: '.count($preguntas).' puntos.',
                'tipo_puntuacion' => 'CONTEO',
                'orden' => $orden + 1,
            ]);

            foreach ($preguntas as $texto) {
                $pregunta = Pregunta::create([
                    'prueba_id' => $prueba->id,
                    'categoria_evaluacion_id' => $categoria->id,
                    'texto' => $texto,
                    'tipo' => 'verdadero_falso',
                    'orden' => ++$numero,
                ]);

                OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'Sí', 'peso' => 1, 'orden' => 1]);
                OpcionRespuesta::create(['pregunta_id' => $pregunta->id, 'texto' => 'No', 'peso' => 0, 'orden' => 2]);
            }
        }

        return $prueba;
    }

    /**
     * El PDF del HEMA no trae rangos de interpretación: son una propuesta del sistema, por
     * proporción del puntaje máximo de cada sección (hasta 50 % "Por mejorar", de ahí hasta
     * menos de 80 % "Aceptable", 80 % o más "Fortaleza"). Solo se cargan en las secciones que
     * todavía no tienen ninguna, así el seeder también sirve para una prueba ya existente.
     */
    private function sembrarInterpretacionesHema(Prueba $hema): void
    {
        $recomendaciones = $this->recomendacionesHema();

        foreach ($hema->categorias()->withCount('preguntas')->get() as $categoria) {
            if ($categoria->interpretaciones()->exists()) {
                continue;
            }

            $maximo = $categoria->preguntas_count;
            $topePorMejorar = (int) floor($maximo * 0.5);
            $inicioFortaleza = (int) ceil($maximo * 0.8);

            $bandas = [
                ['Por mejorar', 0, $topePorMejorar],
                ['Aceptable', $topePorMejorar + 1, $inicioFortaleza - 1],
                ['Fortaleza', $inicioFortaleza, $maximo],
            ];

            foreach ($bandas as $indice => [$etiqueta, $minimo, $tope]) {
                InterpretacionCategoria::create([
                    'categoria_evaluacion_id' => $categoria->id,
                    'valor_min' => $minimo,
                    'valor_max' => $tope,
                    'etiqueta' => $etiqueta,
                    'recomendacion' => $recomendaciones[$categoria->orden][$indice],
                ]);
            }
        }
    }

    /**
     * Por orden de sección: [Por mejorar, Aceptable, Fortaleza].
     *
     * @return array<int, array<int, string>>
     */
    private function recomendacionesHema(): array
    {
        return [
            1 => [
                'Tu lugar de estudio necesita ajustes. Busca un espacio fijo, silencioso, bien iluminado y ventilado, con una mesa amplia y una silla con respaldo. Revisa las preguntas que respondiste con No.',
                'Tu ambiente de estudio es aceptable. Mejora los detalles que respondiste con No (ruido, luz, mesa o silla) para concentrarte mejor.',
                'Tienes un ambiente de estudio muy adecuado. Mantenlo así.',
            ],
            2 => [
                'Tu descanso, tu alimentación o tu manejo emocional pueden estar afectando tu estudio. Duerme cerca de ocho horas, cambia de actividad cuando te canses y busca apoyo si la frustración o la tensión te sobrepasan.',
                'Cuidas parte de tu salud para estudiar, pero hay aspectos por reforzar. Revisa los que respondiste con No: sueño, alimentación o manejo de la frustración.',
                'Cuidas bien tu salud física y emocional, y eso favorece tu estudio.',
            ],
            3 => [
                'Te conviene mejorar tu método de estudio. Empieza con una lectura general, destaca lo principal, haz esquemas y resúmenes, y lleva tus apuntes al día.',
                'Tu método de estudio está en desarrollo. Refuerza las técnicas que respondiste con No, como los esquemas, los resúmenes o destacar lo importante.',
                'Tu método de estudio es sólido. Sigue usando esquemas, resúmenes y repasos.',
            ],
            4 => [
                'Necesitas organizar mejor tu tiempo. Define un horario de estudio habitual, reparte el trabajo a lo largo de la semana, fija prioridades y haz descansos cortos.',
                'Te organizas de manera aceptable. Revisa las respuestas con No para ajustar tu horario, tus prioridades y tus descansos.',
                'Organizas muy bien tu tiempo de estudio. Conserva tus horarios y tus descansos.',
            ],
            5 => [
                'Prepara mejor cómo enfrentas los exámenes: lee las instrucciones con calma, reparte el tiempo entre las preguntas, empieza por las más sencillas y relee antes de entregar.',
                'Tienes una buena base para los exámenes. Refuerza lo que respondiste con No, como distribuir el tiempo, hacer un esquema previo o releer.',
                'Afrontas los exámenes con buenas estrategias. Sigue cuidando la letra, la ortografía y la revisión final.',
            ],
            6 => [
                'Te falta práctica para buscar información. Aprende a usar bibliotecas, fichas y sistemas bibliográficos, y ubica fuentes confiables para tus temas de estudio.',
                'Buscas información de forma parcial. Refuerza las herramientas que respondiste con No, como fichas, bibliotecas o sistemas informatizados.',
                'Buscas información con soltura. Sigue ampliando tus fuentes.',
            ],
            7 => [
                'Conviene fortalecer cómo redactas y te expresas. Practica la redacción de trabajos, argumenta tus ideas y trabaja en equipo.',
                'Te comunicas de forma aceptable. Trabaja lo que respondiste con No, por ejemplo argumentar, discutir trabajos o usar otros idiomas.',
                'Te comunicas con claridad, por escrito y de forma oral. Sigue practicando.',
            ],
            8 => [
                'Tu motivación para aprender está baja. Conecta lo que estudias con tus metas, reflexiona sobre cómo aprendes y busca apoyo si sientes que no avanzas.',
                'Tu motivación es aceptable. Revisa las respuestas con No: reflexionar sobre cómo aprendes, buscar más información y aprovechar mejor tu tiempo.',
                'Estás muy motivado para aprender. Aprovecha esa energía para seguir explorando tus intereses.',
            ],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    private function secciones(): array
    {
        return [
            ['Factores ambientales', [
                '¿Tienes un lugar permanente de estudio?',
                '¿Puedes eliminar fácilmente los ruidos molestos?',
                '¿Es tu lugar de estudios suficientemente amplio?',
                '¿Consigues la oxigenación, temperatura y humedad adecuadas cuando estudias?',
                '¿Cuando utilizas luz artificial, se compensa la localizada y el fondo?',
                '¿Tu mesa es espaciosa?',
                '¿Puedes apoyar con facilidad los antebrazos?',
                'La superficie ¿es mate u opaca?',
                'La silla es de relativa dureza y con respaldo',
                '¿Utilizas la luz diurna permanentemente?',
            ]],
            ['Salud física y emocional', [
                '¿Cambias de actividad cuando te sientes cansado?',
                '¿Puedes dedicarte a un estudio prolongado sin sentir molestias en los ojos?',
                '¿Duermes generalmente ocho horas al día?',
                '¿Tu régimen alimenticio es variado y razonable?',
                '¿Reduces al máximo el alcohol y el tabaco?',
                '¿Tienes en cuenta la incidencia de los problemas afectivos en el rendimiento?',
                '¿Tienes interés en los estudios universitarios?',
                'Una tensión fuerte y prolongada ¿perjudica tu retención?',
                '¿Sabes salir de la frustración que te produce el no conseguir estudiar lo programado?',
            ]],
            ['Aspectos sobre el método de estudio', [
                '¿Haces una exploración general antes de concentrarte para estudiar?',
                '¿Comienzas con una lectura rápida de todo lo que tienes que estudiar cada vez?',
                '¿Puedes comprender con claridad el contenido de lo que estudias?',
                '¿Distingues los puntos principales y lo fundamental de cada tema?',
                '¿Haces esquemas clasificadores de cada unidad de contenido?',
                '¿Sintetizas o resumes en orden a facilitarte los repasos?',
                '¿Destacas de alguna manera el contenido principal en lo que estudias?',
                '¿Llevas los apuntes al día y los completas si es preciso?',
                '¿Buscas los sitios donde oyes bien y tienes buena visibilidad?',
                '¿Dispones del material necesario complementario para estudiar?',
            ]],
            ['Organización de planes y horarios', [
                '¿Acostumbras a tener un horario más o menos habitual de estudio?',
                '¿Te centras fácilmente en el estudio?',
                '¿Consigues resultados satisfactorios cuando te pones a estudiar?',
                '¿Piensas en las prioridades, en tu estudio y trabajos, en el tiempo que dedicas a estudiar?',
                '¿Distribuyes generalmente tu tiempo de estudio a lo largo de la semana?',
                '¿Te concentras con facilidad después de un corto período de adaptación?',
                'Antes de terminar tu estudio, ¿aprovechas el corto período de más rendimiento?',
                '¿Acostumbras a hacer pequeños descansos, cada vez más frecuentes, cuando aumenta el tiempo de tu dedicación?',
                '¿Te pones a estudiar con intención consciente de aprovechar el tiempo?',
                '¿Te mantienes al menos algún tiempo estudiando, aunque de momento no te concentres?',
            ]],
            ['Realización de exámenes', [
                '¿Evitas estudiar utilizando el sueño de la noche anterior a un examen?',
                '¿Lees detenidamente las instrucciones?',
                '¿Distribuyes el tiempo que tienes entre las preguntas que tienes que contestar?',
                '¿Comienzas por las cuestiones más sencillas o que ya sabes?',
                '¿Distingues con claridad la palabra o palabras que te indican lo que realmente se te pide?',
                '¿Haces el esquema preciso que facilite el desarrollo y te permita no dejarlo incompleto?',
                '¿Escribes con claridad?',
                '¿Tienes buena ortografía?',
                '¿Dejas márgenes, títulos, apartados, etc.?',
                '¿Relees el ejercicio antes de entregarlo?',
            ]],
            ['Búsqueda de información', [
                '¿Sabes rellenar fichas bibliográficas?',
                '¿Manejas los ficheros tradicionales con facilidad?',
                '¿Tienes un fichero personal ampliable?',
                '¿Acostumbras a sacar fichas de contenido, frases o referencias?',
                '¿Conoces la clasificación decimal universal aplicada a la documentación?',
                '¿Conoces las bibliotecas generales y su manejo?',
                '¿Tienes localizada alguna fuente de investigación de tu línea de estudio?',
                '¿Sabes dónde consultar revistas?',
                '¿Tienes conocimiento de las principales librerías y editoriales?',
                '¿Conoces los sistemas bibliográficos informatizados?',
            ]],
            ['Comunicación académica escrita y oral', [
                '¿Tienes claras las diferencias entre los distintos tipos de redacción científica?',
                '¿Conoces la estructura general de un trabajo científico?',
                '¿Podrías expresar con facilidad lo escrito con anterioridad?',
                '¿Sabes argumentar para defender tus aportaciones?',
                '¿Sabes criticar y discutir los trabajos de otros?',
                '¿Te sería fácil trabajar en equipo?',
                '¿Tienes acceso directo a un mecanografiado sencillo?',
                '¿Puedes utilizar mínimamente otros dos idiomas?',
                '¿Sabes establecer contacto con personas de interés para tu trabajo?',
                '¿Te expresas con claridad y precisión al comunicar algo?',
            ]],
            ['Acerca de la motivación para aprender', [
                '¿Consideras tu estudio como algo realmente personal?',
                '¿Tienes confianza en tu capacidad de aprender?',
                '¿Consideras tu tiempo de aprendizaje como digno de ser vivido con intensidad?',
                '¿Tratas de reflexionar sobre la forma en que aprendes?',
                '¿Has pensado en cómo poder rentabilizar tu tiempo de aprender?',
                '¿Las bajas puntuaciones te hacen reaccionar para estudiar más y mejor?',
                '¿Tratas de solucionar tus problemas de estudio y aprendizaje en general?',
                '¿Tratas, además de estudiar lo explicado, de tener una actitud creativa y crítica?',
                '¿Tratas de leer revistas y publicaciones en torno a los temas que te interesan en la actualidad?',
                '¿Has buscado información en otros lugares respecto a los estudios que te interesan en la actualidad?',
            ]],
        ];
    }
}
