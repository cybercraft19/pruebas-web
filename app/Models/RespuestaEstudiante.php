<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('respuestas_estudiante')]
#[Fillable(['intento_id', 'pregunta_id', 'opcion_id'])]
class RespuestaEstudiante extends Model
{
    /**
     * @return BelongsTo<Intento, $this>
     */
    public function intento(): BelongsTo
    {
        return $this->belongsTo(Intento::class);
    }

    /**
     * @return BelongsTo<Pregunta, $this>
     */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    /**
     * @return BelongsTo<OpcionRespuesta, $this>
     */
    public function opcion(): BelongsTo
    {
        return $this->belongsTo(OpcionRespuesta::class, 'opcion_id');
    }
}
