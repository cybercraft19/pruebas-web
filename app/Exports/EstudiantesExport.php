<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EstudiantesExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly int $evaluadorId) {}

    public function title(): string
    {
        return 'Estudiantes';
    }

    public function headings(): array
    {
        return ['Nombre', 'Correo', 'Cédula', 'Teléfono', 'Fecha de nacimiento', 'Género', 'Grado', 'Acudiente', 'Teléfono acudiente', 'Creado'];
    }

    public function collection(): Collection
    {
        return User::query()
            ->where('role', 'estudiante')
            ->where('creado_por', $this->evaluadorId)
            ->get(['name', 'email', 'cedula', 'telefono', 'fecha_nacimiento', 'genero', 'grado', 'acudiente_nombre', 'acudiente_telefono', 'created_at'])
            ->map(fn (User $estudiante) => [
                $estudiante->name,
                $estudiante->email,
                $estudiante->cedula ?? '—',
                $estudiante->telefono ?? '—',
                $estudiante->fecha_nacimiento?->format('d/m/Y') ?? '—',
                $estudiante->genero ?? '—',
                $estudiante->grado ? "{$estudiante->grado}°" : '—',
                $estudiante->acudiente_nombre ?? '—',
                $estudiante->acudiente_telefono ?? '—',
                $estudiante->created_at->format('d/m/Y H:i'),
            ]);
    }
}
