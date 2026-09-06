@extends('layouts.admin')
@section('title', 'PR Kits')
@section('content')
@php
    use App\Http\Controllers\Admin\PrKitController;
    use App\Models\PrKit;

    $base = array_filter([
        'state' => $state === 'open' ? null : $state,
        'purpose' => $purpose ?: null,
        'q' => $search !== '' ? $search : null,
    ]);
@endphp
<style>

    .tabs{display:flex;gap:8px;flex-wrap:wrap}
    .tabs a{padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;text-decoration:none;font-size:.84rem;font-weight:650;color:var(--ink-soft)}
    .tabs a:hover{border-color:var(--accent)}
    .tabs a.on{background:var(--accent);border-color:var(--accent);color:var(--ink)}
    .tabs .count{display:inline-block;margin-left:6px;background:var(--line-soft);color:var(--muted);border-radius:999px;padding:0 7px;font-size:.72rem}
    .tabs a.on .count{background:#ffffff66;color:var(--ink)}

    .purpose{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.68rem;font-weight:800;letter-spacing:.03em;border:1px solid;white-space:nowrap}
    .purpose-endorser{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .purpose-giveaway{background:#fdf2f8;color:#9d174d;border-color:#fbcfe8}

    /* What has to happen next, and whether it has already slipped. */
    .step{white-space:nowrap}
    .step .lbl{font-weight:650;font-size:.87rem}
    .step .dt{font-size:.77rem;color:var(--muted)}
    .step.late .lbl{color:#b91c1c}
    .step.late .dt{color:#b91c1c;font-weight:700}
    .step .flag{display:inline-block;margin-left:6px;font-size:.68rem;font-weight:800;color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:1px 7px;border-radius:999px}

    .dots{display:inline-flex;gap:4px;align-items:center}
    .dots i{width:11px;height:11px;border-radius:50%;border:2px solid #ded7c8;display:block}
    .dots i.done{background:#10b981;border-color:#10b981}
    .dots .txt{font-size:.76rem;color:var(--muted);margin-left:3px}

    .kit-card{display:block;text-decoration:none;color:inherit;padding:15px 0;border-bottom:1px solid var(--line-soft)}
    .kit-card:last-child{border-bottom:0}
    .kit-card .top{display:flex;align-items:center;gap:10px;justify-content:space-between}
    .kit-card .nm{font-weight:700;font-size:.97rem}
    .kit-card .meta{color:var(--muted);font-size:.83rem;margin-top:4px}
    .kit-card .foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}

    @container (max-width:860px){
        .table-card table{display:none}
        .table-card .kit-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Fulfilment</span>
        <h1>PR Kits</h1>
        <div class="muted small">
            @if($search !== '')
                {{ $prKits->total() }} {{ Str::plural('result', $prKits->total()) }} for “{{ $search }}”
            @else
                Deliveries out, pickups back, and giveaway stock
            @endif
        </div>
    </div>
    <a class="button" href="{{ route('admin.pr-kits.create') }}">+ Add PR kit</a>
</div>

<div class="list-head">
    <div class="periods">
        @foreach(['open' => 'In flight', 'settled' => 'Closed', 'all' => 'All'] as $value => $label)
            <a class="{{ $state === $value ? 'on' : '' }}"
               href="{{ route('admin.pr-kits.index', array_filter(Arr::except($base, ['state']) + ['state' => $value === 'open' ? null : $value])) }}">
                {{ $label }}<span class="n">{{ $counts[$value] }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.pr-kits.index') }}">
        @foreach(Arr::except($base, ['q']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="search" name="q" value="{{ $search }}" placeholder="Recipient, reference, tracking…">
        <button class="button ghost" type="submit">Search</button>
        @if($search !== '')
            <a class="edit" href="{{ route('admin.pr-kits.index', Arr::except($base, ['q'])) }}">Clear</a>
        @endif
    </form>
</div>

<div class="filter-bar">
    <div class="tabs">
        <a class="{{ $purpose === '' ? 'on' : '' }}" href="{{ route('admin.pr-kits.index', Arr::except($base, ['purpose'])) }}">
            Both kinds <span class="count">{{ array_sum($purposeCounts) }}</span>
        </a>
        <a class="{{ $purpose === PrKit::PURPOSE_ENDORSER ? 'on' : '' }}" href="{{ route('admin.pr-kits.index', Arr::except($base, ['purpose']) + ['purpose' => PrKit::PURPOSE_ENDORSER]) }}">
            Endorser kits <span class="count">{{ $purposeCounts[PrKit::PURPOSE_ENDORSER] }}</span>
        </a>
        <a class="{{ $purpose === PrKit::PURPOSE_GIVEAWAY ? 'on' : '' }}" href="{{ route('admin.pr-kits.index', Arr::except($base, ['purpose']) + ['purpose' => PrKit::PURPOSE_GIVEAWAY]) }}">
            Giveaways <span class="count">{{ $purposeCounts[PrKit::PURPOSE_GIVEAWAY] }}</span>
        </a>
    </div>
</div>

@if($prKits->isEmpty())
    @if($search !== '' || $purpose !== '' || $state !== 'open')
        <x-empty-state
            icon="search"
            title="Nothing here"
            message="No kits match what you have picked. Try the other kind, or look at all kits including the closed ones."
            action-label="Show all kits"
            :action-url="route('admin.pr-kits.index', ['state' => 'all'])" />
    @else
        <x-empty-state
            icon="box"
            title="Nothing in flight"
            message="Kits sent to endorsers, and giveaway stock handed out at events. Recording a delivered endorser kit creates the content it owes automatically."
            action-label="+ Add a PR kit"
            :action-url="route('admin.pr-kits.create')"
            secondary-label="See closed kits"
            :secondary-url="route('admin.pr-kits.index', ['state' => 'settled'])" />
    @endif
@else
<div class="card table-card name-first" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Recipient</th><th>Linked to</th><th>Next step</th><th>Courier</th><th>Content</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($prKits as $prKit)
            @php
                $step = $prKit->nextStep();
                $progress = $prKit->contentProgress();
            @endphp
            <tr>
                <td>
                    <strong>{{ $prKit->recipient }}</strong>
                    <div class="muted small">
                        <span class="purpose purpose-{{ $prKit->purpose }}">{{ $prKit->isGiveaway() ? 'Giveaway' : 'Endorser' }}</span>
                        @if($prKit->quantity > 1)<span style="margin-left:6px">×{{ $prKit->quantity }}</span>@endif
                        @if($prKit->reference)<span style="margin-left:6px">{{ $prKit->reference }}</span>@endif
                    </div>
                </td>
                <td>
                    @if($prKit->endorser)<div class="small">{{ $prKit->endorser->name }}</div>@endif
                    @if($prKit->event)<div class="muted small">{{ $prKit->event->name }}</div>@endif
                    @if(! $prKit->endorser && ! $prKit->event)<span class="muted">—</span>@endif
                </td>
                <td class="step {{ $step['late'] ? 'late' : '' }}">
                    <div class="lbl">
                        {{ $step['label'] }}
                        @if($step['late'])<span class="flag">Late</span>@endif
                    </div>
                    @if($step['date'])
                        <div class="dt">
                            {{ $step['date']->format('M j, Y') }}
                            @if(! $step['late'] && $step['date']->isFuture()) · in {{ today()->diffInDays($step['date']) }}d @endif
                        </div>
                    @endif
                </td>
                <td>{{ $prKit->courier ?: '—' }}</td>
                <td>
                    @if($prKit->isGiveaway())
                        <span class="muted small">Giveaway</span>
                    @elseif($progress['quota'] === 0)
                        <span class="muted">None</span>
                    @else
                        <span class="dots" title="{{ $progress['done'] }} of {{ $progress['quota'] }} delivered">
                            @for($i = 1; $i <= $progress['quota']; $i++)<i class="{{ $i <= $progress['done'] ? 'done' : '' }}"></i>@endfor
                            <span class="txt">{{ $progress['done'] }}/{{ $progress['quota'] }}</span>
                        </span>
                    @endif
                </td>
                <td><span class="pill pill-{{ $prKit->status }}">{{ str($prKit->status)->replace('_', ' ')->title() }}</span></td>
                <td><a class="edit" href="{{ route('admin.pr-kits.edit', $prKit) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- The same kits as cards, for screens the table cannot fit. --}}
    <div class="stack-cards kit-cards">
        @foreach($prKits as $prKit)
            @php
                $step = $prKit->nextStep();
                $progress = $prKit->contentProgress();
            @endphp
            <a class="kit-card" href="{{ route('admin.pr-kits.edit', $prKit) }}">
                <div class="top">
                    <span class="nm">{{ $prKit->recipient }}</span>
                    <span class="pill pill-{{ $prKit->status }}">{{ str($prKit->status)->replace('_', ' ')->title() }}</span>
                </div>
                <div class="meta step {{ $step['late'] ? 'late' : '' }}">
                    {{ $step['label'] }}@if($step['date']) · {{ $step['date']->format('M j, Y') }}@endif
                    @if($step['late'])<span class="flag">Late</span>@endif
                </div>
                <div class="foot">
                    <span class="purpose purpose-{{ $prKit->purpose }}">{{ $prKit->isGiveaway() ? 'Giveaway' : 'Endorser' }}</span>
                    @if($prKit->quantity > 1)<span class="muted small">×{{ $prKit->quantity }}</span>@endif
                    @if(! $prKit->isGiveaway() && $progress['quota'] > 0)
                        <span class="dots">
                            @for($i = 1; $i <= $progress['quota']; $i++)<i class="{{ $i <= $progress['done'] ? 'done' : '' }}"></i>@endfor
                            <span class="txt">{{ $progress['done'] }}/{{ $progress['quota'] }}</span>
                        </span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</div>
{{ $prKits->links() }}
@endif
@endsection
