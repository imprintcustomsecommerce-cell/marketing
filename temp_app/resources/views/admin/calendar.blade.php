@extends('layouts.admin')
@section('title', 'Calendar')
@section('content')
@php
    use App\Http\Controllers\Admin\CalendarController;

    // Every link keeps the current filter and month, so switching one control
    // never silently resets the others.
    $showParam = count($active) === count(CalendarController::FILTERS) ? null : implode(',', $active);
    $base = array_filter(['month' => $month->format('Y-m'), 'show' => $showParam]);
@endphp
<style>
    .cal-nav{display:flex;align-items:center;gap:8px}
    .cal-nav a{display:grid;place-items:center;height:38px;min-width:38px;padding:0 12px;border:1px solid var(--line);border-radius:11px;background:#fff;text-decoration:none;color:var(--ink-soft);font-weight:700;font-size:.85rem}
    .cal-nav a:hover{border-color:var(--accent);color:var(--accent-ink);background:var(--accent-bg)}

    /* Filter chips carry their own count, so it is clear what turning one off
       is hiding. Colour matches the chips inside the grid. */
    .filters{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
    .filters a{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;text-decoration:none;font-size:.84rem;font-weight:650;color:var(--muted)}
    .filters a:hover{border-color:var(--accent)}
    .filters a.on{color:var(--ink);border-color:#ded7c8;background:#fff}
    .filters a .swatch{width:9px;height:9px;border-radius:3px;background:#cbd5e1}
    .filters a.on.f-events .swatch{background:#2563eb}
    .filters a.on.f-kits .swatch{background:#f59e0b}
    .filters a.on.f-content .swatch{background:#059669}
    .filters a .n{font-size:.74rem;color:var(--muted);background:var(--line-soft);border-radius:999px;padding:0 7px;font-weight:700}
    .filters .reset{margin-left:auto;font-size:.82rem;color:var(--muted);text-decoration:none}
    .filters .reset:hover{color:var(--accent-ink);text-decoration:underline}

    /* ------------------------------------------------------------ the month */
    .cal{border:1px solid #ece7dd;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 1px 2px #1018280a,0 8px 24px -20px #10182840}
    .cal-head,.cal-week{display:grid;grid-template-columns:repeat(7,1fr)}
    .cal-head div{padding:11px 12px;font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:#8a8375;font-weight:800;background:#faf8f4;border-bottom:1px solid #ece7dd}
    /* min-width:0 because a grid track refuses to shrink below its content,
       and a nowrap chip's content is the whole untruncated title — without
       it a long entry pushes its day out over the neighbouring one. */
    .cal-day{position:relative;border-right:1px solid #f3efe7;border-bottom:1px solid #f3efe7;min-height:124px;min-width:0;padding:8px;display:flex;flex-direction:column;gap:5px}
    .cal-day:nth-child(7n){border-right:0}
    .cal-week:last-child .cal-day{border-bottom:0}
    .cal-day.out{background:#fdfcfa}
    .cal-day.out .num{color:#c9c3b6}
    .cal-day.picked{background:#fffdf6;box-shadow:inset 0 0 0 2px var(--accent)}
    .num{font-size:.8rem;font-weight:700;color:var(--ink-soft);width:25px;height:25px;display:grid;place-items:center;border-radius:50%;flex:none;text-decoration:none}
    a.num:hover{background:var(--accent-bg);color:var(--accent-ink)}
    .cal-day.today .num{background:var(--accent);color:var(--ink)}

    /* Entry chips. Colour separates the kinds, and each chip still names what
       it is, so the calendar is never read by colour alone. */
    .chip{display:block;text-decoration:none;font-size:.73rem;line-height:1.3;padding:4px 7px;border-radius:7px;border-left:3px solid;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .chip strong{font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis}
    .chip-event{background:#eff6ff;border-color:#2563eb;color:#1e40af}
    .chip-delivery{background:#fffbeb;border-color:#f59e0b;color:#92400e}
    .chip-pickup{background:#f5f3ff;border-color:#7c3aed;color:#5b21b6}
    .chip-giveaway{background:#fdf2f8;border-color:#db2777;color:#9d174d}
    .chip-obligation{background:#ecfdf5;border-color:#059669;color:#065f46}
    .chip-overdue{background:#fef2f2;border-color:#dc2626;color:#991b1b}
    .chip:hover{filter:brightness(.97)}
    .more{font-size:.7rem;color:var(--muted);text-decoration:none;padding-left:3px;font-weight:700}
    .more:hover{color:var(--accent-ink);text-decoration:underline}

    /* ------------------------------------------------------- the day opened */
    .day-panel{margin-top:18px}
    .day-panel .panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
    .entry{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--line-soft);text-decoration:none;color:inherit}
    .entry:last-child{border-bottom:0}
    .entry .bar{width:4px;align-self:stretch;border-radius:99px;flex:none}
    .entry .nm{font-weight:650;font-size:.92rem}
    .entry .mt{font-size:.79rem;color:var(--muted);margin-top:2px}
    .entry .kind{margin-left:auto;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap}

    /* --------------------------------------------------------------- agenda */
    /* A seven-column grid is unreadable on a phone, so small screens get the
       month as a list of the days that actually have something on them. */
    .agenda{display:none}
    .agenda-day{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--line-soft)}
    .agenda-day:last-child{border-bottom:0}
    .agenda-date{flex:none;width:46px;text-align:center}
    .agenda-date .d{font-size:1.25rem;font-weight:800;line-height:1.1}
    .agenda-date .w{font-size:.64rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
    .agenda-day.today .agenda-date .d{background:var(--accent);border-radius:10px}
    .agenda-items{flex:1;min-width:0;display:grid;gap:6px}

    @media(max-width:820px){
        .cal{display:none}
        .agenda{display:block}
        .filters .reset{margin-left:0}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Schedule</span>
        <h1>{{ $month->format('F Y') }}</h1>
        <div class="muted small">{{ $entryCount }} {{ Str::plural('entry', $entryCount) }} shown · events, PR kits, and content due dates</div>
    </div>
    <div class="cal-nav">
        <a href="{{ route('admin.calendar', ['month' => $previous] + array_filter(['show' => $showParam])) }}" aria-label="Previous month">‹</a>
        <a href="{{ route('admin.calendar', array_filter(['show' => $showParam])) }}">Today</a>
        <a href="{{ route('admin.calendar', ['month' => $next] + array_filter(['show' => $showParam])) }}" aria-label="Next month">›</a>
    </div>
</div>

<div class="filters">
    @foreach(CalendarController::FILTERS as $key => $filter)
        @php
            $isOn = in_array($key, $active, true);
            // Clicking a chip toggles it; turning the last one off shows all
            // again rather than leaving an empty calendar.
            $next = $isOn ? array_values(array_diff($active, [$key])) : array_values(array_merge($active, [$key]));
            $param = (count($next) === count(CalendarController::FILTERS) || $next === []) ? null : implode(',', $next);
        @endphp
        <a class="{{ $isOn ? 'on' : '' }} f-{{ $key }}"
           href="{{ route('admin.calendar', array_filter(['month' => $month->format('Y-m'), 'show' => $param])) }}">
            <span class="swatch"></span>
            {{ $filter['label'] }}
            <span class="n">{{ $counts[$key] }}</span>
        </a>
    @endforeach

    @if($showParam)
        <a class="reset" href="{{ route('admin.calendar', ['month' => $month->format('Y-m')]) }}">Show everything</a>
    @endif
</div>

<div class="cal">
    <div class="cal-head">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)<div>{{ $weekday }}</div>@endforeach
    </div>
    @foreach($weeks as $week)
        <div class="cal-week">
            @foreach($week as $day)
                <div class="cal-day {{ $day['in_month'] ? '' : 'out' }} {{ $day['is_today'] ? 'today' : '' }} {{ $selected && $selected->isSameDay($day['date']) ? 'picked' : '' }}">
                    @if($day['entries']->isNotEmpty())
                        <a class="num" href="{{ route('admin.calendar', $base + ['date' => $day['key']]) }}" title="Open {{ $day['date']->format('M j') }}">{{ $day['date']->day }}</a>
                    @else
                        <span class="num">{{ $day['date']->day }}</span>
                    @endif

                    @foreach($day['entries']->take(3) as $entry)
                        <a class="chip chip-{{ $entry['kind'] }}" href="{{ $entry['url'] }}" title="{{ $entry['label'] }} — {{ $entry['meta'] }}">
                            <strong>{{ $entry['label'] }}</strong>
                        </a>
                    @endforeach

                    @if($day['entries']->count() > 3)
                        <a class="more" href="{{ route('admin.calendar', $base + ['date' => $day['key']]) }}">+{{ $day['entries']->count() - 3 }} more</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>

{{-- Small screens: the same month as a list of the days that have something. --}}
<div class="agenda card">
    @php $busy = $days->filter(fn (array $day) => $day['in_month'] && $day['entries']->isNotEmpty()); @endphp

    @forelse($busy as $day)
        <div class="agenda-day {{ $day['is_today'] ? 'today' : '' }}">
            <div class="agenda-date">
                <div class="d">{{ $day['date']->day }}</div>
                <div class="w">{{ $day['date']->format('D') }}</div>
            </div>
            <div class="agenda-items">
                @foreach($day['entries'] as $entry)
                    <a class="chip chip-{{ $entry['kind'] }}" href="{{ $entry['url'] }}">
                        <strong>{{ $entry['label'] }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <p class="muted small" style="margin:0">Nothing scheduled in {{ $month->format('F') }}.</p>
    @endforelse
</div>

@if($selected)
    <section class="card day-panel">
        <div class="panel-head">
            <h2>{{ $selected->format('l, F j') }}</h2>
            <a class="edit" href="{{ route('admin.calendar', $base) }}">Close</a>
        </div>

        @forelse($selectedEntries as $entry)
            @php
                $tone = [
                    'event' => ['#2563eb', '#eff6ff', '#1e40af', 'Event'],
                    'delivery' => ['#f59e0b', '#fffbeb', '#92400e', 'PR kit out'],
                    'pickup' => ['#7c3aed', '#f5f3ff', '#5b21b6', 'PR kit back'],
                    'giveaway' => ['#db2777', '#fdf2f8', '#9d174d', 'Giveaway'],
                    'obligation' => ['#059669', '#ecfdf5', '#065f46', 'Content due'],
                    'overdue' => ['#dc2626', '#fef2f2', '#991b1b', 'Overdue'],
                ][$entry['kind']];
            @endphp
            <a class="entry" href="{{ $entry['url'] }}">
                <span class="bar" style="background:{{ $tone[0] }}"></span>
                <span>
                    <span class="nm">{{ $entry['label'] }}</span>
                    <div class="mt">{{ $entry['meta'] }}@if($entry['time']) · {{ $entry['time'] }}@endif</div>
                </span>
                <span class="kind" style="background:{{ $tone[1] }};color:{{ $tone[2] }}">{{ $tone[3] }}</span>
            </a>
        @empty
            <p class="muted small" style="margin:0">Nothing scheduled on this day.</p>
        @endforelse
    </section>
@endif
@endsection
