<?php

namespace App\Models;

use Database\Factories\CourtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sport_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Court extends Model
{
    /** @use HasFactory<CourtFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sport_id',
        'name',
    ];

    /**
     * Get the sport of this court.
     *
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the sessions using this court.
     *
     * @return HasMany<AttendanceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
