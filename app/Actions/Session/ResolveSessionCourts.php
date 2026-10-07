<?php

namespace App\Actions\Session;

use App\Models\Court;
use Illuminate\Database\Eloquent\Collection;

class ResolveSessionCourts
{
    /**
     * Get courts of the selected sport for the session form.
     *
     * @return Collection<int, Court>
     */
    public function handle(?int $sportId): Collection
    {
        if ($sportId === null) {
            return new Collection;
        }

        return Court::where('sport_id', $sportId)->orderBy('name')->get();
    }
}
