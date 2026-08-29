<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('resultados_informe')]
#[Fillable(['intento_id', 'categoria_evaluacion_id', 'puntaje', 'etiqueta_interpretacion', 'recomendacion'])]
class ResultadoInforme extends Model
{
    protected function casts(): array
    {
        return [
            'puntaje' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Intento, $this>
     */
    public function intento(): BelongsTo
    {
        return $this->belongsTo(Intento::class);
    }

    /**
     * @return BelongsTo<CategoriaEvaluacion, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvaluacion::class, 'categoria_evaluacion_id');
    }
}
