<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'user_id' => User::factory(),
            'scanned_at' => now(),
        ];
    }
}
