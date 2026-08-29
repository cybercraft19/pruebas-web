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
 * en lugar de datos vacíos. Ver docs/112234_100000Cuestionario_ansiedad.pdf y
 * docs/201003271337320.test-de-inteligencias-multiples.pdf.
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
}
