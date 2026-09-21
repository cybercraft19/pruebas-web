<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['intento_id', 'variante', 'aciertos', 'errores'])]
class RejillaResultado extends Model
{
    public const ACIERTOS_BUEN_NIVEL = 20;

    protected $appends = ['nivel'];

    /**
     * @return Attribute<string, never>
     */
    protected function nivel(): Attribute
    {
        return Attribute::get(fn () => $this->aciertos >= self::ACIERTOS_BUEN_NIVEL
            ? 'Buen nivel de concentración'
            : 'Necesita entrenar la atención');
    }

    /**
     * @return BelongsTo<Intento, $this>
     */
    public function intento(): BelongsTo
    {
        return $this->belongsTo(Intento::class);
    }
}
