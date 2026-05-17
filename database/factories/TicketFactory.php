<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'device_id'   => null,
            'title'       => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status'      => TicketStatus::OPEN->value,
            'priority'    => fake()->randomElement(TicketPriority::cases())->value,
            'category'    => fake()->randomElement(['device_assignment', 'incident', 'control']),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TicketStatus::IN_PROGRESS->value]);
    }

    public function closed(): static
    {
        return $this->state(['status' => TicketStatus::CLOSED->value]);
    }
}
