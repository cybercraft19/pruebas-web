<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['prueba_id', 'categoria_evaluacion_id', 'texto', 'tipo', 'orden'])]
class Pregunta extends Model
{
    /**
     * @return BelongsTo<Prueba, $this>
     */
    public function prueba(): BelongsTo
    {
        return $this->belongsTo(Prueba::class);
    }

    /**
     * @return BelongsTo<CategoriaEvaluacion, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvaluacion::class, 'categoria_evaluacion_id');
    }

    /**
     * @return HasMany<OpcionRespuesta, $this>
     */
    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionRespuesta::class)->orderBy('orden');
    }
}
