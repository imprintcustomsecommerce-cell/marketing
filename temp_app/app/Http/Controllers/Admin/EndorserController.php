<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\PrKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EndorserController extends Controller
{
    /** @var list<string> */
    public const TYPES = ['individual', 'racer', 'team', 'influencer', 'organization'];

    /** The roster is worked by status far more often than by anything else. */
    public const STATUSES = ['active' => 'Active', 'pending' => 'Pending', 'new' => 'New', 'inactive' => 'Inactive'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        $filtered = fn ($query) => $query
            ->when(array_key_exists($status, self::STATUSES), fn ($q) => $q->where('status', $status))
            ->when(in_array($type, self::TYPES, true), fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('team_or_group', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('contact_number', 'like', $like);
                });
            });

        $endorsers = Endorser::query()
            ->tap($filtered)
            // This month's kit and the content it owes, so the roster shows
            // whether the monthly rule has been kept without a second screen.
            ->with(['prKits' => fn ($query) => $query
                ->where('purpose', PrKit::PURPOSE_ENDORSER)
                ->whereBetween('delivery_date', [today()->startOfMonth(), today()->endOfMonth()])
                ->with('obligations')])
            // Working names first: nobody chases an inactive endorser.
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 WHEN 'new' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.endorsers.index', [
            'endorsers' => $endorsers,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'statusCounts' => Endorser::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'total' => Endorser::count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.endorsers.form', ['endorser' => new Endorser]);
    }

    public function store(Request $request): RedirectResponse
    {
        Endorser::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.endorsers.index')->with('success', 'Endorser added successfully.');
    }

    public function edit(Endorser $endorser): View
    {
        return view('admin.endorsers.form', compact('endorser'));
    }

    public function update(Request $request, Endorser $endorser): RedirectResponse
    {
        $endorser->update($this->validated($request));

        return redirect()->route('admin.endorsers.index')->with('success', 'Endorser updated successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'team_or_group' => ['nullable', 'string', 'max:255'],
            'social_media_url' => ['nullable', 'url', 'max:500'],
            'group_chat_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['new', 'active', 'pending', 'inactive'])],
            'profile' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
