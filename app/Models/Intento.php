<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['prueba_id', 'estudiante_id', 'estado', 'iniciado_at', 'finalizado_at'])]
class Intento extends Model
{
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Prueba, $this>
     */
    public function prueba(): BelongsTo
    {
        return $this->belongsTo(Prueba::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * @return HasMany<RespuestaEstudiante, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }

    /**
     * @return HasMany<ResultadoInforme, $this>
     */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoInforme::class);
    }

    /**
     * @return HasMany<TmtResultado, $this>
     */
    public function tmtResultados(): HasMany
    {
        return $this->hasMany(TmtResultado::class);
    }
}
