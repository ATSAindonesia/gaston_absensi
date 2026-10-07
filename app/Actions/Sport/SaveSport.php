<?php

namespace App\Actions\Sport;

use App\Models\Sport;

class SaveSport
{
    /**
     * Create or update a sport.
     */
    public function handle(?int $id, string $name): Sport
    {
        if ($id !== null) {
            $sport = Sport::findOrFail($id);
            $sport->update(['name' => $name]);

            return $sport;
        }

        return Sport::create(['name' => $name]);
    }
}
