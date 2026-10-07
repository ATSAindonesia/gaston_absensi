<?php

namespace App\Actions\Sport;

use App\Exceptions\DomainException;
use App\Models\Court;

class DeleteCourt
{
    /**
     * Delete a court. Blocked while any session uses it.
     */
    public function handle(Court $court): void
    {
        if ($court->sessions()->exists()) {
            throw new DomainException(__('Lapangan tidak dapat dihapus karena masih dipakai sesi.'));
        }

        $court->delete();
    }
}
