<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'creado_por', 'cedula', 'telefono', 'fecha_nacimiento', 'acudiente_nombre', 'acudiente_telefono', 'consentimiento_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'consentimiento_at' => 'datetime',
        ];
    }

    public function isEvaluador(): bool
    {
        return $this->role === 'evaluador';
    }

    public function isEstudiante(): bool
    {
        return $this->role === 'estudiante';
    }

    /**
     * @return HasMany<Prueba, $this>
     */
    public function pruebasCreadas(): HasMany
    {
        return $this->hasMany(Prueba::class, 'creado_por');
    }

    /**
     * @return HasMany<Intento, $this>
     */
    public function intentos(): HasMany
    {
        return $this->hasMany(Intento::class, 'estudiante_id');
    }

    /**
     * El evaluador que dio de alta a este estudiante.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function estudiantesCreados(): HasMany
    {
        return $this->hasMany(User::class, 'creado_por');
    }
}
