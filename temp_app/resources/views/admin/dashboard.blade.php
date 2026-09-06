@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<style>
    /* ------------------------------------------------- needs attention strip */
    /* The first thing on the page is what will go wrong if nobody looks at it.
       Anything already clear is left out rather than shown as a zero. */
    .attention{display:grid;grid-template-columns:repeat(auto-fit,minmax(232px,1fr));gap:12px;margin-bottom:22px}
    .att{display:flex;align-items:center;gap:12px;padding:13px 15px;border-radius:14px;text-decoration:none;border:1px solid;transition:transform .14s,box-shadow .14s}
    .att:hover{transform:translateY(-2px);box-shadow:0 10px 22px -16px #10182899}
    .att .n{font-size:1.25rem;font-weight:800;line-height:1;flex:none;min-width:24px}
    .att .t{font-size:.85rem;font-weight:600;line-height:1.35}
    .att .go{margin-left:auto;opacity:.5}
    .att .go svg{width:16px;height:16px;display:block}
    .att-danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}
    .att-warn{background:#fffbeb;border-color:#fde68a;color:#92400e}
    .att-info{background:#eff6ff;border-color:#bfdbfe;color:#1e40af}
    .all-clear{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:.9rem;font-weight:600;margin-bottom:22px}
    .all-clear svg{width:20px;height:20px;flex:none}

    /* ------------------------------------------------------------- main grid */
    .grid-2{display:grid;grid-template-columns:1.55fr 1fr;gap:18px;align-items:start}
    @media(max-width:1080px){.grid-2{grid-template-columns:1fr}}
    .stack{display:grid;gap:18px}

    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .panel-head h2{font-size:1.02rem}

    /* Upcoming events as countdown cards: the date matters more than the row. */
    /* Two across, so the three countdowns and the add tile make a filled 2x2
       block instead of a row of three with one stranded beneath it. */
    .rail{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:14px}
    .lead-rail{margin-bottom:18px}
    .ev{position:relative;display:block;text-decoration:none;color:#fff;border-radius:15px;padding:16px;min-height:142px;overflow:hidden;background:linear-gradient(160deg,#1f2937,#0b1020);box-shadow:0 12px 26px -20px #10182899;transition:transform .15s}
    .ev:hover{transform:translateY(-3px)}
    .ev::before{content:"";position:absolute;inset:0;background:radial-gradient(120% 90% at 100% 0%,#f59e0b4d,transparent 62%)}
    .ev > *{position:relative}
    .ev-days{font-size:1.7rem;font-weight:800;letter-spacing:-.03em;line-height:1}
    .ev-days-cap{font-size:.64rem;letter-spacing:.12em;text-transform:uppercase;color:#fcd34d;margin-left:7px}
    .ev-body{position:absolute;left:16px;right:16px;bottom:14px}
    .ev-name{font-weight:750;font-size:.95rem;margin-bottom:2px;line-height:1.3}
    .ev-meta{font-size:.75rem;color:#cbd5e1}
    .ev-add{display:grid;place-items:center;background:#fff;border:2px dashed #e8e0cf;color:var(--muted);min-height:142px;border-radius:15px;text-decoration:none;font-weight:700;font-size:.88rem;text-align:center;padding:16px}
    .ev-add:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}

    /* Today's own board, so the dashboard opens on work and not on totals. */
    .today-row{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--line-soft);font-size:.9rem}
    .today-row:last-of-type{border-bottom:0}
    .today-row .dot{width:10px;height:10px;border-radius:50%;flex:none;border:2px solid #cbd5e1}
    .today-row .dot.doing{border-color:var(--accent);background:#fef3c7}
    .today-row .dot.done{border-color:#10b981;background:#10b981}
    .today-row.done span{color:var(--muted);text-decoration:line-through}
    .production-row{padding:13px 0;border-bottom:1px solid var(--line-soft)}.production-row:last-child{border-bottom:0;padding-bottom:0}
    .production-top{display:flex;align-items:center;justify-content:space-between;gap:12px}.production-name{font-weight:750;text-decoration:none;font-size:.9rem}.production-name:hover{text-decoration:underline}
    .production-percent{font-size:.76rem;font-weight:800;color:var(--accent-ink)}.production-bar{height:6px;border-radius:99px;background:#f0ede7;overflow:hidden;margin:8px 0 6px}.production-bar span{display:block;height:100%;background:var(--accent);border-radius:inherit}
    .production-meta{display:flex;gap:8px 14px;flex-wrap:wrap;color:var(--muted);font-size:.74rem}.production-meta .late{color:#b91c1c;font-weight:750}
    .today-bar{height:7px;border-radius:999px;background:var(--line-soft);overflow:hidden;margin-bottom:12px}
    .today-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--accent),#10b981)}

    /* Small numbers, kept secondary to the work above them. */
    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .metric-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
    .metric-strip .stat{margin:0}
    @media(max-width:900px){.metric-strip{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:520px){.metric-strip{grid-template-columns:1fr}}
    @media(max-width:640px){.stats{grid-template-columns:1fr}}
    .stat{display:flex;align-items:center;gap:13px;margin:0;padding:16px}
    .stat-chip{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--accent-bg);color:var(--accent-ink);flex:none}
    .stat-chip svg{width:19px;height:19px}
    .stat strong{font-size:1.5rem;display:block;line-height:1.05;letter-spacing:-.03em}
    .stat .cap{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;font-weight:700}

    .row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line-soft)}
    .row:last-child{border-bottom:0}
    .row .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);flex:none}
    .row .nm{font-weight:650;font-size:.89rem;text-decoration:none}
    .row .meta{font-size:.77rem;color:var(--muted)}
    .row .when{margin-left:auto;font-size:.77rem;color:var(--muted);white-space:nowrap}

    .links{list-style:none;margin:0;padding:0;display:grid;gap:8px}
    .links li{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:11px;background:#faf8f4}
    .links .nm{font-weight:650;font-size:.86rem}
    .links .pt{font-size:.74rem;color:var(--muted);font-family:ui-monospace,Consolas,monospace}
    .links button{margin-left:auto;background:#fff;border:1px solid var(--line);color:var(--ink-soft);border-radius:8px;padding:6px 11px;font:inherit;font-size:.75rem;font-weight:700;cursor:pointer}
    .links button:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .share-panel{width:100%;margin:0;height:auto!important;min-height:0;padding:24px}
    .dashboard-lead{align-items:stretch;margin-bottom:18px}
    .dashboard-lead>.card{margin-bottom:0}
    .share-panel .links{grid-template-columns:repeat(2,minmax(0,1fr))}
    .share-panel .primary-links{grid-template-columns:1fr}
    .share-panel .primary-links li:last-child{grid-column:auto;width:100%}
    .share-heading{display:flex;align-items:center;gap:13px;margin-bottom:18px}
    .share-heading h2{margin:0}
    .share-icon{width:42px;height:42px;display:grid;place-items:center;flex:none;border-radius:12px;background:var(--accent-bg);color:var(--accent-ink);border:1px solid #fde68a}
    .share-icon svg{width:20px;height:20px}
    .share-copy{margin:3px 0 0;color:var(--muted);font-size:.82rem}
    .temporary-note{display:flex;align-items:center;gap:9px 14px;flex-wrap:wrap;margin:-5px 0 13px;padding:9px 12px;border:1px solid #fde68a;background:#fffbeb;border-radius:10px;color:#92400e;font-size:.76rem}.temporary-note strong{font-weight:800}.temporary-note span{color:#6b6559}.temporary-note time{margin-left:auto;color:#92400e;font-weight:700}
    .share-link-box{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:2px 14px;align-items:center;background:#faf8f4;border:1px solid #e8e1d6;border-radius:14px;padding:13px 14px 13px 16px}
    .share-link-label{font-size:.72rem;font-weight:800;color:var(--ink);text-transform:uppercase;letter-spacing:.06em}
    .share-link-box>a{grid-column:1;min-width:0;color:#6b6559;font-family:ui-monospace,Consolas,monospace;font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .share-link-box>button{grid-column:2;grid-row:1/3;display:inline-flex;align-items:center;gap:7px;background:var(--ink);color:#fff;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-size:.78rem;font-weight:750;cursor:pointer}
    .share-link-box>button:hover{background:#000}.share-link-box>button svg{width:15px;height:15px}
    .share-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.share-actions a,.share-actions button{display:inline-flex;align-items:center;background:#fff;color:var(--ink-soft);border:1px solid var(--line);border-radius:9px;padding:7px 11px;text-decoration:none;font:inherit;font-size:.74rem;font-weight:700;cursor:pointer}.share-actions a:hover,.share-actions button:hover{border-color:var(--accent);background:var(--accent-bg);color:var(--accent-ink)}
    .direct-links{margin-top:13px;border-top:1px solid var(--line-soft);padding-top:12px}
    .direct-links summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;color:var(--muted);font-size:.78rem;list-style:none}
    .direct-links summary::-webkit-details-marker{display:none}.direct-links summary strong{color:var(--accent-ink)}
    .direct-links[open] summary{margin-bottom:10px}
    .share-panel .links li:last-child:nth-child(odd){grid-column:1/-1;width:calc(50% - 4px);justify-self:center}
    .share-panel .links li>span{min-width:0}
    .share-panel .links .pt{overflow-wrap:anywhere}
    @media(max-width:760px){
        .share-panel .links{grid-template-columns:1fr}
        .share-panel .links li:last-child:nth-child(odd){grid-column:auto;width:100%}
        .share-heading{align-items:flex-start;flex-wrap:wrap}.share-copy{max-width:32ch}
        .share-link-box{grid-template-columns:minmax(0,1fr)}.share-link-box>button{grid-column:1;grid-row:auto;margin-top:9px;justify-content:center}.share-link-box>a{white-space:normal;overflow-wrap:anywhere}
        .temporary-note time{margin-left:0;width:100%}
    }
    @media(max-width:1080px){.dashboard-lead{grid-template-columns:1fr}.share-panel{width:100%}}
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Operations overview · {{ now()->format('l, M j') }}</span>
        <h1>Dashboard</h1>
        <div class="muted small">Marketing records at a glance</div>
    </div>
    <div style="display:flex;gap:8px"><button class="button ghost" id="customize-dashboard" type="button">Customize</button><a class="button" href="{{ route('admin.events.create') }}">+ New event</a></div>
</div>

@if($eventCount === 0 && $endorserCount === 0 && $newInquiryCount === 0)
    {{-- Nothing exists yet: skip the panels and point at the first step. --}}
    <x-empty-state
        icon="calendar"
        title="Welcome to Imprint Hub"
        message="Nothing is recorded yet. Add your first event or endorser, and public inquiries will start landing here as they come in."
        action-label="+ Add your first event"
        :action-url="route('admin.events.create')"
        secondary-label="Add an endorser"
        :secondary-url="route('admin.endorsers.create')" />
@else

    @if($attention->isNotEmpty())
        <div class="attention">
            @foreach($attention as $item)
                <a class="att att-{{ $item['tone'] }}" href="{{ $item['url'] }}">
                    <span class="n">{{ $item['count'] }}</span>
                    <span class="t">{{ Str::after($item['label'], $item['count'].' ') }}</span>
                    <span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 6 6 6-6 6"/></svg></span>
                </a>
            @endforeach
        </div>
    @else
        <div class="all-clear">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
            Nothing needs chasing right now — no unread inquiries, no overdue content, and no gaps in this month's kits.
        </div>
    @endif

    <div class="metric-strip">
        <div class="card stat"><span><strong>{{ $monthlyMetrics['received'] }}</strong><span class="cap">Inquiries this month</span></span></div>
        <div class="card stat"><span><strong>{{ $monthlyMetrics['response'] }}</strong><span class="cap">Average response</span></span></div>
        <div class="card stat"><span><strong>{{ $monthlyMetrics['conversion'] }}</strong><span class="cap">Conversion rate</span></span></div>
        <div class="card stat"><span><strong>{{ $monthlyMetrics['partnerships'] }}</strong><span class="cap">New partnerships</span></span></div>
    </div>

    <div class="grid-2 dashboard-lead">
        @include('admin.partials.client-links')
        @include('admin.partials.latest-inquiries')
    </div>

    {{-- Full width: the countdown rail is a horizontal row, and squeezed
         into a 664px column it wrapped 2x2 while leaving that column short. --}}
    <div class="stack lead-rail">
        @if($upcomingEvents->isNotEmpty())
            <section class="card">
                <div class="panel-head">
                    <h2>Coming up</h2>
                    <a class="button ghost" href="{{ route('admin.calendar') }}">Open calendar</a>
                </div>
                <div class="rail">
                    @foreach($upcomingEvents->take(3) as $event)
                        @php $days = today()->diffInDays($event->event_date, false); @endphp
                        <a class="ev" href="{{ route('admin.events.edit', $event) }}">
                            <div>
                                <span class="ev-days">{{ max($days, 0) }}</span>
                                <span class="ev-days-cap">{{ $days === 0 ? 'Today' : 'Days to go' }}</span>
                            </div>
                            <div class="ev-body">
                                <div class="ev-name">{{ $event->name }}</div>
                                <div class="ev-meta">{{ $event->event_date->format('M j') }} · {{ $event->venue ?: 'Venue not set' }}</div>
                            </div>
                        </a>
                    @endforeach
                    <a class="ev-add" href="{{ route('admin.events.create') }}">+ Add event</a>
                </div>
            </section>
        @endif
    </div>

    <div class="grid-2">
        <div class="stack">
            <section class="card">
                <div class="panel-head">
                    <h2>Your day</h2>
                    <a class="button ghost" href="{{ route('admin.tasks.index') }}">Open board</a>
                </div>

                @if($myTasks->isEmpty())
                    <p class="muted small" style="margin:0">Nothing on your board for today. <a class="edit" href="{{ route('admin.tasks.index') }}">Add a task</a>.</p>
                @else
                    @php $done = $myTasks->where('status', 'done')->count(); @endphp
                    <div class="today-bar"><span style="width:{{ (int) round($done / max($myTasks->count(), 1) * 100) }}%"></span></div>
                    @foreach($myTasks->take(5) as $task)
                        <div class="today-row {{ $task->status }}">
                            <span class="dot {{ $task->status }}"></span>
                            <span>{{ $task->title }}</span>
                        </div>
                    @endforeach
                    @if($myTasks->count() > 5)
                        <div class="muted small" style="margin-top:10px">+{{ $myTasks->count() - 5 }} more on your board</div>
                    @endif
                @endif
            </section>

            <div class="stats">
                <div class="card stat">
                    <span class="stat-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span>
                    <span><strong>{{ $eventCount }}</strong><span class="cap">Events</span></span>
                </div>
                <div class="card stat">
                    <span class="stat-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 11.5a3 3 0 1 0-2-5.3"/></svg></span>
                    <span><strong>{{ $endorserCount }}</strong><span class="cap">Endorsers</span></span>
                </div>
                <div class="card stat">
                    <span class="stat-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v12H7l-3 3z"/></svg></span>
                    <span><strong>{{ $newInquiryCount }}</strong><span class="cap">New inquiries</span></span>
                </div>
            </div>

            @if($productionEvents->isNotEmpty())
                <section class="card">
                    <div class="panel-head">
                        <div><h2>Production progress</h2><div class="muted small">Active Multimedia deliverables</div></div>
                        <a class="button ghost" href="{{ auth()->user()->canSeeMultimedia() ? route('admin.coverage.index', ['show' => 'outstanding']) : route('admin.events.index', ['when' => 'upcoming']) }}">View all</a>
                    </div>
                    @foreach($productionEvents as $event)
                        @php
                            $coverage = $event->coverage;
                            $checked = count($coverage->checklist ?? []);
                            $total = count(App\Models\Coverage::CHECKLIST);
                            $percent = (int) round($checked / max($total, 1) * 100);
                            $crew = collect([$coverage->shooter?->name, $coverage->photoEditor?->name, $coverage->videoEditor?->name, $coverage->accepter?->name])->filter()->unique()->join(', ');
                            $photoLate = $coverage->photo_due_on?->lt(today()) && !in_array($coverage->photo_status, ['posted', 'not_required'], true);
                            $videoLate = $coverage->video_due_on?->lt(today()) && !in_array($coverage->video_status, ['posted', 'not_required'], true);
                        @endphp
                        <div class="production-row">
                            <div class="production-top"><a class="production-name" href="{{ auth()->user()->canSeeMultimedia() ? route('admin.coverage.edit', $event) : route('admin.events.show', $event) }}">{{ $event->name }}</a><span class="production-percent">{{ $percent }}%</span></div>
                            <div class="production-bar"><span style="width:{{ $percent }}%"></span></div>
                            <div class="production-meta">
                                <span>{{ $checked }}/{{ $total }} checklist items</span><span>{{ $crew ?: 'Crew not assigned' }}</span>
                                @if($photoLate || $videoLate)<span class="late">{{ collect([$photoLate ? 'Photos overdue' : null, $videoLate ? 'Video overdue' : null])->filter()->join(' · ') }}</span>
                                @else<span>Photos {{ $coverage->photo_due_on?->format('M j') ?: '—' }} · Video {{ $coverage->video_due_on?->format('M j') ?: '—' }}</span>@endif
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>

        <div class="stack">
            @if($overdueObligations->isNotEmpty())
                <section class="card" style="border-color:#fecaca">
                    <div class="panel-head">
                        <h2 style="color:#991b1b">Overdue content</h2>
                        <a class="button ghost" href="{{ route('admin.obligations.index', ['show' => 'overdue']) }}">Review</a>
                    </div>
                    @foreach($overdueObligations as $obligation)
                        <div class="row">
                            <span class="dot" style="background:#dc2626"></span>
                            <span>
                                <span class="nm">{{ $obligation->title }}</span>
                                <div class="meta">{{ $obligation->endorser?->name ?: 'Endorser removed' }}</div>
                            </span>
                            <span class="when" style="color:#b91c1c;font-weight:700">{{ $obligation->due_date->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </section>
            @endif

            @if($awaitingCrew->isNotEmpty() || $queuedTasks->isNotEmpty())
                {{-- Marketing cannot open the crew's screens, so without this
                     they have no way of telling a request was ever picked up. --}}
                <section class="card" style="border-color:#f5d98e">
                    <div class="panel-head">
                        <h2>With multimedia</h2>
                        <a class="button ghost" href="{{ auth()->user()->canSeeMultimedia() ? route('admin.coverage.index', ['show' => 'requested']) : route('admin.events.index', ['when' => 'all']) }}">Open</a>
                    </div>
                    <p class="muted small" style="margin:-6px 0 12px">Sent over and not taken on yet.</p>

                    @foreach($awaitingCrew as $coverage)
                        <div class="row">
                            <span class="dot" style="background:#f59e0b"></span>
                            <span>
                                <span class="nm">{{ $coverage->event->name }}</span>
                                <div class="meta">
                                    Coverage · {{ $coverage->event->event_date->format('M j') }}
                                    @if($coverage->requester) · from {{ $coverage->requester->name }} @endif
                                </div>
                            </span>
                            <span class="when">{{ $coverage->requested_at?->diffForHumans(short: true) }}</span>
                        </div>
                    @endforeach

                    @foreach($queuedTasks as $queued)
                        <div class="row">
                            <span class="dot" style="background:#f59e0b"></span>
                            <span>
                                <span class="nm">{{ $queued->title }}</span>
                                <div class="meta">
                                    Task
                                    @if($queued->raisedBy) · from {{ $queued->raisedBy->name }} @endif
                                </div>
                            </span>
                            <span class="when">{{ $queued->created_at?->diffForHumans(short: true) }}</span>
                        </div>
                    @endforeach
                </section>
            @endif

            @if($upcomingKits->isNotEmpty())
                <section class="card">
                    <div class="panel-head">
                        <h2>PR kit movements</h2>
                        <a class="button ghost" href="{{ route('admin.pr-kits.index') }}">View all</a>
                    </div>
                    @foreach($upcomingKits as $kit)
                        @php
                            $isDelivery = $kit->delivery_date && $kit->delivery_date->gte(today());
                            $when = $isDelivery ? $kit->delivery_date : $kit->pickup_date;
                        @endphp
                        <div class="row">
                            <span class="dot" style="background:{{ $isDelivery ? '#f59e0b' : '#7c3aed' }}"></span>
                            <span>
                                <span class="nm">{{ $isDelivery ? 'Deliver' : 'Pick up' }} · {{ $kit->recipient }}</span>
                                <div class="meta">{{ $kit->courier ?: 'Courier not set' }}</div>
                            </span>
                            <span class="when">{{ $when?->format('M j') }}</span>
                        </div>
                    @endforeach
                </section>
            @endif

        </div>
    </div>

@endif

<script>
    (function(){
        var toggle=document.getElementById('customize-dashboard'), key='imprint-dashboard-layout'; if(!toggle||!window.localStorage)return;
        var state={}; try{state=JSON.parse(localStorage.getItem(key)||'{}')}catch(e){}
        function id(card){var h=card.querySelector('h2');return h?h.textContent.trim():''}
        document.querySelectorAll('.stack').forEach(function(stack){
            var order=state.order||[]; Array.from(stack.children).filter(function(x){return x.matches('section.card')}).sort(function(a,b){return order.indexOf(id(a))-order.indexOf(id(b))}).forEach(function(x){if(order.includes(id(x)))stack.appendChild(x)});
            stack.addEventListener('dragover',function(e){if(document.body.classList.contains('customizing'))e.preventDefault()});
            stack.addEventListener('drop',function(e){e.preventDefault();var moving=document.querySelector('.dragging'),target=e.target.closest('section.card');if(moving&&target&&moving!==target)stack.insertBefore(moving,target);save()});
        });
        document.querySelectorAll('.stack section.card').forEach(function(card){var name=id(card);if(!name)return;card.dataset.dashboardCard=name;card.draggable=false;if((state.hidden||[]).includes(name))card.classList.add('dashboard-hidden');var b=document.createElement('button');b.type='button';b.className='dash-hide';b.textContent='Hide';b.addEventListener('click',function(){card.classList.toggle('dashboard-hidden');save()});card.appendChild(b);card.addEventListener('dragstart',function(){card.classList.add('dragging')});card.addEventListener('dragend',function(){card.classList.remove('dragging')})});
        function save(){var cards=Array.from(document.querySelectorAll('[data-dashboard-card]'));localStorage.setItem(key,JSON.stringify({hidden:cards.filter(function(c){return c.classList.contains('dashboard-hidden')}).map(id),order:cards.map(id)}))}
        toggle.addEventListener('click',function(){var on=document.body.classList.toggle('customizing');toggle.textContent=on?'Done customizing':'Customize';document.querySelectorAll('[data-dashboard-card]').forEach(function(c){c.draggable=on});});
    })();
    // Copy buttons confirm in place — no toast framework for three words.
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var copy = navigator.clipboard && window.isSecureContext
                ? navigator.clipboard.writeText(button.dataset.copy)
                : new Promise(function (resolve, reject) {
                    var input = document.createElement('textarea');
                    input.value = button.dataset.copy;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    try {
                        document.execCommand('copy') ? resolve() : reject();
                    } catch (error) {
                        reject(error);
                    }
                    input.remove();
                });

            copy.then(function () {
                var label = button.querySelector('span');
                var original = label ? label.textContent : button.textContent;
                if (label) label.textContent = 'Copied'; else button.textContent = 'Copied';
                setTimeout(function () { if (label) label.textContent = original; else button.textContent = original; }, 1400);
            }).catch(function () {
                window.prompt('Copy this link:', button.dataset.copy);
            });
        });
    });

</script>
<style>.dash-hide{display:none;position:absolute;right:12px;bottom:10px;border:1px solid var(--line);background:#fff;border-radius:8px;padding:5px 9px;font-size:.7rem;cursor:pointer}.customizing [data-dashboard-card]{position:relative;outline:2px dashed #f59e0b;cursor:move;padding-bottom:44px}.customizing .dash-hide{display:block}.dashboard-hidden{display:none!important}.customizing .dashboard-hidden{display:block!important;opacity:.42}.dragging{opacity:.35!important}</style>
@endsection
