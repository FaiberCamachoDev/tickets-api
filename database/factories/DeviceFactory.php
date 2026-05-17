<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => fake()->words(2, true),
            'type'          => fake()->randomElement(DeviceType::cases())->value,
            'serial_number' => fake()->unique()->bothify('SN-####-???'),
            'status'        => DeviceStatus::AVAILABLE->value,
        ];
    }

    public function assigned(): static
    {
        return $this->state(['status' => DeviceStatus::ASSIGNED->value]);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => DeviceStatus::MAINTENANCE->value]);
    }
}
