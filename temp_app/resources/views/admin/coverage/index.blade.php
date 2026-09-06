@extends('layouts.admin')
@section('title', 'Event Coverage')
@section('content')
<style>
    .none{color:#b45309;font-size:.78rem;font-weight:700;background:#fffbeb;border:1px solid #fde68a;padding:3px 9px;border-radius:999px;white-space:nowrap}
    .st{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:999px;border:1px solid;white-space:nowrap}
    .st-not_started{background:var(--line-soft);color:var(--muted);border-color:var(--line)}
    .st-editing{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .st-for_review{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .st-posted{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
    .st-not_required{background:var(--line-soft);color:var(--muted);border-color:var(--line)}

    /* A job marketing has sent that nobody has picked up. It gets a rail and a
       tinted row rather than only a pill: the point is that it should be hard
       to scroll past unanswered work. */
    tr.new-job td:first-child{box-shadow:inset 3px 0 0 var(--accent)}
    tr.new-job{background:#fffdf5}
    .cov-card.new-job{
        background:linear-gradient(#fffdf6,#fffbf0);border:1px solid #f5d98e;border-left:3px solid var(--accent);
        border-radius:13px;padding:16px 18px;margin:10px 0;
    }
    .cov-card.new-job + .cov-card.new-job{margin-top:0}
    .ask-line{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:11px}
    .flag{display:inline-flex;align-items:center;gap:6px;font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:3px 9px;border-radius:999px;white-space:nowrap}
    /* Side by side these two push the six-column table past its container and
       "Not needed" falls off the edge, so in the table they stack. The card
       view below has the width to keep them on one line. */
    .respond{display:flex;gap:6px;align-items:stretch;flex-wrap:wrap}
    .respond button{flex:1 1 auto;justify-content:center}
    .respond form{display:contents}
    .respond button{border:1px solid var(--line);background:#fff;border-radius:8px;padding:5px 10px;font:inherit;font-size:.74rem;font-weight:700;color:var(--ink-soft);cursor:pointer;white-space:nowrap}
    .respond button:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .respond button.take{background:#ecfdf5;border-color:#a7f3d0;color:#047857}
    .respond button.take:hover{background:#d1fae5;border-color:#6ee7b7}
    .respond button.take svg{width:14px;height:14px}
    .respond button{display:inline-flex;align-items:center;gap:6px}
    /* In a card the actions get room to breathe and sit last, after the ask. */
    .cov-card .respond{margin-top:12px;gap:8px}
    .cov-card .respond button{padding:8px 15px;font-size:.8rem;border-radius:10px}
    td.respond-cell{width:104px}
    .cov-card .respond{flex-wrap:nowrap}
    .cov-card .respond button{flex:0 0 auto}

    /* The team's sheet has nine columns. Kept as nine they shred every cell
       into a two-word ribbon, so the pairs that are always read together are
       kept together: a cut's status, its editor and the day it went out are one
       cell, and where the event was and what was noted about it sit under its
       name. Nothing from the sheet is dropped. */
    .ev{min-width:200px}
    .ev .nm{font-weight:650}
    .ev .sub{color:var(--muted);font-size:.79rem;margin-top:3px}
    .ev .note{color:var(--muted);font-size:.79rem;margin-top:5px;font-style:italic}

    .cut{min-width:150px}
    .cut .who{color:var(--muted);font-size:.78rem;margin-top:4px}
    .cut .on{font-size:.78rem;margin-top:3px;font-weight:650}
    .cut .on span{font-weight:600;color:var(--muted)}

    td.date{white-space:nowrap}
    td.date .d{font-weight:650}
    td.date .y{color:var(--muted);font-size:.78rem}

    /* Below this the six columns stop fitting, and each event becomes a block. */
    .cov-card{padding:16px 0;border-bottom:1px solid var(--line-soft)}
    .cov-card:last-child{border-bottom:0}
    .cov-card .top{display:flex;align-items:baseline;gap:10px;justify-content:space-between}
    .cov-card .nm{font-weight:700;font-size:.98rem;color:inherit;text-decoration:none}
    .cov-card .nm:hover{text-decoration:underline}
    .cov-card .meta{color:var(--muted);font-size:.83rem;margin-top:4px}
    .cov-card .cuts{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-top:12px}
    .cov-card .cuts > div{background:var(--canvas);border:1px solid var(--line);border-radius:12px;padding:10px 12px}
    .cov-card .lb{font-size:.66rem;letter-spacing:.11em;text-transform:uppercase;color:var(--muted);font-weight:800;margin-bottom:6px}

    @container (max-width:880px){
        .table-card table{display:none}
        .table-card .cov-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Multimedia</span>
        <h1>Event coverage</h1>
        <div class="muted small">Shooter, edits, and posting dates for every event</div>
    </div>
</div>

<div class="list-head">
    <div class="periods">
        {{-- New work first: this is the queue marketing sends into. --}}
        <a class="{{ $filter === 'requested' ? 'on' : '' }} {{ $requestedCount ? 'warn' : '' }}" href="{{ route('admin.coverage.index', ['show' => 'requested']) }}">
            New requests<span class="n">{{ $requestedCount }}</span>
        </a>
        <a class="{{ $filter === 'all' ? 'on' : '' }}" href="{{ route('admin.coverage.index') }}">All events</a>
        <a class="{{ $filter === 'outstanding' ? 'on' : '' }}" href="{{ route('admin.coverage.index', ['show' => 'outstanding']) }}">Outstanding</a>
        <a class="{{ $filter === 'unassigned' ? 'on' : '' }} {{ $unassignedCount ? 'warn' : '' }}" href="{{ route('admin.coverage.index', ['show' => 'unassigned']) }}">
            No shooter<span class="n">{{ $unassignedCount }}</span>
        </a>
    </div>
</div>

@if($events->isEmpty())
    @php
        $emptyTitle = match ($filter) {
            'requested' => 'No new requests',
            'unassigned' => 'Every event has a shooter',
            default => 'Nothing to cover yet',
        };
        $emptyMessage = match ($filter) {
            'requested' => 'Every job marketing has sent over has been picked up. New ones land here the moment an event is booked.',
            'unassigned' => 'No upcoming event is waiting for someone to shoot it.',
            default => 'Coverage is logged against events. Once marketing books one it appears here, ready for a shooter and the edit dates.',
        };
    @endphp
    <x-empty-state
        icon="check"
        :title="$emptyTitle"
        :message="$emptyMessage"
        :action-label="$filter === 'all' ? '+ Add an event' : null"
        :action-url="$filter === 'all' ? route('admin.events.create') : null" />
@else
<div class="card table-card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th>Date</th><th>Event</th><th>Shooter</th>
                <th>Photo edit</th><th>Video edit</th><th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($events as $event)
            @php
                $coverage = $event->coverage;
                $photoStatus = $coverage->photo_status ?? 'not_started';
                $videoStatus = $coverage->video_status ?? 'not_started';
                // Only a job marketing actually sent is new work. An event with
                // no coverage row was never handed over, so it stays an
                // ordinary line rather than appearing as an unanswered request.
                $waiting = $coverage?->isRequested() ?? false;
            @endphp
            <tr class="{{ $waiting ? 'new-job' : '' }}">
                <td class="date">
                    <div class="d">{{ $event->event_date->format('M j') }}</div>
                    <div class="y">{{ $event->event_date->format('Y') }}</div>
                </td>
                <td>
                    <div class="ev">
                        <div class="nm">{{ $event->name }}</div>
                        <div class="sub">
                            {{ $event->categoryLabel() }}
                            @if($event->venue) · {{ $event->venue }} @endif
                        </div>
                        @if($waiting)
                            <div style="margin-top:6px">
                                <span class="flag">New request</span>
                                @if($coverage?->requester)
                                    <span class="muted small">from {{ $coverage->requester->name }}</span>
                                @endif
                            </div>
                        @endif
                        @if($coverage?->remarks)
                            <div class="note">{{ Str::limit($coverage->remarks, 70) }}</div>
                        @endif
                    </div>
                </td>
                <td>
                    @if($coverage?->shooter)
                        {{ $coverage->shooter->name }}
                    @else
                        <span class="none">Not assigned</span>
                    @endif
                </td>
                <td>
                    <div class="cut">
                        <span class="st st-{{ $photoStatus }}">{{ App\Models\Coverage::STATUSES[$photoStatus] }}</span>
                        @if($coverage?->photoEditor)<div class="who">{{ $coverage->photoEditor->name }}</div>@endif
                        @if($coverage?->photo_posted_on)<div class="on"><span>Posted</span> {{ $coverage->photo_posted_on->format('M j, Y') }}</div>@endif
                    </div>
                </td>
                <td>
                    <div class="cut">
                        <span class="st st-{{ $videoStatus }}">{{ App\Models\Coverage::STATUSES[$videoStatus] }}</span>
                        @if($coverage?->videoEditor)<div class="who">{{ $coverage->videoEditor->name }}</div>@endif
                        @if($coverage?->video_posted_on)<div class="on"><span>Posted</span> {{ $coverage->video_posted_on->format('M j, Y') }}</div>@endif
                    </div>
                </td>
                <td class="respond-cell">
                    @if($waiting)
                        <div class="respond">
                            <form method="post" action="{{ route('admin.coverage.respond', $event) }}">@csrf
                                <input type="hidden" name="answer" value="accept">
                                <button class="take" type="submit">Take it on</button>
                            </form>
                            <form method="post" action="{{ route('admin.coverage.respond', $event) }}">@csrf
                                <input type="hidden" name="answer" value="decline">
                                <button type="submit">Not needed</button>
                            </form>
                        </div>
                    @else
                        <a class="edit" href="{{ route('admin.coverage.edit', $event) }}">{{ $coverage ? 'Edit' : 'Log' }}</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- The same rows as blocks, for a column the table cannot fit. --}}
    <div class="stack-cards cov-cards">
        @foreach($events as $event)
            @php
                $coverage = $event->coverage;
                $photoStatus = $coverage->photo_status ?? 'not_started';
                $videoStatus = $coverage->video_status ?? 'not_started';
                // Only a job marketing actually sent is new work. An event with
                // no coverage row was never handed over, so it stays an
                // ordinary line rather than appearing as an unanswered request.
                $waiting = $coverage?->isRequested() ?? false;
            @endphp
            <div class="cov-card {{ $waiting ? 'new-job' : '' }}">
                <div class="top">
                    <a class="nm" href="{{ route('admin.coverage.edit', $event) }}">{{ $event->name }}</a>
                    @if($coverage?->shooter)
                        <span class="small muted">{{ $coverage->shooter->name }}</span>
                    @elseif(! $waiting)
                        {{-- On a job nobody has taken, "no shooter" states the
                             obvious and competes with the request flag. --}}
                        <span class="none">No shooter</span>
                    @endif
                </div>
                <div class="meta">
                    {{ $event->event_date->format('M j, Y') }} · {{ $event->categoryLabel() }}
                    @if($event->venue) · {{ $event->venue }} @endif
                </div>
                @if($coverage?->remarks)
                    <div class="meta">{{ Str::limit($coverage->remarks, 90) }}</div>
                @endif

                @if($waiting)
                    {{-- An unanswered request is a question, not a production
                         record: both edit panels would only ever read "not
                         started" here, so the card is the ask and the answer. --}}
                    <div class="ask-line">
                        <span class="flag">New request</span>
                        {{-- One span, so the separator does not collect the
                             whitespace between two Blade conditionals. --}}
                        <span class="muted small">
                            @if($coverage?->requester)from {{ $coverage->requester->name }}@endif
                            @if($coverage?->requester && $coverage?->requested_at) · @endif
                            @if($coverage?->requested_at){{ $coverage->requested_at->diffForHumans() }}@endif
                        </span>
                    </div>
                    <div class="respond">
                        <form method="post" action="{{ route('admin.coverage.respond', $event) }}">@csrf
                            <input type="hidden" name="answer" value="accept">
                            <button class="take" type="submit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
                                Take it on
                            </button>
                        </form>
                        <form method="post" action="{{ route('admin.coverage.respond', $event) }}">@csrf
                            <input type="hidden" name="answer" value="decline">
                            <button type="submit">Not needed</button>
                        </form>
                    </div>
                @else
                <div class="cuts">
                    <div>
                        <div class="lb">Photo edit</div>
                        <span class="st st-{{ $photoStatus }}">{{ App\Models\Coverage::STATUSES[$photoStatus] }}</span>
                        <div class="cut">
                            @if($coverage?->photoEditor)<div class="who">{{ $coverage->photoEditor->name }}</div>@endif
                            @if($coverage?->photo_posted_on)<div class="on"><span>Posted</span> {{ $coverage->photo_posted_on->format('M j, Y') }}</div>@endif
                        </div>
                    </div>
                    <div>
                        <div class="lb">Video edit</div>
                        <span class="st st-{{ $videoStatus }}">{{ App\Models\Coverage::STATUSES[$videoStatus] }}</span>
                        <div class="cut">
                            @if($coverage?->videoEditor)<div class="who">{{ $coverage->videoEditor->name }}</div>@endif
                            @if($coverage?->video_posted_on)<div class="on"><span>Posted</span> {{ $coverage->video_posted_on->format('M j, Y') }}</div>@endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
{{ $events->links() }}
@endif
@endsection
