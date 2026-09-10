@extends('layouts.admin')
@section('title', 'Coverage · '.$event->name)
@section('content')
<div class="topline">
    <div>
        <h1>Coverage · {{ $event->name }}</h1>
        <div class="muted small">{{ $event->event_date->format('M j, Y') }} · {{ $event->venue ?: 'Venue not set' }} · {{ $event->eventTypeLabel() }}</div>
    </div>
    <a class="edit" href="{{ route('admin.coverage.index') }}">Back to coverage</a>
</div>

@include('admin.events.preparation-panel', ['event' => $event])

<section class="card" style="margin-bottom:18px">
    <div class="topline" style="margin-bottom:12px"><div><h2>Event-day contact</h2><div class="muted small">Everything the crew needs while on location</div></div>@if($event->group_chat_url)<a class="button ghost" href="{{ $event->group_chat_url }}" target="_blank" rel="noopener noreferrer">Open group chat</a>@endif</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <div><strong>{{ $event->contact_person ?: 'Contact not set' }}</strong><div class="muted small">Organizer / contact</div></div>
        <div>@if($event->contact_number)<a class="edit" href="tel:{{ $event->contact_number }}">{{ $event->contact_number }}</a>@else—@endif<div class="muted small">Mobile number</div></div>
        <div><strong>{{ $event->venue ?: 'Venue not set' }}</strong><div class="muted small">Location</div></div>
        <div><strong>{{ $event->start_time ?: 'Time not set' }}@if($event->end_time)–{{ $event->end_time }}@endif</strong><div class="muted small">{{ $event->event_date->format('M j, Y') }}</div></div>
    </div>
</section>

@if($crew->isEmpty())
    <div class="card empty">No active multimedia accounts yet. <a class="edit" href="{{ route('admin.team.create') }}">Add the crew first</a>.</div>
@else
<form class="card grid" method="post" action="{{ route('admin.coverage.update', $event) }}">@csrf @method('put')
    <div class="full">
        <label for="shooter_id">Shooter</label>
        <select id="shooter_id" name="shooter_id">
            <option value="">Not assigned</option>
            @foreach($crew as $id => $name)
                <option value="{{ $id }}" @selected((int) old('shooter_id', $coverage->shooter_id) === $id)>{{ $name }} · {{ $crewLoads[$id] ?? 0 }} active assignments</option>
            @endforeach
        </select>
        <span class="muted small">Leave unassigned when the company is not sending anyone to this event.</span>
    </div>

    <div>
        <label for="photo_editor_id">Photo editor</label>
        <select id="photo_editor_id" name="photo_editor_id">
            <option value="">Not assigned</option>
            @foreach($crew as $id => $name)
                <option value="{{ $id }}" @selected((int) old('photo_editor_id', $coverage->photo_editor_id) === $id)>{{ $name }} · {{ $crewLoads[$id] ?? 0 }} active assignments</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="video_editor_id">Video editor</label>
        <select id="video_editor_id" name="video_editor_id">
            <option value="">Not assigned</option>
            @foreach($crew as $id => $name)
                <option value="{{ $id }}" @selected((int) old('video_editor_id', $coverage->video_editor_id) === $id)>{{ $name }} · {{ $crewLoads[$id] ?? 0 }} active assignments</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="photo_status">Photo edit</label>
        <select id="photo_status" name="photo_status" required>
            @foreach(App\Models\Coverage::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('photo_status', $coverage->photo_status ?: 'not_started') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="video_status">Video edit</label>
        <select id="video_status" name="video_status" required>
            @foreach(App\Models\Coverage::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('video_status', $coverage->video_status ?: 'not_started') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="photo_posted_on">Photos posted on</label>
        <input id="photo_posted_on" type="date" name="photo_posted_on" value="{{ old('photo_posted_on', $coverage->photo_posted_on?->format('Y-m-d')) }}">
        <span class="muted small">Kept only while the status is Posted.</span>
    </div>
    <div>
        <label for="video_posted_on">Video posted on</label>
        <input id="video_posted_on" type="date" name="video_posted_on" value="{{ old('video_posted_on', $coverage->video_posted_on?->format('Y-m-d')) }}">
    </div>

    <div class="full"><label for="deadline_preset">Deadline preset</label><select id="deadline_preset"><option value="">Keep current dates</option><option value="rush">Rush · photos +1 day, video +2 days</option><option value="standard">Standard · photos +2 days, video +4 days</option><option value="extended">Extended · photos +4 days, video +7 days</option></select></div>
    <div><label for="photo_due_on">Photo deadline</label><input id="photo_due_on" type="date" name="photo_due_on" value="{{ old('photo_due_on', $coverage->photo_due_on?->format('Y-m-d')) }}"></div>
    <div><label for="video_due_on">Video deadline</label><input id="video_due_on" type="date" name="video_due_on" value="{{ old('video_due_on', $coverage->video_due_on?->format('Y-m-d')) }}"></div>

    <div class="full">
        <label>Production checklist</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:8px">
            @foreach(App\Models\Coverage::CHECKLIST as $value => $label)
                <label style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0"><input style="width:auto" type="checkbox" name="checklist[]" value="{{ $value }}" @checked(in_array($value, old('checklist', $coverage->checklist ?? []), true))> {{ $label }}</label>
            @endforeach
        </div>
    </div>
    <div class="full"><label for="delivery_url">Final media delivery link</label><input id="delivery_url" type="url" name="delivery_url" value="{{ old('delivery_url', $coverage->delivery_url) }}" placeholder="https://drive.google.com/…"><span class="muted small">Paste the approved Google Drive, Dropbox, or other client-ready folder.</span></div>
    @if($coverage->delivery_sent_at)<div class="full notice">Delivered {{ $coverage->delivery_sent_at->format('M j, Y g:i A') }} by {{ $coverage->deliverySender?->name ?: 'a team member' }}.</div>@endif

    <div class="full"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks">{{ old('remarks', $coverage->remarks) }}</textarea></div>
    <div class="full"><button class="button" type="submit">Save coverage</button></div>
</form>
@if($coverage->exists)
<section class="card" style="margin-top:18px">
    <div class="topline" style="margin-bottom:10px"><div><h2>Revision rounds</h2><div class="muted small">Track requested changes without losing earlier notes.</div></div></div>
    @forelse($coverage->revisions as $revision)<div style="display:flex;gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid var(--line-soft)"><span class="pill pill-{{ $revision->completed_at ? 'completed' : 'pending' }}">Round {{ $revision->round }}</span><span style="flex:1"><strong>{{ $revision->notes }}</strong><span class="muted small" style="display:block">Due {{ $revision->due_on?->format('M j, Y') ?: 'not set' }} · {{ $revision->creator?->name ?: 'Team' }}</span></span><form method="post" action="{{ route('admin.coverage.revisions.complete', [$event, $revision]) }}">@csrf @method('patch')<button class="button ghost" type="submit">{{ $revision->completed_at ? 'Reopen' : 'Complete' }}</button></form></div>@empty<p class="muted small">No revisions requested.</p>@endforelse
    <form method="post" action="{{ route('admin.coverage.revisions.store', $event) }}" class="grid" style="margin-top:8px">@csrf<div><label for="revision_notes">Revision notes</label><input id="revision_notes" name="notes" required></div><div><label for="revision_due">Due date</label><input id="revision_due" type="date" name="due_on"></div><div class="full" style="margin-top:12px"><button class="button ghost" type="submit">+ Add revision round</button></div></form>
</section>
@endif
@if($coverage->exists && $coverage->delivery_url && !$coverage->delivery_sent_at)
<form method="post" action="{{ route('admin.coverage.confirm-delivery', $event) }}" style="margin-top:-8px;margin-bottom:18px">@csrf<button class="button ghost" type="submit">Confirm final link was sent</button></form>
@endif

<section class="card" style="margin-top:18px">
    <h2>Production files</h2>
    <p class="muted small">Upload briefs, shot lists, selects, exports, or a ZIP up to 15 MB.</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.events.files.store', $event) }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">@csrf
        <div style="flex:1;min-width:220px"><label for="file">Choose file</label><input id="file" type="file" name="file" required></div><button class="button" type="submit">Upload</button>
    </form>
    @if($event->files->isNotEmpty())<div style="display:grid;gap:8px;margin-top:16px">@foreach($event->files as $file)<a class="edit" href="{{ route('admin.events.files.download', [$event, $file]) }}">{{ $file->name }} <span class="muted small">· {{ number_format($file->size / 1024, 0) }} KB</span></a>@endforeach</div>@endif
</section>
@endif
<script>
    (function () {
        var preset = document.getElementById('deadline_preset');
        if (!preset) return;
        var eventDate = new Date('{{ $event->event_date->format('Y-m-d') }}T12:00:00');
        function dateAfter(days) { var date = new Date(eventDate); date.setDate(date.getDate() + days); return date.toISOString().slice(0, 10); }
        preset.addEventListener('change', function () {
            var offsets = {rush:[1,2], standard:[2,4], extended:[4,7]}[preset.value];
            if (!offsets) return;
            document.getElementById('photo_due_on').value = dateAfter(offsets[0]);
            document.getElementById('video_due_on').value = dateAfter(offsets[1]);
        });
    })();
</script>
@endsection
