<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Evaluador Demo',
            'email' => 'evaluador@demo.com',
            'password' => bcrypt('password'),
            'role' => 'evaluador',
        ]);

        User::factory()->create([
            'name' => 'Estudiante Demo',
            'email' => 'estudiante@demo.com',
            'password' => bcrypt('password'),
            'role' => 'estudiante',
        ]);

        $this->call(PruebasReferenciaSeeder::class);
    }
}
