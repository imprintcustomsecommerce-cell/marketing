<?php

namespace App\Support;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\User;
use App\Models\Task;

/**
 * The handoff from marketing to multimedia.
 *
 * Marketing does not assign anyone: booking an event opens a coverage job and
 * drops it in the crew's queue, and someone there picks it up. That matches how
 * the shop actually works — a shooter is named sometimes and not others — so
 * the job can be accepted with nobody on it yet.
 *
 * Both the automatic path (creating an event) and the manual one (the "Request
 * coverage" button) come through here, so a job is opened exactly one way.
 */
class CoverageDesk
{
    /** Marketing has asked for this; nobody on the crew has picked it up. */
    public const REQUESTED = 'requested';

    /** Multimedia has taken it on, with or without a shooter named yet. */
    public const ACCEPTED = 'accepted';

    /** The crew have been told this one does not need covering. */
    public const DECLINED = 'declined';
    public const COMPLETED = 'completed';

    public const STAGES = [
        self::REQUESTED => 'Requested',
        self::ACCEPTED => 'Accepted',
        self::DECLINED => 'Not covering',
        self::COMPLETED => 'Production complete',
    ];

    /**
     * Open a coverage job for an event, or re-open one that was turned down.
     *
     * Idempotent on purpose: saving an event twice, or pressing the button on a
     * job the crew already accepted, must not throw away who accepted it or
     * bounce a live job back into the new-work queue.
     */
    public function request(Event $event, ?User $by = null): Coverage
    {
        $coverage = $event->coverage;

        if ($coverage && $coverage->stage === self::ACCEPTED) {
            return $coverage;
        }

        if ($coverage) {
            // A declined job asked for again is new work once more, and the
            // earlier refusal should not linger on the record.
            $coverage->update([
                'stage' => self::REQUESTED,
                'requested_at' => now(),
                'requested_by' => $by?->id,
            ]);

            return $coverage;
        }

        $coverage = $event->coverage()->create([
            'stage' => self::REQUESTED,
            'requested_at' => now(),
            'requested_by' => $by?->id,
            // Sensible defaults: fast social photos first, edited video after.
            // Multimedia can still change either date on the coverage screen.
            'photo_due_on' => $event->event_date->copy()->addDays(2),
            'video_due_on' => $event->event_date->copy()->addDays(4),
            'created_by' => $by?->id,
        ]);

        $this->createProductionTasks($event, $by);

        return $coverage;
    }

    private function createProductionTasks(Event $event, ?User $by): void
    {
        $schedule = [
            ['Prepare brief and shot list', $event->event_date->copy()->subDays(3)],
            ['Check gear and coverage plan', $event->event_date->copy()->subDay()],
            ['Cover event', $event->event_date],
            ['Edit and deliver event media', $event->event_date->copy()->addDays(3)],
        ];

        foreach ($schedule as [$title, $date]) {
            Task::firstOrCreate(
                ['event_id' => $event->id, 'title' => $title],
                ['user_id' => null, 'for_team' => User::TEAM_MULTIMEDIA, 'details' => $event->name, 'task_date' => $date, 'status' => 'todo', 'created_by' => $by?->id],
            );
        }
    }

    /** The crew take the job on. A shooter can still be named later. */
    public function accept(Coverage $coverage, User $by): Coverage
    {
        $coverage->update([
            'stage' => self::ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $by->id,
        ]);

        // Accepting the coverage job also claims its generated production plan,
        // so it leaves Marketing's waiting queue and appears on the crew member's board.
        Task::where('event_id', $coverage->event_id)
            ->where('for_team', User::TEAM_MULTIMEDIA)
            ->whereNull('user_id')
            ->update(['user_id' => $by->id, 'claimed_at' => now()]);

        return $coverage;
    }

    /** Nothing to shoot here. Marketing can ask again if that changes. */
    public function decline(Coverage $coverage, User $by): Coverage
    {
        $coverage->update([
            'stage' => self::DECLINED,
            'accepted_at' => now(),
            'accepted_by' => $by->id,
        ]);

        return $coverage;
    }
}
