@extends('layouts.public')
@section('title', 'Work with Imprint Customs')
@section('meta_description', 'Book the function hall, bring Tambike to your event, or apply for rider sponsorship with Imprint Customs.')
@section('content')
<style>
    .hero{display:flex;gap:20px;align-items:center;flex-wrap:wrap}
    .hero-mark{width:96px;height:96px;flex:none}
    .hero h1{margin-bottom:0}
    @media(min-width:700px){.hero-mark{width:112px;height:112px}}
</style>
<div class="hero">
    <img class="hero-mark" src="{{ asset('images/imprint-customs-mark-512.png') }}" alt="Imprint Customs" width="112" height="112">
    <div>
        <span class="eyebrow">Work with us</span>
        <h1>Let's build something with your riders.</h1>
    </div>
</div>
<p class="lede">Pick the service you need below. Each form goes straight to our team, and we come back to you with the next step.</p>

<div class="tiles">
    <a class="tile" href="{{ route('client.function-hall', [], false) }}">
        <strong>Function Hall</strong>
        <span>Reserve our hall for ride-outs, launches, birthdays, and company events.</span>
    </a>
    <a class="tile" href="{{ route('client.tambike', [], false) }}">
        <strong>Tambike at your event</strong>
        <span>Booth setup, raffle prizes, and marketing support for your ride or rally.</span>
    </a>
    <a class="tile" href="{{ route('client.sponsorship', [], false) }}">
        <strong>Rider &amp; team sponsorship</strong>
        <span>Race under the Imprint Customs banner. Open to individual racers and teams.</span>
    </a>
    <a class="tile" href="{{ route('client.external-event', [], false) }}">
        <strong>External event sponsorship</strong>
        <span>Invite us to sponsor or partner on your ride-out, race, or expo.</span>
    </a>
    <a class="tile full" href="{{ route('client.inquiry', [], false) }}">
        <strong>Something else</strong>
        <span>Send a general inquiry and we will route it to the right team.</span>
    </a>
</div>

<hr class="sep">
<h2>What happens after you send</h2>
<ul class="points">
    <li>Your inquiry lands with the Imprint Customs team the moment you submit.</li>
    <li>We review the details and check availability before replying.</li>
    <li>Nothing is booked or confirmed until we come back to you in writing.</li>
</ul>
<p style="margin-top:22px"><a class="tile" href="{{ route('client.track') }}"><strong>Already sent a form?</strong><span>Track it securely with your reference number and email.</span></a></p>
@endsection
