<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('categorias_evaluacion')]
#[Fillable(['prueba_id', 'nombre', 'descripcion', 'tipo_puntuacion', 'orden'])]
class CategoriaEvaluacion extends Model
{
    /**
     * @return BelongsTo<Prueba, $this>
     */
    public function prueba(): BelongsTo
    {
        return $this->belongsTo(Prueba::class);
    }

    /**
     * @return HasMany<Pregunta, $this>
     */
    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class);
    }

    /**
     * @return HasMany<InterpretacionCategoria, $this>
     */
    public function interpretaciones(): HasMany
    {
        return $this->hasMany(InterpretacionCategoria::class)->orderBy('valor_min');
    }

    /**
     * @return HasMany<ResultadoInforme, $this>
     */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoInforme::class);
    }
}
