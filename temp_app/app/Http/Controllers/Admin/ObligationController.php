<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ObligationController extends Controller
{
    public const TYPES = ['content_video', 'social_post', 'appearance', 'ride_out', 'race_entry', 'content_shoot', 'product_review', 'other'];

    public const STATUSES = ['pending', 'submitted', 'completed', 'waived', 'missed'];

    /** Wording for the confirmation shown after a quick status change. */
    public const STATUSES_LABEL = [
        'pending' => 'pending again',
        'submitted' => 'submitted',
        'completed' => 'delivered',
        'waived' => 'waived',
        'missed' => 'missed',
    ];

    /** The slices of the list people actually work from. */
    public const VIEWS = [
        'open' => 'Open',
        'overdue' => 'Overdue',
        'week' => 'Due this week',
        'done' => 'Delivered',
        'all' => 'All',
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('show'), self::VIEWS)
            ? (string) $request->query('show')
            : 'open';
        $endorserId = (string) $request->query('endorser', '');
        $search = trim((string) $request->query('q'));

        $filtered = fn ($query) => $query
            ->when($endorserId !== '', fn ($q) => $q->where('endorser_id', $endorserId))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('description', 'like', $like));
            });

        $scoped = fn ($query, string $view) => $query
            ->when($view === 'open', fn ($q) => $q->whereIn('status', ['pending', 'submitted']))
            ->when($view === 'overdue', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', '<', today()))
            ->when($view === 'week', fn ($q) => $q->whereIn('status', ['pending', 'submitted'])
                ->whereBetween('due_date', [today(), today()->addDays(7)]))
            ->when($view === 'done', fn ($q) => $q->whereIn('status', ['completed', 'waived']));

        $obligations = Obligation::with(['endorser', 'event', 'prKit'])
            ->tap($filtered)
            ->tap(fn ($query) => $scoped($query, $filter))
            // Delivered work reads newest first; everything else is a queue, so
            // the nearest deadline comes first.
            ->when($filter === 'done', fn ($q) => $q->orderByDesc('completed_on')->orderByDesc('due_date'))
            ->when($filter !== 'done', fn ($q) => $q->orderBy('due_date'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.obligations.index', [
            'obligations' => $obligations,
            'filter' => $filter,
            'endorserId' => $endorserId,
            'search' => $search,
            'endorsers' => Endorser::orderBy('name')->pluck('name', 'id'),
            'counts' => collect(self::VIEWS)->map(
                fn (string $label, string $view) => Obligation::query()->tap($filtered)
                    ->tap(fn ($query) => $scoped($query, $view))->count()
            ),
        ]);
    }

    /**
     * Move one obligation along without opening the whole form: chasing content
     * is a lot of small status changes and few real edits.
     */
    public function status(Request $request, Obligation $obligation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $obligation->update($validated + [
            // Marking it delivered dates it today unless a date was already set.
            'completed_on' => in_array($validated['status'], ['completed', 'waived'], true)
                ? ($obligation->completed_on ?? today())
                : null,
        ]);

        return back()->with('success', "“{$obligation->title}” marked ".self::STATUSES_LABEL[$validated['status']].'.');
    }

    public function create(): View
    {
        return view('admin.obligations.form', ['obligation' => new Obligation] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        Obligation::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.obligations.index')->with('success', 'Obligation added successfully.');
    }

    public function edit(Obligation $obligation): View
    {
        return view('admin.obligations.form', compact('obligation') + $this->options());
    }

    public function update(Request $request, Obligation $obligation): RedirectResponse
    {
        $obligation->update($this->validated($request));

        return redirect()->route('admin.obligations.index')->with('success', 'Obligation updated successfully.');
    }

    /**
     * @return array{endorsers: \Illuminate\Support\Collection, events: \Illuminate\Support\Collection}
     */
    private function options(): array
    {
        return [
            'endorsers' => Endorser::orderBy('name')->pluck('name', 'id'),
            'events' => Event::orderByDesc('event_date')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'endorser_id' => ['required', 'exists:endorsers,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'pr_kit_id' => ['nullable', 'exists:pr_kits,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
            'completed_on' => ['nullable', 'date'],
            'proof_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
