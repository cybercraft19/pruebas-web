<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prueba_id', 'parte', 'practica', 'orden', 'etiqueta', 'pos_x', 'pos_y'])]
class TmtNodo extends Model
{
    protected function casts(): array
    {
        return [
            'practica' => 'boolean',
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Prueba, $this>
     */
    public function prueba(): BelongsTo
    {
        return $this->belongsTo(Prueba::class);
    }
}
