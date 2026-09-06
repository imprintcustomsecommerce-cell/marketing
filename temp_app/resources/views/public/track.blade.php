@extends('layouts.public')
@section('title', 'Track your inquiry')
@section('content')
<span class="eyebrow">Inquiry status</span>
<h1>Track your request</h1>
<p class="lede">Enter the reference and email address used on the form.</p>

<form method="post" action="{{ route('client.track.lookup') }}">@csrf
    <label for="reference">Reference number <span class="req">*</span></label>
    <input id="reference" name="reference" value="{{ old('reference') }}" placeholder="IMP-20260906-000001" required>
    <label for="email">Email address <span class="req">*</span></label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required>
    <div class="actions"><button type="submit">Check status</button></div>
</form>

@isset($lookedUp)
    <hr class="sep">
    @if($submission)
        @php $labels = ['new_inquiry' => 'Received', 'contacted' => 'Under review', 'follow_up' => 'Waiting for follow-up', 'confirmed' => 'Approved', 'declined' => 'Not approved', 'closed' => 'Closed']; @endphp
        <div class="notice"><strong>{{ $labels[$submission->status] ?? str($submission->status)->headline() }}</strong><span style="display:block;font-weight:400;color:var(--ink-soft)">{{ $submission->subject() }} · submitted {{ $submission->submitted_at->format('M j, Y') }}</span>@if($submission->public_update)<span style="display:block;margin-top:9px;padding-top:9px;border-top:1px solid #a7f3d0;font-weight:500;color:var(--ink)">{{ $submission->public_update }}</span>@endif</div>
    @else
        <div class="errors">We could not match that reference and email. Check both entries and try again.</div>
    @endif
@endisset
@endsection
