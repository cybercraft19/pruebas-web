<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['intento_id', 'parte', 'tiempo_segundos', 'errores', 'completado'])]
class TmtResultado extends Model
{
    protected function casts(): array
    {
        return [
            'completado' => 'boolean',
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
