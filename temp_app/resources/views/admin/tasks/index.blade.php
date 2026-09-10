@extends('layouts.admin')
@section('title', 'Daily Tasks')
@section('content')
<style>
    /* ------------------------------------------------------------ day strip */
    .day-nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .day-nav a{display:grid;place-items:center;height:34px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff;text-decoration:none;color:var(--ink-soft);font-size:.85rem;font-weight:700}
    .day-nav a:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .day-nav select{height:34px;padding:0 10px;border-radius:10px;border:1px solid var(--line);font-size:.85rem;width:auto}
    /* Not new, but next door to the queue and plainly broken: on a phone the
       person picker ate the row and left "›" stranded on a line of its own. The
       picker takes its own row and the three day controls stay together. */
    @media(max-width:560px){
        .day-nav{width:100%}
        .day-nav form{flex:1 1 100%}
        .day-nav select{width:100%}
    }

    .week{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:18px}
    .week a{display:block;text-align:center;padding:9px 4px;border:1px solid var(--line);border-radius:12px;background:#fff;text-decoration:none;color:var(--ink-soft)}
    .week a:hover{border-color:var(--accent)}
    .week a.on{background:var(--ink);border-color:var(--ink);color:#fff}
    .week a.today:not(.on){border-color:var(--accent);background:var(--accent-bg)}
    .week .wd{font-size:.66rem;letter-spacing:.1em;text-transform:uppercase;opacity:.7}
    .week .dd{font-size:1.05rem;font-weight:800;line-height:1.3}
    .week .dots{display:flex;gap:3px;justify-content:center;height:6px}
    .week .dots i{width:5px;height:5px;border-radius:50%;background:var(--accent);display:block}
    .week .dots i.done{background:#10b981}
    .week a.on .dots i{background:#fcd34d}

    /* --------------------------------------------------------- day progress */
    .progress{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .bar{flex:1 1 200px;height:8px;border-radius:999px;background:var(--line-soft);overflow:hidden;min-width:140px}
    .bar span{display:block;height:100%;background:linear-gradient(90deg,var(--accent),#10b981);border-radius:999px;transition:width .3s}
    .progress .figure{font-weight:800;font-size:1.05rem;white-space:nowrap}
    .progress .figure small{font-weight:600;color:var(--muted);font-size:.8rem}

    /* ---------------------------------------------------------------- board */
    /* The columns stretch to a common height: three tinted boxes of three
       different depths read as an unfinished layout rather than a board. */
    .board{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:4px}
    @media(max-width:900px){.board{grid-template-columns:1fr}}
    .col{background:var(--canvas);border:1px solid var(--line);border-radius:var(--radius-lg);padding:14px;min-height:140px}
    .col h2{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:8px}
    .col h2 .n{background:#fff;border:1px solid var(--line);border-radius:999px;padding:1px 9px;font-size:.72rem;color:var(--ink-soft)}
    .col-todo{border-top:3px solid #cbd5e1}
    .col-doing{background:#fffdf5;border-color:#fde68a;border-top:3px solid var(--accent)}
    .col-done{background:#f6fdf9;border-color:#a7f3d0;border-top:3px solid #10b981}

    .task{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 13px;margin-bottom:10px;box-shadow:var(--shadow)}
    .task-title{font-weight:650;font-size:.92rem;line-height:1.35;word-break:break-word}
    .task.done .task-title{color:var(--muted);text-decoration:line-through}
    .task-chip{display:inline-block;margin-top:6px;font-size:.7rem;font-weight:700;padding:2px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
    .task-note{font-size:.79rem;color:var(--muted);margin-top:6px;line-height:1.45;white-space:pre-line}
    .task-foot{display:flex;gap:6px;margin-top:11px;align-items:center;flex-wrap:wrap}
    .task-foot form{display:contents}
    .move{border:1px solid var(--line);background:#fff;border-radius:8px;padding:5px 11px;font:inherit;font-size:.75rem;font-weight:700;color:var(--ink-soft);cursor:pointer}
    .move:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}
    .move.primary{background:var(--accent);border-color:var(--accent);color:var(--ink)}
    .move.primary:hover{background:#d97706}
    .icon{margin-left:auto;border:1px solid transparent;background:none;border-radius:8px;padding:4px 7px;cursor:pointer;color:var(--muted);line-height:1}
    .icon:hover{background:var(--line-soft);color:var(--ink)}
    .icon.del:hover{background:#fef2f2;color:#b91c1c}
    .icon svg{width:15px;height:15px;display:block}

    .edit-panel{margin-top:10px;padding-top:10px;border-top:1px dashed var(--line)}
    .edit-panel[hidden]{display:none}
    .edit-panel input,.edit-panel textarea,.edit-panel select{font-size:.85rem;padding:8px}
    .edit-panel textarea{min-height:64px}
    .edit-panel label{margin:8px 0 4px;font-size:.75rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
    .edit-panel .row{display:flex;gap:7px;margin-top:9px}

    .col .none{color:var(--muted);font-size:.85rem;padding:10px 2px;text-align:center}

    /* ------------------------------------------------------- add / send / QA */
    .add{display:grid;grid-template-columns:2fr 1.3fr auto;gap:10px;align-items:end}
    @media(max-width:820px){.add{grid-template-columns:1fr}}
    .add label{margin-top:0}
    .add.with-team{grid-template-columns:2fr 1.3fr 1.3fr auto}
    @media(max-width:1080px){.add.with-team{grid-template-columns:1fr 1fr}}
    @media(max-width:820px){.add.with-team{grid-template-columns:1fr}}

    /* ------------------------------------------------- the team's queue */
    /* Work marketing has sent over that nobody has taken. It sits above the
       board rather than inside it, because it is not anyone's work yet.
       Every class here is prefixed: an unprefixed ".body" collided with the
       layout's own two-column grid and split each item down the middle. */
    .queue{border-color:#f5d98e;background:linear-gradient(#fffdf6,#fffbf0);padding:20px 22px}
    .queue-head{display:flex;align-items:center;gap:11px;margin-bottom:14px}
    .queue-mark{width:32px;height:32px;border-radius:10px;flex:none;display:grid;place-items:center;background:#fef3c7;border:1px solid #fde68a;color:#92400e}
    .queue-mark svg{width:17px;height:17px}
    .queue-head h2{font-size:1rem;margin:0}
    .queue-head .queue-sub{font-size:.78rem;color:#a1783a;margin-top:1px}
    .queue-count{margin-left:auto;font-size:.72rem;font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:3px 11px;white-space:nowrap}

    /* Each request is its own card on the tinted ground, so it reads as a
       discrete job to be picked up rather than a line in a list. */
    .queue-list{display:grid;gap:10px}
    .queue-item{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #f2e4c4;border-radius:13px;padding:13px 15px;box-shadow:0 1px 2px #92400e0f}
    .queue-text{min-width:0;flex:1}
    .queue-title{font-weight:650;font-size:.94rem;line-height:1.35}
    .queue-meta{color:var(--muted);font-size:.79rem;margin-top:4px}
    .queue-detail{color:var(--ink-soft);font-size:.82rem;margin-top:6px;line-height:1.5}
    .queue-item form{flex:none;margin:0}
    .take{display:inline-flex;align-items:center;gap:7px;border:1px solid #a7f3d0;background:#ecfdf5;color:#047857;border-radius:10px;padding:9px 16px;font:inherit;font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .14s,border-color .14s,transform .14s}
    .take:hover{background:#d1fae5;border-color:#6ee7b7;transform:translateY(-1px)}
    .take svg{width:15px;height:15px}

    @media(max-width:640px){
        .queue{padding:16px}
        .queue-item{flex-direction:column;align-items:stretch;gap:11px}
        .queue-item form{width:100%}
        .take{width:100%;justify-content:center}
    }

    /* On a card that came from the queue, whose ask it was. */
    .from-team{display:inline-flex;align-items:center;gap:5px;font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:2px 8px;border-radius:999px;white-space:nowrap;margin-top:7px}
    .give-back{border:0;background:none;padding:4px 0;font:inherit;font-size:.74rem;font-weight:700;color:var(--muted);cursor:pointer;text-decoration:underline}
    .give-back:hover{color:var(--accent-ink)}
    .carry{background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:.9rem}
    .carry form{margin-left:auto}

    .send{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .send input[type=text]{flex:1 1 240px;min-width:0}
    .state{font-size:.78rem;font-weight:700;padding:4px 12px;border-radius:999px;border:1px solid;white-space:nowrap}
    .state-waiting{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .state-checked{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
    .feedback{margin-top:10px;padding:11px 14px;border-radius:11px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:.88rem}

    .review-queue{border-color:#bfdbfe;background:#f7fbff}
    .review-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line-soft);flex-wrap:wrap}
    .review-row:last-child{border-bottom:0}
    .review-row .nm{font-weight:650;font-size:.9rem}
    .review-row .when{font-size:.78rem;color:var(--muted)}
    .review-row form{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .review-row input[type=text]{width:210px}
</style>

<div class="topline">
    <div>
        <h1>Daily tasks</h1>
        <div class="muted small">
            {{ $date->isToday() ? 'Today' : $date->format('l') }} · {{ $date->format('M j, Y') }}
            @if($owner->id !== auth()->id()) · viewing {{ $owner->name }}'s board @endif
        </div>
    </div>
    <div class="day-nav">
        @if($people->isNotEmpty())
            <form method="get" action="{{ route('admin.tasks.index') }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <select name="user" onchange="this.form.submit()" aria-label="Whose board">
                    @foreach($people as $person)
                        <option value="{{ $person->id }}" @selected($person->id === $owner->id)>{{ $person->name }} · {{ App\Models\User::TEAMS[$person->team] ?? $person->team }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <a href="{{ route('admin.tasks.index', ['date' => $previous, 'user' => $owner->id]) }}" aria-label="Previous day">‹</a>
        <a href="{{ route('admin.tasks.index', ['user' => $owner->id]) }}">Today</a>
        <a href="{{ route('admin.tasks.index', ['date' => $next, 'user' => $owner->id]) }}" aria-label="Next day">›</a>
    </div>
</div>

{{-- The working week at a glance: where the work sits, and how much is closed. --}}
<nav class="week" aria-label="This week">
    @for($i = 0; $i < 7; $i++)
        @php
            $day = $weekStart->addDays($i);
            $dayTasks = $week[$day->toDateString()] ?? collect();
        @endphp
        <a class="{{ $day->isSameDay($date) ? 'on' : '' }} {{ $day->isToday() ? 'today' : '' }}"
           href="{{ route('admin.tasks.index', ['date' => $day->toDateString(), 'user' => $owner->id]) }}">
            <div class="wd">{{ $day->format('D') }}</div>
            <div class="dd">{{ $day->day }}</div>
            <div class="dots">
                @foreach($dayTasks->take(4) as $dayTask)
                    <i class="{{ $dayTask->status === 'done' ? 'done' : '' }}"></i>
                @endforeach
            </div>
        </a>
    @endfor
</nav>

@if($pendingReviews->isNotEmpty())
    <section class="card review-queue">
        <div class="topline" style="margin-bottom:10px">
            <h2>Waiting for your check</h2>
            <span class="muted small">{{ $pendingReviews->count() }} {{ Str::plural('board', $pendingReviews->count()) }} sent in</span>
        </div>
        @foreach($pendingReviews as $pending)
            <div class="review-row">
                <span>
                    <span class="nm">{{ $pending->user->name }}</span>
                    <div class="when">
                        {{ $pending->task_date->format('M j, Y') }} · sent {{ $pending->submitted_at->diffForHumans() }}
                        @if($pending->note) · “{{ $pending->note }}” @endif
                    </div>
                </span>
                <a class="edit" href="{{ route('admin.tasks.index', ['user' => $pending->user_id, 'date' => $pending->task_date->toDateString()]) }}">Open board</a>
                <form method="post" action="{{ route('admin.tasks.review', $pending) }}">@csrf
                    <input type="text" name="feedback" placeholder="Feedback (optional)" maxlength="1000">
                    <button class="button" type="submit">Mark checked</button>
                </form>
            </div>
        @endforeach
    </section>
@endif

@if($carriedOver > 0)
    <div class="carry">
        <strong>{{ $carriedOver }} unfinished {{ Str::plural('task', $carriedOver) }}</strong> left open on earlier days.
        <form method="post" action="{{ route('admin.tasks.carry-over') }}">@csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="user" value="{{ $owner->id }}">
            <button class="button ghost" type="submit">Move them to this day</button>
        </form>
    </div>
@endif

@if($total > 0)
    <div class="card">
        <div class="progress">
            <span class="figure">{{ $doneCount }}<small> of {{ $total }} done</small></span>
            <span class="bar"><span style="width:{{ (int) round($doneCount / max($total, 1) * 100) }}%"></span></span>
            @if($doneCount === $total)
                <span class="state state-checked">Day cleared</span>
            @endif
        </div>
    </div>
@endif

@php
    // Marketing can put a task on their own board or send it to the crew. The
    // crew only ever add to their own, so they never see the choice.
    $canHandOver = auth()->user()->canSeeMarketing();
@endphp

@if($raisedByMe->isNotEmpty())
    {{-- The other side of the hand-off. The crew get "From marketing"; this is
         marketing's view of the same work, and the only place they can call it
         back once somebody has taken it on. --}}
    <div class="card queue">
        <div class="queue-head">
            <span class="queue-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v12H7l-3 3z"/><path d="M8 9h8M8 12.5h5"/></svg>
            </span>
            <div>
                <h2>Work you sent to the team</h2>
                <div class="queue-sub">Still open. Remove anything raised by mistake.</div>
            </div>
            <span class="queue-count">{{ $raisedByMe->count() }} open</span>
        </div>

        <div class="queue-list">
            @foreach($raisedByMe as $sent)
                <div class="queue-item">
                    <div class="queue-text">
                        <div class="queue-title">{{ $sent->title }}</div>
                        <div class="queue-meta">
                            @if($sent->user)
                                Taken on by {{ $sent->user->name }}
                            @else
                                Waiting for someone to take it
                            @endif
                            · due {{ $sent->task_date->format('M j') }}
                            @if($sent->event) · {{ $sent->event->name }} @endif
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.tasks.destroy', $sent) }}"
                          data-confirm="Remove &quot;{{ $sent->title }}&quot;?" data-confirm-detail="{{ $sent->user ? $sent->user->name.' has already taken this on.' : 'Nobody has taken this on yet.' }}" data-confirm-action="Remove">
                        @csrf @method('delete')
                        <button class="take" type="submit" style="color:#b91c1c">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($teamQueue->isNotEmpty())
    <div class="card queue">
        <div class="queue-head">
            <span class="queue-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v12H7l-3 3z"/><path d="M8 9h8M8 12.5h5"/></svg>
            </span>
            <div>
                <h2>{{ $teamQueueLabel }}</h2>
                <div class="queue-sub">Nobody has taken these on yet</div>
            </div>
            <span class="queue-count">{{ $teamQueue->count() }} waiting</span>
        </div>

        <div class="queue-list">
            @foreach($teamQueue as $queued)
                <div class="queue-item">
                    <div class="queue-text">
                        <div class="queue-title">{{ $queued->title }}</div>
                        <div class="queue-meta">
                            Raised by {{ $queued->raisedBy?->name ?? 'marketing' }}
                            · {{ $queued->created_at?->diffForHumans() }}
                            @if($queued->event) · {{ $queued->event->name }} @endif
                        </div>
                        @if($queued->details)
                            <div class="queue-detail">{{ $queued->details }}</div>
                        @endif
                    </div>
                    <form method="post" action="{{ route('admin.tasks.claim', $queued) }}">@csrf
                        <button class="take" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
                            Take it
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="card">
    <form class="add {{ $canHandOver ? 'with-team' : '' }}" method="post" action="{{ route('admin.tasks.store') }}">@csrf
        <input type="hidden" name="task_date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="user" value="{{ $owner->id }}">
        <div>
            <label for="title">New task</label>
            <input id="title" name="title" placeholder="Draft the ride-out captions" maxlength="255" required>
        </div>
        <div>
            <label for="event_id">Related event</label>
            <select id="event_id" name="event_id">
                <option value="">None</option>
                @foreach($events as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        @if($canHandOver)
            <div>
                <label for="for_team">Add to</label>
                <select id="for_team" name="for_team">
                    <option value="{{ $multimediaTeam }}" selected>Multimedia queue</option>
                    <option value="{{ $marketingTeam }}">Marketing queue</option>
                    <option value="personal">Personal board (only you)</option>
                </select>
            </div>
            {{-- Left on "Anyone" the task waits in the queue for whoever picks
                 it up, which is the usual way round. Naming someone is for work
                 only one person can do. One picker per team, shown with its own
                 queue, so a crew member cannot be picked for marketing work. --}}
            @if($crew->isNotEmpty())
                <div data-show-when="for_team" data-show-value="{{ $multimediaTeam }}">
                    <label for="assign_to">Assign to</label>
                    <select id="assign_to" name="assign_to">
                        <option value="">Anyone on the crew</option>
                        @foreach($crew as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($marketingPeople->isNotEmpty())
                <div data-show-when="for_team" data-show-value="{{ $marketingTeam }}">
                    <label for="assign_to_marketing">Assign to</label>
                    <select id="assign_to_marketing" name="assign_to">
                        <option value="">Anyone on marketing</option>
                        @foreach($marketingPeople as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endif
        <div><button class="button" type="submit">Add task</button></div>
    </form>
</div>

<div class="card">
    @if(auth()->user()->isAdmin() && $owner->is(auth()->user()))
        <p class="muted small" style="margin:0">Administrator boards do not need to be submitted for checking.</p>
    @elseif($submission && $submission->isReviewed())
        <div class="send">
            <span class="state state-checked">Checked</span>
            <span class="muted small">Reviewed by {{ $submission->reviewer?->name ?? 'the administrator' }} {{ $submission->reviewed_at->diffForHumans() }}.</span>
            <form method="post" action="{{ route('admin.tasks.submit') }}" style="margin-left:auto">@csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="user" value="{{ $owner->id }}">
                <button class="button ghost" type="submit">Send again</button>
            </form>
        </div>
        @if($submission->feedback)
            <div class="feedback"><strong>Feedback:</strong> {{ $submission->feedback }}</div>
        @endif
    @elseif($submission)
        <div class="send">
            <span class="state state-waiting">Waiting to be checked</span>
            <span class="muted small">Sent {{ $submission->submitted_at->diffForHumans() }}.</span>
            <form method="post" action="{{ route('admin.tasks.submit') }}" style="margin-left:auto">@csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="user" value="{{ $owner->id }}">
                <button class="button ghost" type="submit">Re-send</button>
            </form>
        </div>
    @elseif($total > 0)
        <form class="send" method="post" action="{{ route('admin.tasks.submit') }}">@csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="user" value="{{ $owner->id }}">
            <input type="text" name="note" placeholder="Note for the administrator (optional)" maxlength="1000">
            <button class="button" type="submit">Send to admin for checking</button>
        </form>
    @else
        <p class="muted small" style="margin:0">Add a task before sending the day for checking.</p>
    @endif
</div>

<div class="board">
    @foreach(App\Models\Task::STATUSES as $status => $label)
        @php
            $column = $tasks[$status] ?? collect();
            // The one move that makes sense next, promoted over the others.
            $primary = ['todo' => 'doing', 'doing' => 'done', 'done' => null][$status];
        @endphp
        <section class="col col-{{ $status }}">
            <h2>{{ $label }} <span class="n">{{ $column->count() }}</span></h2>

            @forelse($column as $task)
                <article class="task {{ $status === 'done' ? 'done' : '' }}">
                    <div class="task-title">{{ $task->title }}</div>
                    @if($task->event)<span class="task-chip">{{ $task->event->name }}</span>@endif
                    @if($task->for_team)
                        {{-- Taken from the queue rather than written by hand, so
                             it is clear whose ask it was. --}}
                        <div><span class="from-team">From {{ $task->raisedBy?->name ?? 'marketing' }}</span></div>
                    @endif
                    @if($task->details)<div class="task-note">{{ $task->details }}</div>@endif

                    <div class="task-foot">
                        @if($primary)
                            <form method="post" action="{{ route('admin.tasks.update', $task) }}">@csrf @method('put')
                                <input type="hidden" name="status" value="{{ $primary }}">
                                <button class="move primary" type="submit">{{ $primary === 'doing' ? 'Start' : 'Mark done' }}</button>
                            </form>
                        @endif
                        @foreach(App\Models\Task::STATUSES as $target => $targetLabel)
                            @continue($target === $status || $target === $primary)
                            <form method="post" action="{{ route('admin.tasks.update', $task) }}">@csrf @method('put')
                                <input type="hidden" name="status" value="{{ $target }}">
                                <button class="move" type="submit">{{ $target === 'todo' ? 'Reopen' : $targetLabel }}</button>
                            </form>
                        @endforeach

                        <button class="icon" type="button" data-edit="{{ $task->id }}" aria-label="Edit task" title="Edit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 19 3-.7 9.3-9.3a1.8 1.8 0 0 0 0-2.6l-.7-.7a1.8 1.8 0 0 0-2.6 0L4.7 15z"/></svg>
                        </button>
                        @if($task->for_team)
                            <form method="post" action="{{ route('admin.tasks.release', $task) }}" data-confirm="Put this back in the team queue?" data-confirm-detail="It leaves your board and waits for somebody to take it on." data-confirm-action="Put back">@csrf
                                <button class="give-back" type="submit">Put back</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('admin.tasks.destroy', $task) }}" data-confirm="Remove this task?" data-confirm-detail="It is deleted from the board for good." data-confirm-action="Remove">@csrf @method('delete')
                            <button class="icon del" type="submit" aria-label="Remove task" title="Remove">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                            </button>
                        </form>
                    </div>

                    {{-- Fixing a typo should not mean deleting and retyping the card. --}}
                    <form class="edit-panel" id="edit-{{ $task->id }}" method="post" action="{{ route('admin.tasks.update', $task) }}" hidden>@csrf @method('put')
                        <label for="title-{{ $task->id }}">Task</label>
                        <input id="title-{{ $task->id }}" name="title" value="{{ $task->title }}" maxlength="255" required>
                        <label for="details-{{ $task->id }}">Notes</label>
                        <textarea id="details-{{ $task->id }}" name="details" maxlength="2000" placeholder="Anything worth remembering">{{ $task->details }}</textarea>
                        <label for="event-{{ $task->id }}">Related event</label>
                        <select id="event-{{ $task->id }}" name="event_id">
                            <option value="">None</option>
                            @foreach($events as $id => $name)
                                <option value="{{ $id }}" @selected($task->event_id === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="row">
                            <button class="move primary" type="submit">Save</button>
                            <button class="move" type="button" data-cancel="{{ $task->id }}">Cancel</button>
                        </div>
                    </form>
                </article>
            @empty
                <div class="none">
                    @if($status === 'todo')
                        Nothing queued.
                    @elseif($status === 'doing')
                        Nothing in progress.
                    @else
                        Nothing finished yet.
                    @endif
                </div>
            @endforelse
        </section>
    @endforeach
</div>

<script>
    // Inline editing: the panel is plain markup, revealed on demand.
    document.querySelectorAll('[data-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById('edit-' + button.dataset.edit);
            panel.hidden = ! panel.hidden;
            if (! panel.hidden) panel.querySelector('input[name="title"]').focus();
        });
    });

    document.querySelectorAll('[data-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('edit-' + button.dataset.cancel).hidden = true;
        });
    });

    // "Assign to" only means anything for crew work, so it follows the queue
    // choice. Disabled rather than merely hidden, so a stale pick cannot be
    // posted with a personal task.
    document.querySelectorAll('[data-show-when]').forEach(function (section) {
        var controller = document.querySelector('[name="' + section.dataset.showWhen + '"]');
        if (! controller) return;

        function sync() {
            var visible = controller.value === section.dataset.showValue;
            section.hidden = ! visible;
            section.querySelectorAll('select, input').forEach(function (field) {
                field.disabled = ! visible;
            });
        }

        controller.addEventListener('change', sync);
        sync();
    });
</script>
@endsection
