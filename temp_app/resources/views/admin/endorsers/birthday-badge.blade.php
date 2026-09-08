{{-- Shown next to an endorser's name. Silent unless the greeting is close, so
     the list does not carry a date against every single person. --}}
@php
    $daysAway = $endorser->daysUntilBirthday();
@endphp
@if($daysAway !== null && $daysAway <= 30)
    <span class="bday {{ $daysAway === 0 ? 'today' : '' }}">
        @if($daysAway === 0)
            🎂 Birthday today
        @elseif($daysAway === 1)
            🎂 Birthday tomorrow
        @else
            🎂 {{ $endorser->nextBirthday()->format('M j') }}
        @endif
    </span>
@endif
