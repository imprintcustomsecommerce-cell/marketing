<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Coverage;
use App\Models\Event;
use App\Models\EventFile;
use App\Models\Task;
use App\Support\CoverageDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    /** Which slice of the diary is being looked at. */
    public const PERIODS = ['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All', 'archived' => 'Archived'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $category = (string) $request->query('category', '');
        $period = array_key_exists((string) $request->query('when'), self::PERIODS)
            ? (string) $request->query('when')
            : 'upcoming';

        $filtered = fn ($query) => $query
            ->when($period === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when(array_key_exists($category, Event::EVENT_TYPES), fn ($q) => $q->where('event_type', $category))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('organization', 'like', $like)
                        ->orWhere('venue', 'like', $like);
                });
            });

        // Upcoming reads forwards from today; past reads backwards from
        // yesterday. Sorting the whole table newest-first buried next week's
        // ride-out under everything that had already happened.
        $events = Event::with('coverage.shooter')
            ->tap($filtered)
            ->when($period === 'upcoming', fn ($query) => $query->whereDate('event_date', '>=', today())->orderBy('event_date'))
            ->when($period === 'past', fn ($query) => $query->whereDate('event_date', '<', today())->orderByDesc('event_date'))
            ->when($period === 'all', fn ($query) => $query->orderByDesc('event_date'))
            ->when($period === 'archived', fn ($query) => $query->orderByDesc('archived_at'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
            'search' => $search,
            'category' => $category,
            'period' => $period,
            // Counts respect the other filters, so a chip says what it will
            // actually return rather than a total from the whole table.
            'counts' => Event::query()->tap($filtered)
                ->when($period === 'upcoming', fn ($q) => $q->whereDate('event_date', '>=', today()))
                ->when($period === 'past', fn ($q) => $q->whereDate('event_date', '<', today()))
                ->selectRaw('event_type, count(*) as total')->groupBy('event_type')->pluck('total', 'event_type'),
            'periodCounts' => [
                'upcoming' => Event::query()->tap($filtered)->whereDate('event_date', '>=', today())->count(),
                'past' => Event::query()->tap($filtered)->whereDate('event_date', '<', today())->count(),
                'all' => Event::query()->tap($filtered)->count(),
                'archived' => Event::whereNotNull('archived_at')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.events.form', ['event' => new Event]);
    }

    public function store(Request $request, CoverageDesk $desk): RedirectResponse
    {
        $event = Event::create($this->validated($request) + ['created_by' => $request->user()->id]);

        // Booking an event is the request for coverage. The crew see it in
        // their queue the moment it is saved, rather than marketing having to
        // remember to tell them separately.
        $desk->request($event, $request->user());

        $redirect = redirect()->route('admin.events.index')->with('success', 'Event added, and multimedia have been asked to cover it.');
        if ($this->conflicts($event)->isNotEmpty()) $redirect->with('warning', 'Schedule warning: another event is already booked on this date.');
        return $redirect;
    }

    public function edit(Event $event): View
    {
        return view('admin.events.form', compact('event'));
    }

    public function show(Event $event): View
    {
        $event->load(['coverage.shooter', 'coverage.photoEditor', 'coverage.videoEditor', 'tasks.user', 'files.uploader', 'publicLinks', 'creator']);

        $subjectGroups = [
            Event::class => [$event->id],
            Coverage::class => $event->coverage ? [$event->coverage->id] : [],
            Task::class => $event->tasks->pluck('id')->all(),
            EventFile::class => $event->files->pluck('id')->all(),
        ];

        $activityQuery = ActivityLog::with('user')->where(function ($query) use ($subjectGroups): void {
            foreach ($subjectGroups as $type => $ids) {
                if ($ids) {
                    $query->orWhere(fn ($part) => $part->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            }
        });

        return view('admin.events.show', [
            'event' => $event,
            'activities' => $activityQuery->latest()->limit(15)->get(),
        ]);
    }
    public function summary(Event $event): View
    {
        $event->load(['coverage.shooter','coverage.photoEditor','coverage.videoEditor','coverage.deliverySender','coverage.revisions.creator','tasks.user','files.uploader','creator']);
        return view('admin.events.summary', compact('event'));
    }

    /**
     * Ask the crew to cover an event that never reached them — one from before
     * the handoff existed, or one they turned down and that is now going ahead.
     */
    public function requestCoverage(Request $request, Event $event, CoverageDesk $desk): RedirectResponse
    {
        $desk->request($event, $request->user());

        return back()->with('success', "Multimedia have been asked to cover {$event->name}.");
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $oldDate = $event->event_date->copy();
        $event->update($this->validated($request));

        if (! $oldDate->isSameDay($event->event_date)) {
            $days = $oldDate->diffInDays($event->event_date, false);
            $event->tasks()->where('status', '!=', 'done')->get()->each(fn (Task $task) => $task->update(['task_date' => $task->task_date->copy()->addDays($days)]));
            $event->coverage?->update(['photo_due_on' => $event->event_date->copy()->addDays(2), 'video_due_on' => $event->event_date->copy()->addDays(4)]);
        }

        $redirect = redirect()->route('admin.events.index')->with('success', 'Event updated successfully. Unfinished production dates were synchronized.');
        if ($this->conflicts($event)->isNotEmpty()) $redirect->with('warning', 'Schedule warning: another event is already booked on this date.');
        return $redirect;
    }

    /**
     * Tick items off the pre-event checklist.
     *
     * Separate from the edit form so both teams can keep it current without
     * opening — or being allowed to open — the whole event record. Only the
     * ticks move; the wording of a custom item is changed on the form.
     */
    public function preparation(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'preparation_done' => ['nullable', 'array'],
            'preparation_done.*' => ['string', Rule::in(array_keys(Event::PREPARATION))],
            'custom_done' => ['nullable', 'array'],
            'custom_done.*' => ['integer', 'min:0'],
        ]);

        $ticked = array_map('intval', $validated['custom_done'] ?? []);

        $event->update([
            // Only items the event actually asked for can be marked done, so a
            // stale tick cannot survive an item being taken off the list.
            'preparation_done' => array_values(array_intersect(
                array_keys($event->preparationNeeded()),
                $validated['preparation_done'] ?? [],
            )),
            'custom_preparation' => array_values(array_map(
                fn (array $item, int $index) => ['label' => $item['label'], 'done' => in_array($index, $ticked, true)],
                $event->customPreparation(),
                array_keys($event->customPreparation()),
            )),
        ]);

        return back()->with('success', 'Checklist updated.');
    }

    /**
     * Delete the event outright.
     *
     * Archiving is the everyday "remove": it hides the event but keeps the
     * record. This is the other one — for a booking entered by mistake or a
     * duplicate, where leaving it archived only clutters the list.
     *
     * The database nulls the event out of tasks, kits, and obligations rather
     * than deleting them, which would strand the generated production tasks on
     * people's boards with nothing to open. Those are removed here, along with
     * the uploaded files, which live on disk and no foreign key can reach.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $name = $event->name;

        foreach ($event->files as $file) {
            Storage::disk('local')->delete($file->path);
        }

        // Public links point at a resource id with no foreign key behind it, so
        // without this a shared link would outlive the event it was made for.
        $event->publicLinks()->delete();
        $event->tasks()->delete();

        // Coverage and event files go with it through the schema's cascade.
        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', "\"{$name}\" and everything filed under it were deleted.");
    }

    private function conflicts(Event $event)
    {
        return Event::whereKeyNot($event->id)->whereNull('archived_at')->whereDate('event_date', $event->event_date)->whereNotIn('status', ['cancelled'])->get()->filter(function(Event $other) use($event){
            $sameVenue=$event->venue && $other->venue && mb_strtolower(trim($event->venue))===mb_strtolower(trim($other->venue));
            if(!$event->start_time || !$event->end_time || !$other->start_time || !$other->end_time) return true;
            $overlaps=$event->start_time < $other->end_time && $event->end_time > $other->start_time;
            return $overlaps || $sameVenue;
        });
    }

    public function archive(Event $event): RedirectResponse
    {
        $event->update(['archived_at' => now()]);

        // The work goes with it. An archived event left its production tasks
        // sitting on people's boards, due for a day nobody is now working
        // towards, with no way to tell why they were there.
        $archived = $event->tasks()->update(['archived_at' => now()]);

        return redirect()->route('admin.events.index')->with(
            'success',
            $archived > 0
                ? "Event archived, along with {$archived} ".str('task')->plural($archived).'. Both come back if you restore it.'
                : 'Event archived. It can be restored anytime.',
        );
    }

    public function restore(Event $event): RedirectResponse
    {
        $event->update(['archived_at' => null]);

        // Archived tasks are hidden by a global scope, so they have to be asked
        // for by name to be brought back.
        $event->tasks()->withoutGlobalScope('not_archived')
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null]);

        return redirect()->route('admin.events.show', $event)->with('success', 'Event restored.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Event::CATEGORIES))],
            'event_type' => ['required', Rule::in(['in_house','outside_event','tambike'])],
            'event_category' => ['required', Rule::in(['motorcycle','automotive','car','others'])],
            'organization' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'event_date' => ['required', 'date'],
            // Load-in usually precedes the event and load-out follows it, but
            // neither is required: a short booth is often in and out on the day.
            'ingress_date' => ['nullable', 'date'],
            'egress_date' => ['nullable', 'date', 'after_or_equal:ingress_date'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'booth_size' => ['nullable', 'string', 'max:100'],
            'venue_type' => ['nullable', Rule::in(array_keys(Event::VENUE_TYPES))],
            'deal_type' => ['nullable', Rule::in(array_keys(Event::DEAL_TYPES))],
            // An amount only means anything on a cash deal, and is required there.
            'cash_amount' => ['nullable', 'required_if:deal_type,cash', 'numeric', 'min:0', 'max:99999999'],
            'preparation' => ['nullable', 'array'],
            'preparation.*' => ['string', Rule::in(array_keys(Event::PREPARATION))],
            'custom_preparation' => ['nullable', 'array', 'max:30'],
            'custom_preparation.*.label' => ['nullable', 'string', 'max:120'],
            'custom_preparation.*.done' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'venue' => ['nullable', 'string', 'max:255'],
            'group_chat_url' => ['nullable', 'url', 'max:500'],
            'estimated_pax' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::in(['new', 'pending', 'confirmed', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Unticking every box posts nothing at all, so an absent list has to
        // mean "none ticked" rather than "leave the old ones alone".
        $validated['preparation'] = array_values($validated['preparation'] ?? []);

        // Rows are added in the browser, so a blank one is somebody clicking
        // "Add item" and changing their mind rather than something to store.
        $validated['custom_preparation'] = array_values(array_map(
            fn (array $item) => ['label' => trim($item['label']), 'done' => (bool) ($item['done'] ?? false)],
            // Ticking on the form says an item is needed, never that it is
            // already sorted — that is the checklist panel's job.
            array_filter(
                $validated['custom_preparation'] ?? [],
                fn ($item) => is_array($item) && filled(trim((string) ($item['label'] ?? ''))),
            ),
        ));

        // An ex-deal carries no figure, so a leftover amount is cleared rather
        // than kept against a deal that is no longer paid in cash.
        if (($validated['deal_type'] ?? null) !== 'cash') {
            $validated['cash_amount'] = null;
        }

        return $validated;
    }
}



