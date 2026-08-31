<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('opciones_respuesta')]
#[Fillable(['pregunta_id', 'categoria_evaluacion_id', 'texto', 'peso', 'orden'])]
class OpcionRespuesta extends Model
{
    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Pregunta, $this>
     */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    /**
     * Categoría a la que suma esta opción, cuando difiere de la categoría de
     * la pregunta (preguntas "compartidas" cuya categoría depende de la
     * opción elegida, como en un test de estilos de aprendizaje VAK).
     *
     * @return BelongsTo<CategoriaEvaluacion, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvaluacion::class, 'categoria_evaluacion_id');
    }
}
