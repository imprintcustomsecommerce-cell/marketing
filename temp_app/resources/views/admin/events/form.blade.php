@extends('layouts.admin')
@section('title', $event->exists ? 'Edit Event' : 'New Event')
@section('content')
<div class="topline">
    <div>
        <span class="page-kicker">{{ $event->exists ? 'Event record' : 'New record' }}</span>
        <h1>{{ $event->exists ? 'Edit '.$event->name : 'Add a new event' }}</h1>
        <div class="muted small">Capture the schedule, venue, attendance, and coordination details.</div>
    </div>
</div>

<form class="record-form" method="post" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
    @csrf
    @if($event->exists)@method('put')@endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v14H4zM4 9h16M8 3v4M16 3v4"/></svg></span>
            <span><h2>Event information</h2><p>The core information staff use to identify and categorize this event.</p></span>
        </div>
        <div class="form-grid">
            <div class="span-2"><label for="name">Event name <span class="required">*</span></label><input id="name" name="name" value="{{ old('name', $event->name) }}" placeholder="e.g. Tambike Night Ride" required autofocus></div>
            <div><label for="category">Event type <span class="required">*</span></label><select id="category" name="category" required>@foreach(App\Models\Event::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category', $event->category ?: 'tambike') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="status">Status <span class="required">*</span></label><select id="status" name="status" required>@foreach(['new','pending','confirmed','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', $event->status ?: 'new') === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>
            <div><label for="organization">Organization</label><input id="organization" name="organization" value="{{ old('organization', $event->organization) }}" placeholder="Client, club, or partner"></div>
            <div><label for="estimated_pax">Estimated attendance</label><input id="estimated_pax" type="number" min="1" name="estimated_pax" value="{{ old('estimated_pax', $event->estimated_pax) }}" placeholder="Expected guests"></div>
            <div><label for="contact_person">Contact person</label><input id="contact_person" name="contact_person" value="{{ old('contact_person', $event->contact_person) }}" placeholder="Client representative"></div>
            <div><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $event->contact_number) }}" placeholder="09XX XXX XXXX"></div>
            <div class="span-2"><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $event->contact_email) }}" placeholder="client@example.com"></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <span><h2>Schedule and location</h2><p>When and where the team needs to be.</p></span>
        </div>
        <div class="form-grid">
            <div><label for="event_date">Event date <span class="required">*</span></label><input id="event_date" type="date" name="event_date" value="{{ old('event_date', optional($event->event_date)->format('Y-m-d')) }}" required></div>
            <div><label for="venue">Venue</label><input id="venue" name="venue" value="{{ old('venue', $event->venue) }}" placeholder="Venue or meeting point"></div>
            <div><label for="start_time">Start time</label><input id="start_time" type="time" name="start_time" value="{{ old('start_time', $event->start_time) }}"></div>
            <div><label for="end_time">End time</label><input id="end_time" type="time" name="end_time" value="{{ old('end_time', $event->end_time) }}"></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L7 21l.6-3.2A8.4 8.4 0 1 1 21 11.5z"/></svg></span>
            <span><h2>Coordination</h2><p>Keep the working conversation and internal context attached to the event.</p></span>
        </div>
        <div class="form-grid">
            <div class="span-2"><label for="group_chat_url">Group chat link</label><input id="group_chat_url" type="url" name="group_chat_url" value="{{ old('group_chat_url', $event->group_chat_url) }}" placeholder="https://m.me/j/..."><span class="field-help">Messenger, Viber, or WhatsApp coordination thread.</span></div>
            <div class="span-2"><label for="notes">Internal notes</label><textarea id="notes" name="notes" placeholder="Requirements, reminders, or important context…">{{ old('notes', $event->notes) }}</textarea></div>
        </div>
    </section>

    <div class="form-actions">
        <a class="button ghost" href="{{ route('admin.events.index') }}">Cancel</a>
        <button class="button" type="submit">{{ $event->exists ? 'Save event' : 'Create event' }}</button>
    </div>
</form>
@endsection
