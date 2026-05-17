<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceAssignment>
 */
class DeviceAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_id'   => Device::factory(),
            'user_id'     => User::factory(),
            'assigned_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'returned_at' => null,
            'notes'       => fake()->optional(0.4)->sentence(),
        ];
    }

    public function returned(): static
    {
        return $this->state([
            'returned_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
