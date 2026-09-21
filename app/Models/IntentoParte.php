<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Momento (medido por el servidor) en que el estudiante empezó una parte cronometrada
 * (parte del TMT o variante de la rejilla), para poder validar el resultado que informa.
 */
#[Table('intento_partes')]
#[Fillable(['intento_id', 'parte', 'iniciado_at'])]
class IntentoParte extends Model
{
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Intento, $this>
     */
    public function intento(): BelongsTo
    {
        return $this->belongsTo(Intento::class);
    }
}
