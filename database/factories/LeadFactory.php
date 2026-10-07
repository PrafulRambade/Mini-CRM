<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+91 98### #####'),
            'company' => fake()->optional(0.8)->company(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => fake()->randomElement([LeadStatus::New, LeadStatus::InProgress, LeadStatus::Lost]),
            'assigned_to' => User::factory(),
            'follow_up_date' => fake()->optional(0.7)->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(12),
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->id]);
    }
}
