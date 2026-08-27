<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('opciones_respuesta')]
#[Fillable(['pregunta_id', 'texto', 'peso', 'orden'])]
class OpcionRespuesta extends Model
{
    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Pregunta, $this>
     */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }
}
