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
    /**
     * Kept so records written before the request step was dropped still read,
     * and so a declined job asked for again has somewhere to land. Nothing
     * creates coverage in this stage any more: booking an event is the handover.
     */
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
     * Open a coverage job for an event.
     *
     * Booking the event is the handover. There used to be a request the crew had
     * to accept before the work was theirs, but marketing were asking for
     * something that was already coming to them either way, and every hall
     * booking sat in the queue waiting for an answer nobody needed to give.
     *
     * Idempotent on purpose: saving an event twice must not throw away who is
     * already on the job.
     */
    public function request(Event $event, ?User $by = null): Coverage
    {
        $coverage = $event->coverage;

        if ($coverage && $coverage->stage === self::ACCEPTED) {
            return $coverage;
        }

        if ($coverage) {
            // A job turned down and then asked for again is live work once
            // more, and the earlier refusal should not linger on the record.
            $coverage->update([
                'stage' => self::ACCEPTED,
                'requested_at' => now(),
                'requested_by' => $by?->id,
                'accepted_at' => now(),
            ]);

            return $coverage;
        }

        $coverage = $event->coverage()->create([
            'stage' => self::ACCEPTED,
            'accepted_at' => now(),
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
        // Photo and video are separate people on the coverage record, so the
        // editing work is two tasks. One combined "event media" task left the
        // second editor with nothing to take on.
        $schedule = [
            ['Prepare brief and shot list', 'shooter', $event->event_date->copy()->subDays(3)],
            ['Check gear and coverage plan', 'shooter', $event->event_date->copy()->subDay()],
            ['Cover event', 'shooter', $event->event_date],
            ['Edit and deliver event photos', 'photo', $event->event_date->copy()->addDays(2)],
            ['Edit and deliver event videos', 'video', $event->event_date->copy()->addDays(4)],
        ];

        foreach ($schedule as [$title, $role, $date]) {
            Task::firstOrCreate(
                ['event_id' => $event->id, 'title' => $title],
                ['user_id' => null, 'for_team' => User::TEAM_MULTIMEDIA, 'crew_role' => $role, 'details' => $event->name, 'task_date' => $date, 'status' => 'todo', 'created_by' => $by?->id],
            );
        }
    }

    /** The crew take the job on. A shooter can still be named later. */
    public function accept(Coverage $coverage, User $by, bool $assignTasks = true, ?string $specialty = null): Coverage
    {
        $coverage->update([
            'stage' => self::ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $by->id,
        ]);

        if ($assignTasks && in_array($specialty, ['shooter', 'photo', 'video'], true)) {
            $coverage->update([$specialty === 'shooter' ? 'shooter_id' : ($specialty === 'photo' ? 'photo_editor_id' : 'video_editor_id') => $by->id]);
        }

        // Accepting the coverage job also claims its generated production plan,
        // so it leaves Marketing's waiting queue and appears on the crew member's board.
        if ($assignTasks) {
            $tasks = Task::where('event_id', $coverage->event_id)
                ->where('for_team', User::TEAM_MULTIMEDIA)
                ->whereNull('user_id');

            // Match on the recorded role. Tasks raised before roles existed
            // carry none, and stay claimable so old events do not strand work.
            if (in_array($specialty, ['shooter', 'photo', 'video'], true)) {
                $tasks->where(fn ($q) => $q->where('crew_role', $specialty)->orWhereNull('crew_role'));
            }

            $tasks->update(['user_id' => $by->id, 'claimed_at' => now(), 'task_date' => today()]);
        }

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
