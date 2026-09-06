@extends('layouts.public')
@section('title', 'Event details')
@section('content')
<h1>Event details for review</h1>
@if(!$unlocked)
    <p>This link is password protected.</p>
    <form method="post" action="{{ route('client.event.unlock', $link->token, false) }}">@csrf
        <label for="password">Password</label><input id="password" type="password" name="password" required>
        <div class="actions"><button type="submit">View details</button></div>
    </form>
@else
    @foreach($link->visible_data as $label => $value)
        <div class="field"><strong>{{ str($label)->replace('_', ' ')->title() }}</strong>{{ is_array($value) ? implode(', ', $value) : $value }}</div>
    @endforeach
    <form method="post" action="{{ route('client.event.respond', $link->token, false) }}">@csrf
        <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label for="comment">Comment (optional)</label><textarea id="comment" name="comment" maxlength="3000">{{ old('comment') }}</textarea>
        <div class="actions">
            @if($link->allow_confirmation)<button name="response" value="confirmed">Confirm details</button>@endif
            @if($link->allow_change_request)<button class="alt" name="response" value="change_requested">Request changes</button>@endif
        </div>
    </form>
@endif
@endsection
