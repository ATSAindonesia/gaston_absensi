<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sport_id' => Sport::factory(),
            'name' => 'Lapangan '.fake()->unique()->randomLetter().fake()->randomNumber(2),
        ];
    }
}
