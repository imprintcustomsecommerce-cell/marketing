@extends('layouts.admin')
@section('title', 'Monthly Kit Coverage')
@section('content')
<style>
    .cal-nav{display:flex;align-items:center;gap:8px}
    .cal-nav a{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--line);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink-soft);font-weight:700}
    .cal-nav a:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .cal-nav .today{width:auto;padding:0 14px;font-size:.85rem}

    /* Two dots per required video: filled once it is delivered. Reading "how
       many are still owed" should not need arithmetic. */
    .dots{display:inline-flex;gap:5px;align-items:center}
    .dots i{width:13px;height:13px;border-radius:50%;border:2px solid var(--line);display:block}
    .dots i.done{background:#059669;border-color:#059669}
    .dots i.part{background:#bfdbfe;border-color:#2563eb}
    .dots .none{color:var(--muted);font-size:.82rem}
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Fulfilment</span>
        <h1>Kit coverage · {{ $month->format('F Y') }}</h1>
        <div class="muted small">One kit per endorser per month, two videos per kit</div>
    </div>
    <div class="cal-nav">
        <a href="{{ route('admin.kit-coverage', ['month' => $previous]) }}" aria-label="Previous month">‹</a>
        <a class="today" href="{{ route('admin.kit-coverage') }}">This month</a>
        <a href="{{ route('admin.kit-coverage', ['month' => $next]) }}" aria-label="Next month">›</a>
    </div>
</div>

@if($withoutKit > 0)
    <div class="card" style="border-color:#fde68a;background:#fffbeb;margin-bottom:18px">
        <strong>{{ $withoutKit }} {{ Str::plural('endorser', $withoutKit) }}</strong> {{ $withoutKit === 1 ? 'has' : 'have' }} no kit recorded for {{ $month->format('F') }}.
        <a class="edit" href="{{ route('admin.pr-kits.create') }}">Send a kit</a>
    </div>
@endif

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Endorser</th><th>Kit this month</th><th>Delivered</th><th>Content</th><th>Outstanding</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            @php $kit = $row['kits']->first(); @endphp
            <tr>
                <td><strong>{{ $row['endorser']->name }}</strong><div class="muted small">{{ str($row['endorser']->type)->title() }}</div></td>
                <td>
                    @if($kit)
                        {{ $kit->reference ?: $kit->recipient }}
                        @if($row['kits']->count() > 1)<div class="muted small">+{{ $row['kits']->count() - 1 }} more this month</div>@endif
                    @else
                        <span class="pill pill-missed">No kit</span>
                    @endif
                </td>
                <td>{{ $kit?->delivery_date?->format('M j') ?: '—' }}</td>
                <td>
                    @if($row['quota'] === 0)
                        <span class="dots"><span class="none">—</span></span>
                    @else
                        <span class="dots" title="{{ $row['done'] }} of {{ $row['quota'] }} delivered">
                            @for($i = 1; $i <= $row['quota']; $i++)
                                <i class="{{ $i <= $row['done'] ? 'done' : ($i <= $row['done'] + $row['submitted'] ? 'part' : '') }}"></i>
                            @endfor
                            <span class="muted small" style="margin-left:4px">{{ $row['done'] }}/{{ $row['quota'] }}</span>
                        </span>
                    @endif
                </td>
                <td>
                    @if($row['quota'] === 0)
                        <span class="muted">—</span>
                    @elseif($row['done'] >= $row['quota'])
                        <span class="pill pill-completed">Complete</span>
                    @else
                        <span class="pill pill-pending">{{ $row['quota'] - $row['done'] }} owed</span>
                    @endif
                </td>
                <td>
                    @if($kit)<a class="edit" href="{{ route('admin.pr-kits.edit', $kit) }}">Open kit</a>
                    @else<a class="edit" href="{{ route('admin.pr-kits.create') }}">Add kit</a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No active endorsers yet. <a class="edit" href="{{ route('admin.endorsers.create') }}">Add one</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
