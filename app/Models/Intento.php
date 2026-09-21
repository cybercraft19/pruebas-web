<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['prueba_id', 'estudiante_id', 'estado', 'iniciado_at', 'finalizado_at', 'firmado_at', 'firmado_por'])]
class Intento extends Model
{
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'firmado_at' => 'datetime',
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

    /**
     * @return HasMany<IntentoParte, $this>
     */
    public function partes(): HasMany
    {
        return $this->hasMany(IntentoParte::class);
    }

    /**
     * @return HasMany<RejillaResultado, $this>
     */
    public function rejillaResultados(): HasMany
    {
        return $this->hasMany(RejillaResultado::class);
    }

    /**
     * El evaluador que firmó y publicó este resultado.
     *
     * @return BelongsTo<User, $this>
     */
    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmado_por');
    }
}
