<?php

namespace App\Actions\Sport;

use App\Models\Court;

class AddCourt
{
    /**
     * Add a court to a sport.
     */
    public function handle(int $sportId, string $name): Court
    {
        return Court::create([
            'sport_id' => $sportId,
            'name' => $name,
        ]);
    }
}
