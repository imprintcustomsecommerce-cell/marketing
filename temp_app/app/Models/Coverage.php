<?php

namespace App\Models;

use App\Support\CoverageDesk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The multimedia log for one event: who shot it, who edited the photos and
 * video, and when each went out. Mirrors the coverage sheet the team keeps.
 */
class Coverage extends Model
{

    protected $fillable = ['event_id', 'stage', 'requested_at', 'requested_by', 'accepted_at', 'accepted_by', 'shooter_id', 'photo_editor_id', 'video_editor_id', 'photo_status', 'photo_posted_on', 'video_status', 'video_posted_on', 'photo_due_on', 'video_due_on', 'checklist', 'delivery_url', 'delivery_sent_at', 'delivery_sent_by', 'remarks', 'created_by'];

    public const CHECKLIST = ['brief' => 'Brief reviewed', 'shot_list' => 'Shot list prepared', 'gear' => 'Gear checked', 'assets' => 'Brand assets ready', 'backup' => 'Files backed up', 'delivery' => 'Final files delivered'];

    public const STATUSES = [
        'not_started' => 'Not started',
        'editing' => 'Editing',
        'for_review' => 'For review',
        'posted' => 'Posted',
        'not_required' => 'Not required',
    ];

    protected function casts(): array
    {
        return [
            'photo_posted_on' => 'date',
            'video_posted_on' => 'date',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'photo_due_on' => 'date',
            'video_due_on' => 'date',
            'checklist' => 'array',
            'delivery_sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shooter_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function deliverySender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_sent_by');
    }
    public function revisions(): HasMany { return $this->hasMany(CoverageRevision::class)->orderByDesc('round'); }

    /** Waiting on the crew to pick it up. */
    /** The three jobs on a coverage, and the column that records who took each. */
    public const CREW_ROLES = [
        'shooter' => ['field' => 'shooter_id', 'label' => 'Shooter'],
        'photo' => ['field' => 'photo_editor_id', 'label' => 'Photo edit'],
        'video' => ['field' => 'video_editor_id', 'label' => 'Video edit'],
    ];

    /**
     * Roles nobody has taken yet, as role => label.
     *
     * Accepting claims one role, not the whole job, so the remaining roles have
     * to stay offerable after the first person has answered.
     *
     * @return array<string,string>
     */
    public function openRoles(): array
    {
        $open = [];
        foreach (self::CREW_ROLES as $role => $meta) {
            if ($this->{$meta['field']} === null) {
                $open[$role] = $meta['label'];
            }
        }

        return $open;
    }

    /**
     * Coverage for archived events is out of sight everywhere.
     *
     * Archiving an event hides it from the diary and takes its tasks off the
     * boards, but its coverage row was still turning up in the crew's queue,
     * the workload counts, and the calendar. Coverage is read from a dozen
     * places and filtering each one would eventually miss one, so it is
     * filtered here. Reporting asks for the archived rows back by name, since
     * work that was delivered still happened.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('live_event', fn (Builder $query) => $query
            ->whereHas('event', fn (Builder $event) => $event->whereNull('archived_at')));
    }

    public function isRequested(): bool
    {
        return $this->stage === CoverageDesk::REQUESTED;
    }

    public function isAccepted(): bool
    {
        return $this->stage === CoverageDesk::ACCEPTED;
    }

    public function stageLabel(): string
    {
        return CoverageDesk::STAGES[$this->stage] ?? 'Requested';
    }

    public function photoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photo_editor_id');
    }

    public function videoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'video_editor_id');
    }

    /**
     * No shooter assigned yet for an event that has not happened — the case the
     * team wants to spot early, while there is still time to assign one.
     */
    public function needsShooter(): bool
    {
        return $this->shooter_id === null && $this->event?->event_date?->gte(today());
    }

    public function isComplete(): bool
    {
        return in_array($this->photo_status, ['posted', 'not_required'], true)
            && in_array($this->video_status, ['posted', 'not_required'], true);
    }
}
