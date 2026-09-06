@extends('layouts.admin')
@section('title', $prKit->exists ? 'Edit PR Kit' : 'New PR Kit')
@section('content')
<div class="topline">
    <h1>{{ $prKit->exists ? 'Edit PR kit' : 'Add a PR kit' }}</h1>
    <a class="edit" href="{{ route('admin.pr-kits.index') }}">Back to PR kits</a>
</div>

<form class="card grid" method="post" action="{{ $prKit->exists ? route('admin.pr-kits.update', $prKit) : route('admin.pr-kits.store') }}">@csrf
    @if($prKit->exists)@method('put')@endif

    <div>
        <label>Purpose</label>
        <select name="purpose" required>
            <option value="endorser" @selected(old('purpose', $prKit->purpose ?: 'endorser') === 'endorser')>Endorser kit — owes content back</option>
            <option value="giveaway" @selected(old('purpose', $prKit->purpose) === 'giveaway')>Giveaway — raffle or event handout</option>
        </select>
        <span class="muted small">An endorser kit goes to a person and owes content. A giveaway goes to an event and owes nothing.</span>
    </div>
    <div><label>Quantity</label><input type="number" min="1" max="10000" name="quantity" value="{{ old('quantity', $prKit->quantity ?? 1) }}" required><span class="muted small">Number of kits in this batch.</span></div>

    <div><label>Recipient</label><input name="recipient" value="{{ old('recipient', $prKit->recipient) }}" required placeholder="Endorser name, or the event for giveaways"></div>
    <div><label>Reference</label><input name="reference" value="{{ old('reference', $prKit->reference) }}" placeholder="PRK-001"></div>

    {{-- Only one of these applies at a time; the server enforces it either way. --}}
    <div data-when="endorser">
        <label for="endorser_id">Endorser</label>
        <select id="endorser_id" name="endorser_id">
            <option value="">{{ $endorsers->isEmpty() ? 'No endorsers yet' : 'Select an endorser' }}</option>
            @foreach($endorsers as $id => $name)
                <option value="{{ $id }}" @selected((int) old('endorser_id', $prKit->endorser_id) === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <span class="muted small">Who the monthly kit is for.</span>
    </div>

    <div data-when="giveaway">
        <label for="event_id">Event</label>
        <select id="event_id" name="event_id">
            <option value="">{{ $events->isEmpty() ? 'No events yet' : 'Select an event' }}</option>
            @foreach($events as $id => $name)
                <option value="{{ $id }}" @selected((int) old('event_id', $prKit->event_id) === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <span class="muted small">Where the giveaway stock is handed out.</span>
    </div>

    <div><label>Delivery date</label><input type="date" name="delivery_date" value="{{ old('delivery_date', $prKit->delivery_date?->format('Y-m-d')) }}"></div>
    <div><label>Pickup date</label><input type="date" name="pickup_date" value="{{ old('pickup_date', $prKit->pickup_date?->format('Y-m-d')) }}"></div>

    <div><label>Courier</label><input name="courier" value="{{ old('courier', $prKit->courier) }}"></div>
    <div><label>Tracking number</label><input name="tracking_number" value="{{ old('tracking_number', $prKit->tracking_number) }}"></div>

    <div><label>Status</label><select name="status" required>@foreach(App\Http\Controllers\Admin\PrKitController::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $prKit->status ?: 'scheduled') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
    <div data-when="endorser">
        <label>Content required</label>
        <input type="number" min="0" max="20" name="content_quota" value="{{ old('content_quota', $prKit->content_quota ?? 2) }}">
        <span class="muted small">House rule is 2 videos per kit. The obligations are created once the status says the kit was received.</span>
    </div>

    <div class="full"><label>Address</label><input name="address" value="{{ old('address', $prKit->address) }}"></div>

    <div class="full"><label>Kit contents</label><textarea name="contents" placeholder="Jersey, decals, cap, product samples…">{{ old('contents', $prKit->contents) }}</textarea></div>
    <div class="full"><label>Internal notes</label><textarea name="notes">{{ old('notes', $prKit->notes) }}</textarea></div>
    <div class="full"><button class="button" type="submit">{{ $prKit->exists ? 'Save changes' : 'Add PR kit' }}</button></div>
</form>

@if($prKit->exists && $prKit->obligations->isNotEmpty())
    <section class="card">
        <div class="topline" style="margin-bottom:12px">
            <h2>Content owed for this kit</h2>
            <a class="edit" href="{{ route('admin.obligations.index') }}">All obligations</a>
        </div>
        <div style="overflow:auto;margin:0 -22px -22px">
            <table>
                <thead><tr><th>Content</th><th>Due</th><th>Proof</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($prKit->obligations as $obligation)
                    <tr>
                        <td><strong>{{ $obligation->title }}</strong></td>
                        <td>{{ $obligation->due_date->format('M j, Y') }}</td>
                        <td>@if($obligation->proof_url)<a class="edit" href="{{ $obligation->proof_url }}" target="_blank" rel="noopener">View</a>@else<span class="muted">—</span>@endif</td>
                        <td><span class="pill pill-{{ $obligation->isOverdue() ? 'missed' : $obligation->status }}">{{ $obligation->isOverdue() ? 'Overdue' : str($obligation->status)->title() }}</span></td>
                        <td><a class="edit" href="{{ route('admin.obligations.edit', $obligation) }}">Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@elseif($prKit->exists)
    <div class="card muted small">No content obligations yet. They are created automatically once this kit has an endorser, a delivery date, and a status of Delivered, Awaiting Pickup, or Returned.</div>
@endif
<script>
    // Show only the half of the form that applies to the chosen purpose. The
    // server nulls the other side regardless, so this is convenience, not the rule.
    (function () {
        var purpose = document.querySelector('[name="purpose"]');
        if (! purpose) return;

        function apply() {
            document.querySelectorAll('[data-when]').forEach(function (block) {
                block.hidden = block.dataset.when !== purpose.value;
            });
        }

        purpose.addEventListener('change', apply);
        apply();
    })();
</script>
@endsection
