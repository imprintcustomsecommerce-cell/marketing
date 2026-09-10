@extends('layouts.admin')
@section('title', 'Events')
@section('content')
@php
    use App\Http\Controllers\Admin\EventController;

    // Every control keeps the others, so changing one never silently resets
    // the rest of the view.
    $base = array_filter([
        'when' => $period === 'upcoming' ? null : $period,
        'category' => $category ?: null,
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

    .cat{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid;white-space:nowrap}
    /* "External Event Sponsorship" as one unbreakable pill made the Type column
       wider than the event name itself and pushed the last column off the edge.
       In the table it wraps; everywhere it has room it stays on one line. */
    table .cat{white-space:normal;max-width:128px;line-height:1.3;text-align:center}
    .cat-tambike{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .cat-function_hall{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
    .cat-external_sponsorship{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .cat-in_house{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
    .cat-outside_event{background:#fff7ed;color:#c2410c;border-color:#fed7aa}
    .cat-unset{background:#f8fafc;color:#64748b;border-color:#e2e8f0}

    .when{white-space:nowrap}
    .when .d{font-weight:650}
    .when .rel{font-size:.76rem;color:var(--muted)}
    .when .soon{color:#b45309;font-weight:700}

    /* Whether a shooter is booked belongs next to the date: it is the thing
       that has to be sorted before the day arrives. */
    .shoot{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;white-space:nowrap}
    .shoot .dot{width:8px;height:8px;border-radius:50%;flex:none}
    .shoot.yes .dot{background:#10b981}
    .shoot.no .dot{background:#e5e0d5}
    .shoot.no{color:var(--muted)}
    /* Where the handoff to multimedia stands, so marketing can see whether the
       crew have picked a job up without leaving this screen. */
    .shoot.waiting{color:#92400e;font-weight:700}
    .shoot.waiting .dot{background:var(--accent)}
    /* Compact on purpose: this is a nine-column table and the button was
       widening the coverage column enough to push "Edit" off the edge. */
    .ask{border:1px solid var(--line);background:#fff;border-radius:7px;padding:3px 8px;font:inherit;font-size:.7rem;font-weight:700;color:var(--ink-soft);cursor:pointer;white-space:nowrap}
    .ask:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}

    .chat{display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:.8rem;font-weight:700;color:var(--accent-ink);background:var(--accent-bg);border:1px solid #fde68a;padding:5px 11px;border-radius:999px;white-space:nowrap}
    table .chat{padding:6px;border-radius:9px}
    table .chat span{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
    .chat:hover{border-color:var(--accent);background:#fef3c7}
    .chat svg{width:14px;height:14px}
    .chat-add{font-size:.8rem;color:var(--muted);text-decoration:none}
    .chat-add:hover{color:var(--accent-ink);text-decoration:underline}

    /* A seven-column table does not survive a phone, so it becomes cards. */
    .ev-card{padding:15px 0;border-bottom:1px solid var(--line-soft)}
    .ev-card:last-child{border-bottom:0}
    .ev-card .top{display:flex;align-items:center;gap:10px;justify-content:space-between}
    .ev-card .nm{font-weight:700;font-size:.98rem;color:inherit;text-decoration:none}
    .ev-card .nm:hover{text-decoration:underline}
    .ev-card .meta{color:var(--muted);font-size:.83rem;margin-top:4px}
    .ev-card .foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}
    .row-actions{display:inline-flex;gap:10px;align-items:center;white-space:nowrap}
    .row-actions form{display:inline-flex}
    .row-actions .link-btn{border:0;background:none;padding:0;font:inherit;font-size:.84rem;
        font-weight:650;cursor:pointer;color:var(--ink-soft);min-height:0}
    .row-actions .link-btn:hover{text-decoration:underline}
    .row-actions .link-btn.danger{color:#b91c1c}

    @container (max-width:900px){
        .table-card table{display:none}
        .table-card .ev-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Marketing calendar</span>
        <h1>Events</h1>
        <div class="muted small">
            @if($search !== '')
                {{ $events->total() }} {{ Str::plural('result', $events->total()) }} for “{{ $search }}”
            @else
                Ride-outs, hall bookings, and sponsored events
            @endif
        </div>
    </div>
    <a class="button" href="{{ route('admin.events.create') }}">+ Add event</a>
</div>

<div class="list-head">
    <div class="periods">
        @foreach(EventController::PERIODS as $value => $label)
            <a class="{{ $period === $value ? 'on' : '' }}"
               href="{{ route('admin.events.index', array_filter(Arr::except($base, ['when']) + ['when' => $value === 'upcoming' ? null : $value])) }}">
                {{ $label }}<span class="n">{{ $periodCounts[$value] }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.events.index') }}">
        @foreach(Arr::except($base, ['q']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input type="search" name="q" value="{{ $search }}" placeholder="Name, organiser, venue…">
        <button class="button ghost" type="submit">Search</button>
        @if($search !== '')
            <a class="edit" href="{{ route('admin.events.index', Arr::except($base, ['q'])) }}">Clear</a>
        @endif
    </form>
</div>

<div class="filter-bar">
    <div class="tabs">
        <a class="{{ $category === '' ? 'on' : '' }}" href="{{ route('admin.events.index', Arr::except($base, ['category'])) }}">
            All types <span class="count">{{ $counts->sum() }}</span>
        </a>
        @foreach(App\Models\Event::EVENT_TYPES as $value => $label)
            <a class="{{ $category === $value ? 'on' : '' }}" href="{{ route('admin.events.index', Arr::except($base, ['category']) + ['category' => $value]) }}">
                {{ $label }} <span class="count">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>
</div>

@if($events->isEmpty())
    @if($search !== '' || $category !== '' || $period === 'past')
        <x-empty-state
            icon="search"
            title="Nothing here"
            message="No events match what you have picked. Try another type or period, or clear the filters to see everything."
            action-label="Clear filters"
            :action-url="route('admin.events.index', ['when' => 'all'])" />
    @else
        <x-empty-state
            icon="calendar"
            title="No events yet"
            message="Ride-outs, hall bookings, and expo sponsorships all live here. Add one, or convert an inquiry that came in through the public forms."
            action-label="+ Add your first event"
            :action-url="route('admin.events.create')"
            secondary-label="Read inquiries"
            :secondary-url="route('admin.inquiries.index')" />
    @endif
@else
<div class="card table-card name-first" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Event</th><th>Type</th><th>Date</th><th>Venue</th><th>Guests</th><th>Coverage</th><th>Chat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($events as $event)
            @php
                $days = today()->diffInDays($event->event_date, false);
                $shooter = $event->coverage?->shooter;
                // No coverage row means nobody was ever asked — an event from
                // before the handoff existed. Calling that "with multimedia"
                // would claim a request that was never sent, so it gets the
                // button instead.
                $waiting = $event->coverage?->isRequested() ?? false;
                $declined = $event->coverage?->stage === App\Support\CoverageDesk::DECLINED;
                $neverSent = $event->coverage === null;
            @endphp
            <tr>
                <td><a class="nm" href="{{ route('admin.events.show', $event) }}"><strong>{{ $event->name }}</strong></a><div class="muted small">{{ $event->organization }}</div></td>
                <td><span class="cat cat-{{ $event->event_type ?: 'unset' }}">{{ $event->eventTypeLabel() }}</span></td>
                <td class="when">
                    <div class="d">{{ $event->event_date->format('M j, Y') }}</div>
                    <div class="rel {{ $days >= 0 && $days <= 7 ? 'soon' : '' }}">
                        {{ $days === 0 ? 'Today' : ($days > 0 ? 'in '.$days.' '.Str::plural('day', $days) : $event->event_date->diffForHumans()) }}
                    </div>
                </td>
                <td>{{ $event->venue ?: '—' }}</td>
                <td>{{ $event->estimated_pax ?: '—' }}</td>
                <td>
                    @if($shooter)
                        <span class="shoot yes"><span class="dot"></span>{{ $shooter->name }}</span>
                    @elseif($waiting)
                        <span class="shoot waiting"><span class="dot"></span>With multimedia</span>
                    @else
                        <span class="shoot no"><span class="dot"></span>{{ $declined ? 'Not covering' : 'No shooter' }}</span>
                        @if($declined || $neverSent)
                            <form method="post" action="{{ route('admin.events.request-coverage', $event) }}" style="margin-top:6px">@csrf
                                <button class="ask" type="submit">{{ $declined ? 'Ask again' : 'Send to crew' }}</button>
                            </form>
                        @endif
                    @endif
                </td>
                <td>
                    @if($event->group_chat_url)
                        {{-- noopener so the chat tab cannot reach back into the hub. --}}
                        <a class="chat" href="{{ $event->group_chat_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L7 21l.6-3.2A8.4 8.4 0 1 1 21 11.5z"/></svg>
                            <span>Open chat</span>
                        </a>
                    @else
                        <a class="chat-add" href="{{ route('admin.events.edit', $event) }}">+ Add link</a>
                    @endif
                </td>
                <td><span class="pill pill-{{ $event->status }}">{{ str($event->status)->title() }}</span></td>
                <td>
                    <div class="row-actions">
                        <a class="edit" href="{{ route('admin.events.edit', $event) }}">Edit</a>
                        @include('admin.events.row-actions', ['event' => $event])
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- The same rows as cards, for screens the table cannot fit. --}}
    <div class="stack-cards ev-cards">
        @foreach($events as $event)
            @php
                $days = today()->diffInDays($event->event_date, false);
                $shooter = $event->coverage?->shooter;
                // No coverage row means nobody was ever asked — an event from
                // before the handoff existed. Calling that "with multimedia"
                // would claim a request that was never sent, so it gets the
                // button instead.
                $waiting = $event->coverage?->isRequested() ?? false;
                $declined = $event->coverage?->stage === App\Support\CoverageDesk::DECLINED;
                $neverSent = $event->coverage === null;
            @endphp
            {{-- The card is not itself a link: the group chat button lives inside
                 it, and an anchor cannot be nested in another anchor. --}}
            <div class="ev-card">
                <div class="top">
                    <a class="nm" href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a>
                    <span class="pill pill-{{ $event->status }}">{{ str($event->status)->title() }}</span>
                </div>
                <div class="meta">
                    {{ $event->event_date->format('M j, Y') }}
                    · {{ $days === 0 ? 'Today' : ($days > 0 ? 'in '.$days.' '.Str::plural('day', $days) : $event->event_date->diffForHumans()) }}
                    @if($event->venue) · {{ $event->venue }} @endif
                </div>
                <div class="foot">
                    <span class="cat cat-{{ $event->event_type ?: 'unset' }}">{{ $event->eventTypeLabel() }}</span>
                    @if($shooter)
                        <span class="shoot yes"><span class="dot"></span>{{ $shooter->name }}</span>
                    @elseif($waiting)
                        <span class="shoot waiting"><span class="dot"></span>With multimedia</span>
                    @else
                        <span class="shoot no"><span class="dot"></span>{{ $declined ? 'Not covering' : 'No shooter' }}</span>
                        @if($declined || $neverSent)
                            <form method="post" action="{{ route('admin.events.request-coverage', $event) }}">@csrf
                                <button class="ask" type="submit">{{ $declined ? 'Ask again' : 'Send to crew' }}</button>
                            </form>
                        @endif
                    @endif
                    @if($event->group_chat_url)
                        <a class="chat" href="{{ $event->group_chat_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L7 21l.6-3.2A8.4 8.4 0 1 1 21 11.5z"/></svg>
                            Open chat
                        </a>
                    @endif
                    <span class="row-actions" style="margin-left:auto">
                        <a class="edit" href="{{ route('admin.events.edit', $event) }}">Edit</a>
                        @include('admin.events.row-actions', ['event' => $event])
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</div>
{{ $events->links() }}
@endif
@endsection
