<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\PrKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrKitController extends Controller
{
    public const STATUSES = ['scheduled', 'packed', 'in_transit', 'delivered', 'awaiting_pickup', 'returned', 'cancelled'];

    /** Kits that have finished their journey and need no more attention. */
    public const SETTLED = ['returned', 'cancelled'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $purpose = (string) $request->query('purpose', '');
        $state = (string) $request->query('state', 'open');

        $filtered = fn ($query) => $query
            ->when(in_array($purpose, PrKit::PURPOSES, true), fn ($q) => $q->where('purpose', $purpose))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('recipient', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('courier', 'like', $like)
                        ->orWhere('tracking_number', 'like', $like);
                });
            });

        $prKits = PrKit::with(['endorser', 'event', 'obligations'])
            ->tap($filtered)
            ->when($state === 'open', fn ($q) => $q->whereNotIn('status', self::SETTLED))
            ->when($state === 'settled', fn ($q) => $q->whereIn('status', self::SETTLED))
            // Whatever moves next comes first; a kit already back on the shelf
            // has no reason to sit at the top of the list.
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', self::SETTLED)
            ->orderByRaw('COALESCE(delivery_date, pickup_date) asc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pr-kits.index', [
            'prKits' => $prKits,
            'search' => $search,
            'purpose' => $purpose,
            'state' => $state,
            'counts' => [
                'open' => PrKit::query()->tap($filtered)->whereNotIn('status', self::SETTLED)->count(),
                'settled' => PrKit::query()->tap($filtered)->whereIn('status', self::SETTLED)->count(),
                'all' => PrKit::query()->tap($filtered)->count(),
            ],
            'purposeCounts' => [
                PrKit::PURPOSE_ENDORSER => PrKit::where('purpose', PrKit::PURPOSE_ENDORSER)->count(),
                PrKit::PURPOSE_GIVEAWAY => PrKit::where('purpose', PrKit::PURPOSE_GIVEAWAY)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.pr-kits.form', ['prKit' => new PrKit(['content_quota' => 2, 'purpose' => PrKit::PURPOSE_ENDORSER, 'quantity' => 1])] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $prKit = PrKit::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.pr-kits.index')
            ->with('success', 'PR kit added successfully.'.$this->obligationNotice($prKit));
    }

    public function edit(PrKit $prKit): View
    {
        $prKit->load('obligations.endorser');

        return view('admin.pr-kits.form', ['prKit' => $prKit] + $this->options());
    }

    public function update(Request $request, PrKit $prKit): RedirectResponse
    {
        $prKit->update($this->validated($request));

        return redirect()->route('admin.pr-kits.index')
            ->with('success', 'PR kit updated successfully.'.$this->obligationNotice($prKit));
    }

    /**
     * Generating the content obligations is the whole point of recording a kit,
     * so it happens on save rather than waiting for someone to remember.
     */
    private function obligationNotice(PrKit $prKit): string
    {
        $created = $prKit->syncContentObligations();

        if ($created === 0) {
            return '';
        }

        return " {$created} content ".str('obligation')->plural($created)." created, due {$prKit->delivery_date->endOfMonth()->format('M j, Y')}.";
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
        // Omitting the quota means the house rule applies: two videos per kit.
        $request->mergeIfMissing(['content_quota' => 2, 'purpose' => PrKit::PURPOSE_ENDORSER, 'quantity' => 1]);

        $isGiveaway = $request->input('purpose') === PrKit::PURPOSE_GIVEAWAY;

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:60'],
            'recipient' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(PrKit::PURPOSES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'endorser_id' => [$isGiveaway ? 'nullable' : 'required', 'exists:endorsers,id'],
            'event_id' => [$isGiveaway ? 'required' : 'nullable', 'exists:events,id'],
            'contents' => ['nullable', 'string', 'max:5000'],
            'courier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'delivery_date' => ['nullable', 'date'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:delivery_date'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'content_quota' => ['required', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // The two shapes are mutually exclusive, and the server decides rather
        // than trusting whatever the form happened to leave in a hidden field.
        if ($isGiveaway) {
            // Giveaway stock goes to an event, and owes no content back.
            $validated['endorser_id'] = null;
            $validated['content_quota'] = 0;
        } else {
            // An endorser kit is a monthly shipment to a person, not to an event.
            $validated['event_id'] = null;
        }

        return $validated;
    }
}
