<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One card on somebody's daily board.
 */
class Task extends Model
{
    use LogsActivity;

    protected $fillable = ['user_id', 'for_team', 'title', 'details', 'task_date', 'status', 'event_id', 'completed_at', 'claimed_at', 'created_by', 'archived_at'];

    public const STATUSES = ['todo' => 'To do', 'doing' => 'In progress', 'done' => 'Done'];

    protected function casts(): array
    {
        return ['task_date' => 'date', 'completed_at' => 'datetime', 'claimed_at' => 'datetime', 'archived_at' => 'datetime'];
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
