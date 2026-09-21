<?php

namespace Database\Seeders;

use App\Models\CategoriaEvaluacion;
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

        $this->crearHema($evaluador);
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
            'instrucciones' => 'Toca los números en orden ascendente, de menor a mayor, empezando por el 00. Tienes un minuto por cada rejilla: señala todos los que puedas.',
            'estado' => 'publicada',
        ]);

        app(RejillaLayoutService::class)->generar($prueba);
    }

    private function crearHema(User $evaluador): void
    {
        if (Prueba::where('creado_por', $evaluador->id)->where('titulo', self::TITULO_HEMA)->exists()) {
            return;
        }

        $prueba = Prueba::create([
            'creado_por' => $evaluador->id,
            'tipo' => 'cuestionario',
            'titulo' => self::TITULO_HEMA,
            'instrucciones' => 'Responde Sí o No a cada pregunta. Al final se cuenta cuántas respuestas afirmativas tuviste en cada sección (el máximo es de 10 puntos por sección, y 9 en Salud física y emocional).',
            'estado' => 'publicada',
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
