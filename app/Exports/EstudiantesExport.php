<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EstudiantesExport implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Estudiantes';
    }

    public function headings(): array
    {
        return ['Nombre', 'Correo', 'Creado'];
    }

    public function collection(): Collection
    {
        return User::query()
            ->where('role', 'estudiante')
            ->get(['name', 'email', 'created_at'])
            ->map(fn (User $estudiante) => [
                $estudiante->name,
                $estudiante->email,
                $estudiante->created_at->format('d/m/Y H:i'),
            ]);
    }
}
