<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $devices = Device::all();

        // Tickets abiertos sin dispositivo
        Ticket::factory(10)
            ->recycle($users)
            ->create();

        // Tickets con dispositivo asociado
        Ticket::factory(8)
            ->recycle($users)
            ->create(['device_id' => fn () => $devices->random()->id]);

        // Tickets en progreso
        Ticket::factory(5)
            ->inProgress()
            ->recycle($users)
            ->create();

        // Tickets cerrados
        Ticket::factory(7)
            ->closed()
            ->recycle($users)
            ->create();
    }
}
