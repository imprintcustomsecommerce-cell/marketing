@extends('layouts.admin')
@section('title', 'Endorsers')
@section('content')
@php
    use App\Http\Controllers\Admin\EndorserController;

    // Every control keeps the others, so changing one never resets the rest.
    $base = array_filter([
        'status' => $status ?: null,
        'type' => $type ?: null,
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

    .who{display:flex;align-items:center;gap:11px}
    .pic{width:36px;height:36px;border-radius:50%;background:linear-gradient(145deg,#fcd34d,#f59e0b);color:#1c1206;display:grid;place-items:center;font-weight:800;font-size:.8rem;flex:none}
    .who .nm{font-weight:700}
    .who .sub{font-size:.79rem;color:var(--muted)}

    .type-tag{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid var(--line);background:#faf8f4;color:var(--ink-soft);white-space:nowrap}

    .contact a{display:block;font-size:.84rem;text-decoration:none;color:var(--ink-soft)}
    .contact a:hover{color:var(--accent-ink);text-decoration:underline}

    /* The house rule, per row: was a kit sent this month, and how much of the
       content it owes has landed. */
    .kit{display:flex;align-items:center;gap:9px;white-space:nowrap}
    .dots{display:inline-flex;gap:4px}
    .dots i{width:11px;height:11px;border-radius:50%;border:2px solid #ded7c8;display:block}
    .dots i.done{background:#10b981;border-color:#10b981}
    .kit .sent{font-size:.78rem;font-weight:700;color:#047857}
    .missing{font-size:.75rem;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:3px 9px;border-radius:999px;white-space:nowrap}

    .chat{display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:.8rem;font-weight:700;color:var(--accent-ink);background:var(--accent-bg);border:1px solid #fde68a;padding:5px 11px;border-radius:999px;white-space:nowrap}
    .chat:hover{border-color:var(--accent);background:#fef3c7}
    .chat svg{width:14px;height:14px}
    .chat-add{font-size:.8rem;color:var(--muted);text-decoration:none}
    .chat-add:hover{color:var(--accent-ink);text-decoration:underline}

    .en-card{display:block;text-decoration:none;color:inherit;padding:15px 0;border-bottom:1px solid var(--line-soft)}
    .en-card:last-child{border-bottom:0}
    .en-card .top{display:flex;align-items:center;gap:11px;justify-content:space-between}
    .en-card .foot{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:11px}

    @container (max-width:820px){
        .table-card table{display:none}
        .table-card .en-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Partner directory</span>
        <h1>Endorsers</h1>
        <div class="muted small">
            @if($search !== '')
                {{ $endorsers->total() }} {{ Str::plural('result', $endorsers->total()) }} for “{{ $search }}”
            @else
                Racers, teams, influencers, and partner organisations
            @endif
        </div>
    </div>
    <a class="button" href="{{ route('admin.endorsers.create') }}">+ Add endorser</a>
</div>

<div class="filter-bar">
    <div class="tabs">
        <a class="{{ $status === '' ? 'on' : '' }}" href="{{ route('admin.endorsers.index', Arr::except($base, ['status'])) }}">
            Everyone <span class="count">{{ $total }}</span>
        </a>
        @foreach(EndorserController::STATUSES as $value => $label)
            <a class="{{ $status === $value ? 'on' : '' }}" href="{{ route('admin.endorsers.index', Arr::except($base, ['status']) + ['status' => $value]) }}">
                {{ $label }} <span class="count">{{ $statusCounts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form class="spacer" method="get" action="{{ route('admin.endorsers.index') }}">
        @foreach(Arr::except($base, ['q', 'type']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <select name="type" onchange="this.form.submit()" aria-label="Type">
            <option value="">All types</option>
            @foreach(EndorserController::TYPES as $value)
                <option value="{{ $value }}" @selected($type === $value)>{{ str($value)->title() }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Name, team, email…">
        <button class="button ghost" type="submit">Search</button>
        @if($search !== '' || $type !== '')
            <a class="edit" href="{{ route('admin.endorsers.index', Arr::except($base, ['q', 'type'])) }}">Clear</a>
        @endif
    </form>
</div>

@if($endorsers->isEmpty())
    @if($search !== '' || $status !== '' || $type !== '')
        <x-empty-state
            icon="search"
            title="Nobody matches"
            message="No endorsers fit what you have picked. Try another status or type, or clear the filters to see the whole roster."
            action-label="Clear filters"
            :action-url="route('admin.endorsers.index')" />
    @else
        <x-empty-state
            icon="people"
            title="No endorsers yet"
            message="Racers, teams, influencers, and partner organisations you work with. Add one to start tracking their PR kits and the content they owe."
            action-label="+ Add your first endorser"
            :action-url="route('admin.endorsers.create')" />
    @endif
@else
<div class="card table-card name-first" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Name</th><th>Type</th><th>Contact</th><th>This month's kit</th><th>Group chat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($endorsers as $endorser)
            @php
                $kit = $endorser->prKits->first();
                $quota = $kit?->content_quota ?? 0;
                $done = $kit ? $kit->obligations->whereIn('status', ['completed', 'waived'])->count() : 0;
                $expected = in_array($endorser->status, ['active', 'pending'], true);
            @endphp
            <tr>
                <td>
                    <span class="who">
                        <span class="pic">{{ mb_strtoupper(mb_substr($endorser->name, 0, 1)) }}</span>
                        <span>
                            <span class="nm">{{ $endorser->name }}</span>
                            @if($endorser->team_or_group)<div class="sub">{{ $endorser->team_or_group }}</div>@endif
                        </span>
                    </span>
                </td>
                <td><span class="type-tag">{{ str($endorser->type)->title() }}</span></td>
                <td class="contact">
                    @if($endorser->contact_number)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $endorser->contact_number) }}">{{ $endorser->contact_number }}</a>
                    @endif
                    @if($endorser->email)<a href="mailto:{{ $endorser->email }}">{{ $endorser->email }}</a>@endif
                    @if(! $endorser->contact_number && ! $endorser->email)<span class="muted">—</span>@endif
                </td>
                <td>
                    @if($kit)
                        <span class="kit">
                            <span class="sent">Sent {{ $kit->delivery_date->format('M j') }}</span>
                            @if($quota > 0)
                                <span class="dots" title="{{ $done }} of {{ $quota }} delivered">
                                    @for($i = 1; $i <= $quota; $i++)<i class="{{ $i <= $done ? 'done' : '' }}"></i>@endfor
                                </span>
                            @endif
                        </span>
                    @elseif($expected)
                        <span class="missing">Not sent</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td>
                    @if($endorser->group_chat_url)
                        {{-- noopener so the chat tab cannot reach back into the hub. --}}
                        <a class="chat" href="{{ $endorser->group_chat_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L7 21l.6-3.2A8.4 8.4 0 1 1 21 11.5z"/></svg>
                            Open chat
                        </a>
                    @else
                        <a class="chat-add" href="{{ route('admin.endorsers.edit', $endorser) }}">+ Add link</a>
                    @endif
                </td>
                <td><span class="pill pill-{{ $endorser->status }}">{{ str($endorser->status)->title() }}</span></td>
                <td><a class="edit" href="{{ route('admin.endorsers.edit', $endorser) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- The same roster as cards, for screens the table cannot fit. --}}
    <div class="stack-cards en-cards">
        @foreach($endorsers as $endorser)
            @php
                $kit = $endorser->prKits->first();
                $quota = $kit?->content_quota ?? 0;
                $done = $kit ? $kit->obligations->whereIn('status', ['completed', 'waived'])->count() : 0;
                $expected = in_array($endorser->status, ['active', 'pending'], true);
            @endphp
            <a class="en-card" href="{{ route('admin.endorsers.edit', $endorser) }}">
                <div class="top">
                    <span class="who">
                        <span class="pic">{{ mb_strtoupper(mb_substr($endorser->name, 0, 1)) }}</span>
                        <span>
                            <span class="nm">{{ $endorser->name }}</span>
                            <div class="sub">{{ $endorser->team_or_group ?: str($endorser->type)->title() }}</div>
                        </span>
                    </span>
                    <span class="pill pill-{{ $endorser->status }}">{{ str($endorser->status)->title() }}</span>
                </div>
                <div class="foot">
                    @if($kit)
                        <span class="kit">
                            <span class="sent">Kit sent {{ $kit->delivery_date->format('M j') }}</span>
                            @if($quota > 0)
                                <span class="dots">@for($i = 1; $i <= $quota; $i++)<i class="{{ $i <= $done ? 'done' : '' }}"></i>@endfor</span>
                            @endif
                        </span>
                    @elseif($expected)
                        <span class="missing">No kit this month</span>
                    @endif
                    @if($endorser->group_chat_url)<span class="chat-add">Chat linked</span>@endif
                </div>
            </a>
        @endforeach
    </div>
</div>
{{ $endorsers->links() }}
@endif
@endsection
