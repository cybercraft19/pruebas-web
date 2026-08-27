<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('interpretaciones_categoria')]
#[Fillable(['categoria_evaluacion_id', 'valor_min', 'valor_max', 'etiqueta', 'recomendacion'])]
class InterpretacionCategoria extends Model
{
    /**
     * @return BelongsTo<CategoriaEvaluacion, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvaluacion::class, 'categoria_evaluacion_id');
    }
}
