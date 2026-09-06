<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Support\TaskDesk;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A day at a time, per person. Everyone works their own board; the
 * administrator can look at anyone's, since Joey checks on both teams.
 */
class TaskBoardController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->date($request->query('date'));
        $owner = $this->owner($request);

        // raisedBy so a claimed card can name who asked for it.
        $tasks = Task::with(['event', 'raisedBy'])
            ->where('user_id', $owner->id)
            ->whereDate('task_date', $date)
            ->orderBy('id')
            ->get()
            ->groupBy('status');

        $all = $tasks->flatten();

        // A week's worth of counts, so the day strip can show where the work is
        // without a query per day.
        $weekStart = $date->startOfWeek(CarbonImmutable::MONDAY);
        $week = Task::where('user_id', $owner->id)
            ->whereBetween('task_date', [$weekStart, $weekStart->addDays(6)])
            ->get(['task_date', 'status'])
            ->groupBy(fn (Task $task) => $task->task_date->toDateString());

        return view('admin.tasks.index', [
            'date' => $date,
            'owner' => $owner,
            'tasks' => $tasks,
            'total' => $all->count(),
            'doneCount' => $all->where('status', 'done')->count(),
            'weekStart' => $weekStart,
            'week' => $week,
            'previous' => $date->subDay()->toDateString(),
            'next' => $date->addDay()->toDateString(),
            'events' => Event::whereDate('event_date', '>=', today()->subMonth())->orderBy('event_date')->pluck('name', 'id'),
            // Only the administrator gets the person switcher.
            'people' => $request->user()->isAdmin()
                ? User::where('is_active', true)->orderBy('team')->orderBy('name')->get()
                : collect(),
            'submission' => TaskSubmission::where('user_id', $owner->id)->whereDate('task_date', $date)->first(),
            'pendingReviews' => $request->user()->isAdmin()
                ? TaskSubmission::with('user')->whereNull('reviewed_at')->orderBy('submitted_at')->get()
                : collect(),
            'multimediaTeam' => User::TEAM_MULTIMEDIA,
            // This is the Multimedia team's intake queue. Administrators can
            // manage the system, but a Marketing administrator should not see
            // or claim the crew's unassigned production work.
            'teamQueue' => $request->user()->team === User::TEAM_MULTIMEDIA
                ? Task::with(['event', 'raisedBy'])->unclaimedFor(User::TEAM_MULTIMEDIA)->orderBy('id')->get()
                : collect(),
            'carriedOver' => Task::where('user_id', $owner->id)
                ->whereDate('task_date', '<', $date)
                ->whereIn('status', ['todo', 'doing'])
                ->count(),
        ]);
    }

    public function store(Request $request, TaskDesk $desk): RedirectResponse
    {
        $owner = $this->owner($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'task_date' => ['required', 'date'],
            'event_id' => ['nullable', 'exists:events,id'],
            'for_team' => ['nullable', Rule::in([User::TEAM_MULTIMEDIA, 'personal'])],
        ]);

        // Marketing work is production work by default, so it reaches
        // Multimedia without relying on somebody to choose the queue manually.
        // "personal" remains an explicit escape hatch for internal desk work.
        $destination = $validated['for_team'] ?? ($request->user()->canSeeMarketing() ? User::TEAM_MULTIMEDIA : null);
        $team = $destination === 'personal' ? null : $destination;
        unset($validated['for_team']);

        if ($team !== null) {
            // Only marketing hands work to the crew, and the crew are not
            // handed a person — the task waits until one of them takes it.
            abort_unless($request->user()->canSeeMarketing(), 403);

            $desk->raiseFor($team, $validated, $request->user());

            return back()->with('success', 'Sent to the multimedia queue.');
        }

        Task::create($validated + [
            'user_id' => $owner->id,
            'status' => 'todo',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task added.');
    }

    /** Someone on the team takes a queued task onto their own board. */
    public function claim(Request $request, Task $task, TaskDesk $desk): RedirectResponse
    {
        $user = $request->user();

        abort_unless($task->isUnclaimed(), 404);
        abort_unless($user->team === $task->for_team, 403);

        $desk->claim($task, $user);

        return back()->with('success', 'Added to your board for today.');
    }

    /** And can put it back if it was not theirs to do. */
    public function release(Request $request, Task $task, TaskDesk $desk): RedirectResponse
    {
        $this->authoriseTask($request, $task);
        abort_if($task->for_team === null, 404);

        $desk->release($task);

        return back()->with('success', 'Put back in the team queue.');
    }

    /**
     * Handles both moving a card between columns and editing its wording, since
     * either one is a small change to the same row.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authoriseTask($request, $task);

        $validated = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(array_keys(Task::STATUSES))],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'event_id' => ['sometimes', 'nullable', 'exists:events,id'],
        ]);

        if (array_key_exists('status', $validated)) {
            // Reopening a card clears the completion stamp rather than leaving a
            // stale one behind.
            $validated['completed_at'] = $validated['status'] === 'done' ? now() : null;
        }

        $task->update($validated);

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authoriseTask($request, $task);
        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    /**
     * Move everything still open from earlier days onto this board, rather than
     * making someone retype yesterday's unfinished work.
     */
    public function carryOver(Request $request): RedirectResponse
    {
        $date = $this->date($request->input('date'));
        $owner = $this->owner($request);

        $moved = Task::where('user_id', $owner->id)
            ->whereDate('task_date', '<', $date)
            ->whereIn('status', ['todo', 'doing'])
            ->update(['task_date' => $date]);

        return back()->with('success', $moved === 0
            ? 'Nothing was left open on earlier days.'
            : "{$moved} unfinished ".str('task')->plural($moved).' moved to this day.');
    }

    /**
     * Hand the day's board to the administrator for checking. Sending again
     * after feedback re-opens the same row rather than stacking duplicates.
     */
    public function submit(Request $request): RedirectResponse
    {
        $date = $this->date($request->input('date'));
        $owner = $this->owner($request);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        abort_if(Task::where('user_id', $owner->id)->whereDate('task_date', $date)->doesntExist(), 422);

        TaskSubmission::updateOrCreate(
            ['user_id' => $owner->id, 'task_date' => $date],
            [
                'submitted_at' => now(),
                'note' => $validated['note'] ?? null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'feedback' => null,
            ],
        );

        return back()->with('success', 'Sent to the administrator for checking.');
    }

    /**
     * The administrator signs off on a submitted day.
     */
    public function review(Request $request, TaskSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate(['feedback' => ['nullable', 'string', 'max:1000']]);

        $submission->update([
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        return back()->with('success', "Checked {$submission->user->name}'s board.");
    }

    private function date(?string $value): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value)->startOfDay() : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /**
     * Whose board is being looked at. Staff always get their own.
     */
    private function owner(Request $request): User
    {
        $requested = $request->input('user');

        if ($requested && $request->user()->isAdmin()) {
            return User::findOr($requested, fn () => $request->user());
        }

        return $request->user();
    }

    private function authoriseTask(Request $request, Task $task): void
    {
        $user = $request->user();

        // Whoever raised a queued task can still withdraw or reword it while it
        // is sitting there unclaimed; once someone takes it, it is theirs.
        $ownsRequest = $task->isUnclaimed() && $task->created_by === $user->id;

        abort_unless($user->isAdmin() || $task->user_id === $user->id || $ownsRequest, 403);
    }
}
