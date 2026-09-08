<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\Task;
use App\Models\Coverage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * What can appear on the calendar, and which filter chip each belongs to.
     * Overdue obligations keep their own colour but filter with obligations —
     * hiding them separately would be a way to lose track of them.
     */
    public const FILTERS = [
        'events' => ['label' => 'Events', 'kinds' => ['event']],
        'kits' => ['label' => 'PR kits', 'kinds' => ['delivery', 'pickup', 'giveaway']],
        'content' => ['label' => 'Content due', 'kinds' => ['obligation', 'overdue']],
        'production' => ['label' => 'Multimedia', 'kinds' => ['production', 'production-overdue']],
    ];

    public function __invoke(Request $request): View
    {
        $month = $this->month($request->query('month'));
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        // The grid always shows whole weeks, so it runs from the Monday on or
        // before the 1st to the Sunday on or after the last day.
        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $end->endOfWeek(CarbonImmutable::SUNDAY);

        $all = $this->events($gridStart, $gridEnd)
            ->concat($this->deliveries($gridStart, $gridEnd))
            ->concat($this->pickups($gridStart, $gridEnd))
            ->concat($this->obligations($gridStart, $gridEnd));
        $all = $all->concat($this->production($gridStart, $gridEnd));

        $active = $this->activeFilters($request->query('show'));
        $visibleKinds = collect($active)->flatMap(fn (string $key) => self::FILTERS[$key]['kinds'])->all();

        $entries = $all->filter(fn (array $entry) => in_array($entry['kind'], $visibleKinds, true))->groupBy('date');

        $days = collect();
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days->push([
                'date' => $day,
                'key' => $key,
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isSameDay(today()),
                'entries' => $entries->get($key, collect())->sortBy('sort')->values(),
            ]);
        }

        // A day can hold more than the cell shows, so one can be opened in full.
        $selected = $this->selectedDate($request->query('date'), $gridStart, $gridEnd);

        return view('admin.calendar', [
            'month' => $month,
            'weeks' => $days->chunk(7),
            'days' => $days,
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'entryCount' => $entries->flatten(1)->count(),
            'active' => $active,
            // Counts come from the unfiltered set, so a chip always says how
            // much it would bring back rather than zero once it is switched off.
            'counts' => collect(self::FILTERS)->map(
                fn (array $filter) => $all->whereIn('kind', $filter['kinds'])->count()
            ),
            'selected' => $selected,
            'selectedEntries' => $selected
                ? $entries->get($selected->toDateString(), collect())->sortBy('sort')->values()
                : collect(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function activeFilters(?string $value): array
    {
        $requested = collect(explode(',', (string) $value))
            ->map(fn (string $key) => trim($key))
            ->filter(fn (string $key) => array_key_exists($key, self::FILTERS))
            ->values()
            ->all();

        // No filter, or nothing recognised, means show everything.
        return $requested ?: array_keys(self::FILTERS);
    }

    private function selectedDate(?string $value, CarbonImmutable $from, CarbonImmutable $to): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->between($from, $to) ? $date : null;
    }

    private function month(?string $value): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::createFromFormat('Y-m', $value)->startOfMonth() : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    private function events(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Event::whereNull('archived_at')->whereBetween('event_date', [$from, $to])->get()->map(fn (Event $event) => [
            'date' => $event->event_date->toDateString(),
            'kind' => 'event',
            'label' => $event->name,
            'meta' => $event->eventTypeLabel().' · '.($event->venue ?: 'Venue not set'),
            'time' => $event->start_time,
            'status' => $event->status,
            'url' => route('admin.events.edit', $event),
            'sort' => 1,
        ]);
    }

    private function production(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $tasks = Task::with('event')->whereNull('archived_at')->where('for_team', 'multimedia')->whereBetween('task_date', [$from, $to])->get()->map(fn (Task $task) => [
            'date' => $task->task_date->toDateString(),
            'kind' => $task->task_date->lt(today()) && $task->status !== 'done' ? 'production-overdue' : 'production',
            'label' => $task->title,
            'meta' => $task->event?->name ?: 'Multimedia task',
            'time' => null,
            'status' => $task->status,
            'url' => route('admin.tasks.index', ['date' => $task->task_date->toDateString()]),
            'sort' => 5,
        ]);

        $deadlines = Coverage::with('event')->get()->flatMap(function (Coverage $coverage) use ($from, $to) {
            return collect(['photo' => $coverage->photo_due_on, 'video' => $coverage->video_due_on])
                ->filter(fn ($date) => $date && $date->between($from, $to))
                ->map(fn ($date, $medium) => [
                    'date' => $date->toDateString(), 'kind' => $date->lt(today()) && $coverage->{$medium.'_status'} !== 'posted' ? 'production-overdue' : 'production',
                    'label' => ucfirst($medium).' deadline · '.$coverage->event->name, 'meta' => 'Coverage deadline', 'time' => null,
                    'status' => $coverage->{$medium.'_status'}, 'url' => route('admin.coverage.edit', $coverage->event), 'sort' => 6,
                ]);
        });

        return $tasks->concat($deadlines);
    }

    private function deliveries(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PrKit::with('endorser')->whereBetween('delivery_date', [$from, $to])->get()->map(fn (PrKit $kit) => [
            'date' => $kit->delivery_date->toDateString(),
            'kind' => $kit->isGiveaway() ? 'giveaway' : 'delivery',
            'label' => ($kit->isGiveaway() ? 'Giveaway · ' : 'Deliver · ').$kit->recipient.($kit->quantity > 1 ? " (×{$kit->quantity})" : ''),
            'meta' => $kit->courier ?: 'Courier not set',
            'time' => null,
            'status' => $kit->status,
            'url' => route('admin.pr-kits.edit', $kit),
            'sort' => 2,
        ]);
    }

    private function pickups(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PrKit::with('endorser')->whereBetween('pickup_date', [$from, $to])->get()->map(fn (PrKit $kit) => [
            'date' => $kit->pickup_date->toDateString(),
            'kind' => 'pickup',
            'label' => 'Pick up · '.$kit->recipient,
            'meta' => $kit->address ?: 'Address not set',
            'time' => null,
            'status' => $kit->status,
            'url' => route('admin.pr-kits.edit', $kit),
            'sort' => 3,
        ]);
    }

    private function obligations(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Obligation::with('endorser')->whereBetween('due_date', [$from, $to])->get()->map(fn (Obligation $obligation) => [
            'date' => $obligation->due_date->toDateString(),
            'kind' => $obligation->isOverdue() ? 'overdue' : 'obligation',
            'label' => $obligation->title,
            'meta' => optional($obligation->endorser)->name ?: 'Endorser removed',
            'time' => null,
            'status' => $obligation->status,
            'url' => route('admin.obligations.edit', $obligation),
            'sort' => 4,
        ]);
    }
}
