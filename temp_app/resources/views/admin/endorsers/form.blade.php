@extends('layouts.admin')
@section('title', $endorser->exists ? 'Edit Endorser' : 'New Endorser')
@section('content')
<div class="topline">
    <div>
        <span class="page-kicker">{{ $endorser->exists ? 'Endorser profile' : 'New profile' }}</span>
        <h1>{{ $endorser->exists ? 'Edit '.$endorser->name : 'Add a new endorser' }}</h1>
        <div class="muted small">Keep contact, partnership, and coordination details in one profile.</div>
    </div>
</div>

<form class="record-form" method="post" action="{{ $endorser->exists ? route('admin.endorsers.update', $endorser) : route('admin.endorsers.store') }}">
    @csrf
    @if($endorser->exists)@method('put')@endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg></span>
            <span><h2>Profile</h2><p>Who the partner is and how they are represented in the system.</p></span>
        </div>
        <div class="form-grid">
            <div class="span-2"><label for="name">Name <span class="required">*</span></label><input id="name" name="name" value="{{ old('name', $endorser->name) }}" placeholder="Full name or organization" required autofocus></div>
            <div><label for="type">Endorser type <span class="required">*</span></label><select id="type" name="type">@foreach(['individual','racer','team','influencer','organization'] as $type)<option value="{{ $type }}" @selected(old('type', $endorser->type ?: 'individual') === $type)>{{ str($type)->title() }}</option>@endforeach</select></div>
            <div><label for="status">Status <span class="required">*</span></label><select id="status" name="status">@foreach(['new','active','pending','inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $endorser->status ?: 'new') === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>
            <div><label for="team_or_group">Team or group</label><input id="team_or_group" name="team_or_group" value="{{ old('team_or_group', $endorser->team_or_group) }}" placeholder="Affiliated team, club, or group"></div>
            <div><label for="birthday">Birthday</label><input id="birthday" type="date" name="birthday" max="{{ today()->toDateString() }}" value="{{ old('birthday', optional($endorser->birthday)->format('Y-m-d')) }}"><span class="muted small">Used for the greeting reminder. Leave blank if you do not know it.</span></div>
            <div class="span-2"><label for="profile">Profile</label><textarea id="profile" name="profile" placeholder="Background, achievements, audience, or partnership context…">{{ old('profile', $endorser->profile) }}</textarea></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg></span>
            <span><h2>Contact details</h2><p>Direct channels for staff coordination and campaign follow-up.</p></span>
        </div>
        <div class="form-grid">
            <div><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $endorser->contact_number) }}" placeholder="09XX XXX XXXX"></div>
            <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $endorser->email) }}" placeholder="name@example.com"></div>
            <div><label for="social_media_url">Social media URL</label><input id="social_media_url" type="url" name="social_media_url" value="{{ old('social_media_url', $endorser->social_media_url) }}" placeholder="https://..."></div>
            <div><label for="group_chat_url">Group chat link</label><input id="group_chat_url" type="url" name="group_chat_url" value="{{ old('group_chat_url', $endorser->group_chat_url) }}" placeholder="https://m.me/j/..."></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg></span>
            <span><h2>Internal notes</h2><p>Private staff context; never displayed on public client pages.</p></span>
        </div>
        <label for="notes">Notes</label><textarea id="notes" name="notes" placeholder="Agreement details, next steps, or internal reminders…">{{ old('notes', $endorser->notes) }}</textarea>
    </section>

    <div class="form-actions">
        <a class="button ghost" href="{{ route('admin.endorsers.index') }}">Cancel</a>
        <button class="button" type="submit">{{ $endorser->exists ? 'Save endorser' : 'Create endorser' }}</button>
    </div>
</form>
@endsection
