<?php

namespace App\Actions\Sport;

use App\Exceptions\DomainException;
use App\Models\Court;
use App\Models\Sport;

class DeleteSport
{
    /**
     * Delete a sport. Blocked while any session uses it or its courts.
     */
    public function handle(Sport $sport): void
    {
        if ($sport->sessions()->exists() || Court::where('sport_id', $sport->getKey())->whereHas('sessions')->exists()) {
            throw new DomainException(__('Cabang olahraga tidak dapat dihapus karena masih dipakai sesi.'));
        }

        $sport->delete();
    }
}
