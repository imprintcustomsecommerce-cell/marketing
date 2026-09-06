@extends('layouts.admin')
@section('title', 'Obligations')
@section('content')
@php
    use App\Http\Controllers\Admin\ObligationController;

    $base = array_filter([
        'show' => $filter === 'open' ? null : $filter,
        'endorser' => $endorserId ?: null,
        'q' => $search !== '' ? $search : null,
    ]);
@endphp
<style>


    .title .nm{font-weight:650}
    .title .from{font-size:.77rem;color:var(--muted);margin-top:3px}
    .title .from a{color:var(--accent-ink);text-decoration:none}
    .title .from a:hover{text-decoration:underline}

    .type-tag{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid var(--line);background:#faf8f4;color:var(--ink-soft);white-space:nowrap}

    .due{white-space:nowrap}
    .due .d{font-weight:650;font-size:.87rem}
    .due .rel{font-size:.77rem;color:var(--muted)}
    .due.soon .rel{color:#b45309;font-weight:700}
    .due.late .d,.due.late .rel{color:#b91c1c;font-weight:700}

    /* Chasing content is mostly small status changes, so the common ones are
       one click from the list rather than a trip through the form. */
    /* Both quick actions belong on one line: stacked, they double the height of
       every row and the list stops reading as a list. */
    .quick{display:flex;gap:6px;align-items:center;flex-wrap:nowrap}
    td.quick-cell{width:1%;white-space:nowrap}
    /* A person's name is not a phrase to be wrapped; the title column has the
       slack to give. */
    table td:nth-child(2){white-space:nowrap}
    .quick form{display:contents}
    .quick button{border:1px solid var(--line);background:#fff;border-radius:8px;padding:5px 10px;font:inherit;font-size:.74rem;font-weight:700;color:var(--ink-soft);cursor:pointer;white-space:nowrap}
    .quick button:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .quick button.go{background:#ecfdf5;border-color:#a7f3d0;color:#047857}
    .quick button.go:hover{background:#d1fae5}
    .proof{font-size:.76rem;color:var(--accent-ink);text-decoration:none}
    .proof:hover{text-decoration:underline}

    .ob-card{padding:15px 0;border-bottom:1px solid var(--line-soft)}
    .ob-card:last-child{border-bottom:0}
    .ob-card .top{display:flex;align-items:flex-start;gap:10px;justify-content:space-between}
    .ob-card .foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}

    @container (max-width:900px){
        .table-card table{display:none}
        .table-card .ob-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Endorser commitments</span>
        <h1>Obligations</h1>
        <div class="muted small">
            @if($search !== '')
                {{ $obligations->total() }} {{ Str::plural('result', $obligations->total()) }} for “{{ $search }}”
            @else
                What each endorser owes us, and when it is due
            @endif
        </div>
    </div>
    <a class="button" href="{{ route('admin.obligations.create') }}">+ Add obligation</a>
</div>

<div class="list-head">
    <div class="periods">
        @foreach(ObligationController::VIEWS as $value => $label)
            <a class="{{ $filter === $value ? 'on' : '' }} {{ $value === 'overdue' ? 'warn' : '' }}"
               href="{{ route('admin.obligations.index', array_filter(Arr::except($base, ['show']) + ['show' => $value === 'open' ? null : $value])) }}">
                {{ $label }}<span class="n">{{ $counts[$value] }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.obligations.index') }}">
        @foreach(Arr::except($base, ['endorser', 'q']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <select name="endorser" onchange="this.form.submit()" aria-label="Endorser">
            <option value="">All endorsers</option>
            @foreach($endorsers as $id => $name)
                <option value="{{ $id }}" @selected((string) $endorserId === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search obligations…">
        <button class="button ghost" type="submit">Search</button>
        @if($search !== '' || $endorserId !== '')
            <a class="edit" href="{{ route('admin.obligations.index', Arr::except($base, ['endorser', 'q'])) }}">Clear</a>
        @endif
    </form>
</div>

@if($obligations->isEmpty())
    @if($filter === 'overdue')
        <x-empty-state
            icon="check"
            title="Nothing is overdue"
            message="Every piece of content that has come due has been delivered or waived. Nothing to chase."
            action-label="See what is open"
            :action-url="route('admin.obligations.index')" />
    @elseif($search !== '' || $endorserId !== '' || $filter !== 'open')
        <x-empty-state
            icon="search"
            title="Nothing here"
            message="No obligations match what you have picked. Try another view, or clear the filters."
            action-label="Clear filters"
            :action-url="route('admin.obligations.index', ['show' => 'all'])" />
    @else
        <x-empty-state
            icon="check"
            title="Nothing outstanding"
            message="Obligations appear here when you record a delivered PR kit, or when you add one by hand for an appearance or a race entry."
            action-label="+ Add an obligation"
            :action-url="route('admin.obligations.create')"
            secondary-label="Record a PR kit"
            :secondary-url="route('admin.pr-kits.create')" />
    @endif
@else
<div class="card table-card name-first" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Obligation</th><th>Endorser</th><th>Type</th><th>Due</th><th>Status</th><th>Move it on</th><th></th></tr></thead>
        <tbody>
        @foreach($obligations as $obligation)
            @php
                $late = $obligation->isOverdue();
                $days = today()->diffInDays($obligation->due_date, false);
                $soon = ! $late && $days >= 0 && $days <= 7;
                $settled = in_array($obligation->status, ['completed', 'waived'], true);
            @endphp
            <tr>
                <td class="title">
                    <div class="nm">{{ $obligation->title }}</div>
                    <div class="from">
                        @if($obligation->prKit)
                            From <a href="{{ route('admin.pr-kits.edit', $obligation->prKit) }}">{{ $obligation->prKit->reference ?: 'the PR kit' }}</a>
                        @elseif($obligation->event)
                            {{ $obligation->event->name }}
                        @endif
                        @if($obligation->proof_url)
                            <a class="proof" href="{{ $obligation->proof_url }}" target="_blank" rel="noopener noreferrer">· View proof</a>
                        @endif
                    </div>
                </td>
                <td>{{ $obligation->endorser?->name ?: '—' }}</td>
                <td><span class="type-tag">{{ str($obligation->type)->replace('_', ' ')->title() }}</span></td>
                <td class="due {{ $late ? 'late' : ($soon ? 'soon' : '') }}">
                    <div class="d">{{ $obligation->due_date->format('M j, Y') }}</div>
                    <div class="rel">
                        @if($settled)
                            {{ $obligation->completed_on ? 'Done '.$obligation->completed_on->format('M j') : 'Done' }}
                        @elseif($days === 0)
                            Due today
                        @elseif($days > 0)
                            in {{ $days }} {{ Str::plural('day', $days) }}
                        @else
                            {{ $obligation->due_date->diffForHumans() }}
                        @endif
                    </div>
                </td>
                <td><span class="pill pill-{{ $late ? 'missed' : $obligation->status }}">{{ $late ? 'Overdue' : str($obligation->status)->title() }}</span></td>
                <td class="quick-cell">
                    <div class="quick">
                        @if($obligation->status === 'pending')
                            <form method="post" action="{{ route('admin.obligations.status', $obligation) }}">@csrf @method('patch')
                                <input type="hidden" name="status" value="submitted">
                                <button type="submit">Submitted</button>
                            </form>
                        @endif
                        @unless($settled)
                            <form method="post" action="{{ route('admin.obligations.status', $obligation) }}">@csrf @method('patch')
                                <input type="hidden" name="status" value="completed">
                                <button class="go" type="submit">Mark delivered</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.obligations.status', $obligation) }}">@csrf @method('patch')
                                <input type="hidden" name="status" value="pending">
                                <button type="submit">Reopen</button>
                            </form>
                        @endunless
                    </div>
                </td>
                <td><a class="edit" href="{{ route('admin.obligations.edit', $obligation) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- The same list as cards, for screens the table cannot fit. --}}
    <div class="stack-cards ob-cards">
        @foreach($obligations as $obligation)
            @php
                $late = $obligation->isOverdue();
                $days = today()->diffInDays($obligation->due_date, false);
                $settled = in_array($obligation->status, ['completed', 'waived'], true);
            @endphp
            <div class="ob-card">
                <div class="top">
                    <span>
                        <a class="nm" href="{{ route('admin.obligations.edit', $obligation) }}" style="text-decoration:none;font-weight:700">{{ $obligation->title }}</a>
                        <div class="muted small" style="margin-top:3px">{{ $obligation->endorser?->name ?: 'Endorser removed' }}</div>
                    </span>
                    <span class="pill pill-{{ $late ? 'missed' : $obligation->status }}">{{ $late ? 'Overdue' : str($obligation->status)->title() }}</span>
                </div>
                <div class="muted small" style="margin-top:6px">
                    Due {{ $obligation->due_date->format('M j, Y') }}
                    @if(! $settled) · {{ $days === 0 ? 'today' : ($days > 0 ? 'in '.$days.' '.Str::plural('day', $days) : $obligation->due_date->diffForHumans()) }} @endif
                </div>
                <div class="foot quick">
                    @unless($settled)
                        <form method="post" action="{{ route('admin.obligations.status', $obligation) }}">@csrf @method('patch')
                            <input type="hidden" name="status" value="completed">
                            <button class="go" type="submit">Mark delivered</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.obligations.status', $obligation) }}">@csrf @method('patch')
                            <input type="hidden" name="status" value="pending">
                            <button type="submit">Reopen</button>
                        </form>
                    @endunless
                    @if($obligation->proof_url)
                        <a class="proof" href="{{ $obligation->proof_url }}" target="_blank" rel="noopener noreferrer">View proof</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
{{ $obligations->links() }}
@endif
@endsection
