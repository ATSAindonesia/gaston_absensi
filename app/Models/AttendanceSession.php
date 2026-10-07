<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property int|null $created_by
 * @property int|null $sport_id
 * @property int|null $court_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property SessionStatus $status
 * @property string|null $cancel_reason
 * @property string|null $qr_secret
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AttendanceSession extends Model
{
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'created_by',
        'sport_id',
        'court_id',
        'starts_at',
        'ends_at',
        'status',
        'cancel_reason',
        'qr_secret',
        'started_at',
        'finished_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the admin who created the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the sport of this session.
     *
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the court of this session.
     *
     * @return BelongsTo<Court, $this>
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Get the participants (anggota) registered in this session.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attendance_session_user')
            ->withTimestamps();
    }

    /**
     * Get the attendance records of this session.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'attendance_session_id');
    }

    /**
     * Scope to the currently active session(s).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::Active);
    }

    /**
     * Scope to upcoming (scheduled) sessions.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::Soon);
    }

    /**
     * Determine if the session is scheduled (soon).
     */
    public function isSoon(): bool
    {
        return $this->status === SessionStatus::Soon;
    }

    /**
     * Determine if the session is currently active (start).
     */
    public function isActive(): bool
    {
        return $this->status === SessionStatus::Active;
    }

    /**
     * Determine if the session was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled;
    }

    /**
     * Determine if the session was finished.
     */
    public function isFinished(): bool
    {
        return $this->status === SessionStatus::Finished;
    }

    /**
     * Scope to sessions owned by the given user (their own room).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $query->where('created_by', $id);
    }

    /**
     * Determine if the session is owned by the given user (their own room).
     */
    public function isOwnedBy(User|int $user): bool
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $this->created_by !== null && (int) $this->created_by === (int) $id;
    }

    /**
     * Determine if this session overlaps in time with another session.
     * Boundary touch (one ends exactly when the other starts) is available.
     */
    public function overlapsWith(AttendanceSession $other): bool
    {
        return $this->starts_at < $other->ends_at && $this->ends_at > $other->starts_at;
    }

    /**
     * Get IDs of users busy in other non-terminal sessions overlapping this one.
     *
     * @return list<int>
     */
    public function busyParticipantIds(): array
    {
        return DB::table('attendance_session_user as pivot')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('attendance_sessions as blocking')
                    ->whereColumn('blocking.id', 'pivot.attendance_session_id')
                    ->where('blocking.id', '!=', $this->getKey())
                    ->whereIn('blocking.status', [SessionStatus::Soon->value, SessionStatus::Active->value])
                    ->where('blocking.starts_at', '<', $this->ends_at)
                    ->where('blocking.ends_at', '>', $this->starts_at);
            })
            ->distinct()
            ->pluck('pivot.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Determine if the session can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === SessionStatus::Soon;
    }

    /**
     * Determine if the session can be finished.
     */
    public function canBeFinished(): bool
    {
        return $this->status === SessionStatus::Active;
    }

    /**
     * Determine if the session can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [SessionStatus::Soon, SessionStatus::Active], true);
    }
}
