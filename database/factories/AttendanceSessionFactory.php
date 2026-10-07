<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 days');
        $sport = Sport::factory()->create();
        $court = Court::factory()->for($sport)->create();

        return [
            'name' => fake()->words(3, true),
            'created_by' => User::factory(),
            'sport_id' => $sport->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => SessionStatus::Soon,
            'cancel_reason' => null,
            'started_at' => null,
            'finished_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate that the session is currently active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Active,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the session was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the session was finished.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Finished,
            'finished_at' => now(),
        ]);
    }
}
