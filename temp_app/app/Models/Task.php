<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One card on somebody's daily board.
 */
class Task extends Model
{

    protected $fillable = ['user_id', 'for_team', 'crew_role', 'title', 'details', 'task_date', 'status', 'event_id', 'completed_at', 'claimed_at', 'created_by', 'archived_at'];

    public const STATUSES = ['todo' => 'To do', 'doing' => 'In progress', 'done' => 'Done'];

    /** Which multimedia role a generated production task belongs to. */
    public const CREW_ROLES = ['shooter' => 'Shooter task', 'photo' => 'Photo task', 'video' => 'Video task'];

    public function crewRoleLabel(): string
    {
        return self::CREW_ROLES[$this->crew_role] ?? 'Task';
    }

    protected function casts(): array
    {
        return ['task_date' => 'date', 'completed_at' => 'datetime', 'claimed_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    /**
     * Archived tasks are out of sight everywhere.
     *
     * Boards, queues, counts, and notifications read tasks from a dozen places,
     * and archiving an event has to take its work off all of them. Filtering at
     * each call site would eventually miss one, so it is filtered here; the few
     * places that need archived rows ask for them with
     * `withoutGlobalScope('not_archived')`.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('not_archived', fn (Builder $query) => $query->whereNull('archived_at'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Raised for a team and not yet taken by anyone on it. Both halves matter:
     * an ordinary personal card has no team, and a claimed one has an owner.
     */
    public function isUnclaimed(): bool
    {
        return $this->for_team !== null && $this->user_id === null;
    }

    /** Work waiting for a team to pick up. */
    public function scopeUnclaimedFor($query, string $team)
    {
        return $query->where('for_team', $team)->whereNull('user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
