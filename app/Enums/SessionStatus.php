<?php

namespace App\Enums;

/**
 * Status sesi olahraga.
 *
 * - soon: terjadwal, belum mulai (label UI: Segera)
 * - active: sedang berlangsung / start (label UI: Aktif)
 * - cancelled: dibatalkan di tengah jalan (label UI: Dibatalkan)
 * - finished: diselesaikan admin (label UI: Selesai)
 */
enum SessionStatus: string
{
    case Soon = 'soon';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Finished = 'finished';

    /**
     * Get the Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Soon => 'Segera',
            self::Active => 'Aktif',
            self::Cancelled => 'Dibatalkan',
            self::Finished => 'Selesai',
        };
    }

    /**
     * Get the Flux badge color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Soon => 'zinc',
            self::Active => 'green',
            self::Cancelled => 'red',
            self::Finished => 'blue',
        };
    }

    /**
     * Determine if the status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Finished => true,
            default => false,
        };
    }
}
