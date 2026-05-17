<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario de prueba con credenciales conocidas
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Usuarios adicionales para los seeders de tickets
        User::factory(4)->create();

        $this->call([
            DeviceSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
