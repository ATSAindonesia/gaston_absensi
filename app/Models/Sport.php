<?php

namespace App\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Sport extends Model
{
    /** @use HasFactory<SportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the courts of this sport.
     *
     * @return HasMany<Court, $this>
     */
    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    /**
     * Get the sessions using this sport.
     *
     * @return HasMany<AttendanceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
