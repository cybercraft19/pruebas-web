<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['creado_por', 'titulo', 'instrucciones', 'tiempo_max_minutos', 'estado', 'pdf_referencia_path'])]
class Prueba extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * @return HasMany<CategoriaEvaluacion, $this>
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(CategoriaEvaluacion::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Pregunta, $this>
     */
    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Intento, $this>
     */
    public function intentos(): HasMany
    {
        return $this->hasMany(Intento::class);
    }
}
