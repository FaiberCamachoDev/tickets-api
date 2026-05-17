<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        Device::factory(15)->create();
        Device::factory(3)->assigned()->create();
        Device::factory(2)->maintenance()->create();
    }
}
