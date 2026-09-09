@extends('layouts.admin')
@section('title', $event->name)
@section('content')
@if($event->archived_at)
<div class="card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px"><div><strong>Archived event</strong><div class="muted small">Hidden from active event lists and reminders.</div></div><form method="post" action="{{ route('admin.events.restore', $event) }}">@csrf @method('patch')<button class="button ghost" type="submit">Restore</button></form></div>
@endif
<style>.event-head{padding:26px;background:linear-gradient(135deg,#111827,#1f2937);color:#fff;border-radius:18px;margin-bottom:18px;position:relative;overflow:hidden}.event-head::after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;right:-90px;top:-140px;background:#f59e0b25}.event-head .muted{color:#cbd5e1}.event-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:18px}.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}.datum{padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--canvas)}.datum small{display:block;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-size:.66rem;font-weight:750}.datum strong{display:block;margin-top:3px}.row-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line-soft)}.row-item:last-child{border:0}.row-main{flex:1}.row-main strong{display:block}.file-form{display:flex;gap:9px;align-items:center;margin-top:14px}.file-form input{flex:1}.timeline{border-left:2px solid #fde68a;margin-left:6px;padding-left:18px}.timeline-item{position:relative;padding:0 0 16px}.timeline-item::before{content:"";position:absolute;left:-24px;top:6px;width:10px;height:10px;border-radius:50%;background:var(--accent)}@media(max-width:900px){.event-grid,.detail-grid{grid-template-columns:1fr}.file-form{align-items:stretch;flex-direction:column}}</style>
<div class="event-head">
    <div class="topline" style="position:relative;z-index:1;margin:0">
        <div><span class="page-kicker" style="color:#fcd34d">{{ $event->eventTypeLabel() }} · {{ $event->eventCategoryLabel() }}</span><h1>{{ $event->name }}</h1><div class="muted small">{{ $event->event_date->format('l, F j, Y') }} · {{ $event->venue ?: 'Venue not set' }}</div></div>
        <div style="display:flex;gap:9px;flex-wrap:wrap"><span class="pill pill-{{ $event->status }}">{{ str($event->status)->title() }}</span><a class="button ghost" href="{{ route('admin.events.summary', $event) }}" target="_blank">Print summary</a><a class="button" href="{{ route('admin.events.edit', $event) }}">Edit event</a></div>
    </div>
</div>

<div class="event-grid">
    <div>
        <section class="card"><div class="topline" style="margin-bottom:14px"><h2>Event details</h2><div style="display:flex;gap:10px;align-items:center"><a class="edit" href="{{ route('admin.calendar', ['date' => $event->event_date->toDateString()]) }}">Open calendar</a>@unless($event->archived_at)<form method="post" action="{{ route('admin.events.archive', $event) }}">@csrf @method('patch')<button class="edit" style="border:0;background:none;cursor:pointer" type="submit">Archive event</button></form>@endunless
            @if(auth()->user()->isAdmin())
                <form method="post" action="{{ route('admin.events.destroy', $event) }}"
                      onsubmit="return confirm('Delete &quot;{{ addslashes($event->name) }}&quot; for good?

Its coverage, production tasks, and uploaded files go with it. This cannot be undone. Archive it instead if you only want it out of the way.')">
                    @csrf @method('delete')
                    <button class="edit" style="border:0;background:none;cursor:pointer;color:#b91c1c" type="submit">Delete permanently</button>
                </form>
            @endif</div></div><div class="detail-grid">
            <div class="datum"><small>Organization</small><strong>{{ $event->organization ?: 'Not provided' }}</strong></div>
            <div class="datum"><small>Estimated attendance</small><strong>{{ $event->estimated_pax ? number_format($event->estimated_pax).' people' : 'Not provided' }}</strong></div>
            <div class="datum"><small>Time</small><strong>{{ $event->start_time ? \Carbon\Carbon::parse($event->start_time)->format('g:i A') : 'Not set' }}{{ $event->end_time ? ' – '.\Carbon\Carbon::parse($event->end_time)->format('g:i A') : '' }}</strong></div>
            <div class="datum"><small>Created by</small><strong>{{ $event->creator?->name ?? 'Unknown' }}</strong></div>
        </div>@if($event->notes)<div style="margin-top:18px"><small class="page-kicker">Internal notes</small><p style="white-space:pre-wrap;margin-bottom:0">{{ $event->notes }}</p></div>@endif</section>

        <section class="card"><h2>Client information</h2><div class="detail-grid" style="margin-top:14px"><div class="datum"><small>Contact person</small><strong>{{ $event->contact_person ?: 'Not provided' }}</strong></div><div class="datum"><small>Contact number</small><strong>{{ $event->contact_number ?: 'Not provided' }}</strong></div><div class="datum" style="grid-column:1/-1"><small>Email</small><strong>{{ $event->contact_email ?: 'Not provided' }}</strong></div></div></section>

        <section class="card"><div class="topline" style="margin-bottom:8px"><h2>Multimedia coverage</h2>@if($event->coverage)<a class="edit" href="{{ route('admin.coverage.edit', $event) }}">Open coverage</a>@endif</div>@if($event->coverage)<div class="detail-grid"><div class="datum"><small>Stage</small><strong>{{ $event->coverage->stageLabel() }}</strong></div><div class="datum"><small>Shooter</small><strong>{{ $event->coverage->shooter?->name ?? 'Unassigned' }}</strong></div><div class="datum"><small>Photo</small><strong>{{ App\Models\Coverage::STATUSES[$event->coverage->photo_status] ?? 'Not started' }} · {{ $event->coverage->photoEditor?->name ?? 'No editor' }}</strong></div><div class="datum"><small>Video</small><strong>{{ App\Models\Coverage::STATUSES[$event->coverage->video_status] ?? 'Not started' }} · {{ $event->coverage->videoEditor?->name ?? 'No editor' }}</strong></div></div>@else<p class="muted small">This event has not been sent to Multimedia yet.</p><form method="post" action="{{ route('admin.events.request-coverage', $event) }}">@csrf<button class="button" type="submit">Request coverage</button></form>@endif</section>
    </div>

    <aside>
        <section class="card"><div class="topline" style="margin-bottom:6px"><h2>Related tasks</h2><a class="edit" href="{{ route('admin.tasks.index') }}">Task board</a></div>@forelse($event->tasks as $task)<div class="row-item"><span class="pill pill-{{ $task->status === 'done' ? 'completed' : 'pending' }}">{{ App\Models\Task::STATUSES[$task->status] }}</span><span class="row-main"><strong>{{ $task->title }}</strong><span class="muted small">{{ $task->user?->name ?? 'Multimedia queue' }}</span></span></div>@empty<p class="muted small">No tasks are linked to this event.</p>@endforelse</section>

        <section class="card"><h2>Files</h2>@forelse($event->files as $file)<div class="row-item"><span class="row-main"><strong>{{ $file->name }}</strong><span class="muted small">{{ number_format($file->size / 1024, 1) }} KB · {{ $file->uploader?->name ?? 'Unknown' }}</span></span><a class="edit" href="{{ route('admin.events.files.download', [$event, $file]) }}">Download</a></div>@empty<p class="muted small">No files are attached.</p>@endforelse<form class="file-form" method="post" action="{{ route('admin.events.files.store', $event) }}" enctype="multipart/form-data">@csrf<input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip"><button class="button" type="submit">Attach</button></form><span class="field-help">Private storage · maximum 15 MB</span></section>

        <section class="card"><h2>Client links</h2>@forelse($event->publicLinks as $link)<div class="row-item"><span class="row-main"><strong>{{ $link->isAvailable() ? 'Active link' : 'Inactive link' }}</strong><span class="muted small">{{ $link->submission_count }} responses</span></span></div>@empty<p class="muted small">No client review links have been generated.</p>@endforelse</section>

        <section class="card"><div class="topline" style="margin-bottom:12px"><h2>History</h2>@if(auth()->user()->isAdmin())<a class="edit" href="{{ route('admin.activity') }}">All activity</a>@endif</div><div class="timeline">@forelse($activities as $activity)<div class="timeline-item"><strong>{{ ucfirst($activity->action) }}</strong><div class="muted small">{{ $activity->user?->name ?? 'Public/system' }} · {{ $activity->created_at->diffForHumans() }}</div></div>@empty<p class="muted small">No changes recorded yet.</p>@endforelse</div></section>
    </aside>
</div>
@endsection

