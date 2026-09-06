@extends('layouts.admin')
@section('title', $obligation->exists ? 'Edit Obligation' : 'New Obligation')
@section('content')
<div class="topline">
    <h1>{{ $obligation->exists ? 'Edit obligation' : 'Add an obligation' }}</h1>
    <a class="edit" href="{{ route('admin.obligations.index') }}">Back to obligations</a>
</div>

@if($endorsers->isEmpty())
    <div class="card empty">An obligation belongs to an endorser, and none exist yet. <a class="edit" href="{{ route('admin.endorsers.create') }}">Add an endorser first</a>.</div>
@else
<form class="card grid" method="post" action="{{ $obligation->exists ? route('admin.obligations.update', $obligation) : route('admin.obligations.store') }}">@csrf
    @if($obligation->exists)@method('put')@endif

    <div class="full"><label>Obligation</label><input name="title" value="{{ old('title', $obligation->title) }}" placeholder="Instagram reel featuring the new jersey" required></div>

    <div><label>Endorser</label><select name="endorser_id" required>@foreach($endorsers as $id => $name)<option value="{{ $id }}" @selected((int) old('endorser_id', $obligation->endorser_id) === $id)>{{ $name }}</option>@endforeach</select></div>
    <div><label>Event</label><select name="event_id"><option value="">Not linked</option>@foreach($events as $id => $name)<option value="{{ $id }}" @selected((int) old('event_id', $obligation->event_id) === $id)>{{ $name }}</option>@endforeach</select></div>

    <div><label>Type</label><select name="type" required>@foreach(App\Http\Controllers\Admin\ObligationController::TYPES as $type)<option value="{{ $type }}" @selected(old('type', $obligation->type ?: 'social_post') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
    <div><label>Status</label><select name="status" required>@foreach(App\Http\Controllers\Admin\ObligationController::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $obligation->status ?: 'pending') === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>

    <div><label>Due date</label><input type="date" name="due_date" value="{{ old('due_date', $obligation->due_date?->format('Y-m-d')) }}" required></div>
    <div><label>Completed on</label><input type="date" name="completed_on" value="{{ old('completed_on', $obligation->completed_on?->format('Y-m-d')) }}"></div>

    <div class="full"><label>Proof URL</label><input type="url" name="proof_url" value="{{ old('proof_url', $obligation->proof_url) }}" placeholder="https://instagram.com/p/…"></div>
    <div class="full"><label>Description</label><textarea name="description">{{ old('description', $obligation->description) }}</textarea></div>
    <div class="full"><label>Internal notes</label><textarea name="notes">{{ old('notes', $obligation->notes) }}</textarea></div>
    <div class="full"><button class="button" type="submit">{{ $obligation->exists ? 'Save changes' : 'Add obligation' }}</button></div>
</form>
@endif
@endsection
