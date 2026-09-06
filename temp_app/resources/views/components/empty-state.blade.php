@props([
    'title' => 'Nothing here yet',
    'message' => null,
    'icon' => 'box',
    'actionLabel' => null,
    'actionUrl' => null,
    'secondaryLabel' => null,
    'secondaryUrl' => null,
])

{{--
    A screen with no rows should say what belongs here and offer the way in,
    rather than showing an empty table with column headings over a blank strip.
--}}
<div {{ $attributes->merge(['class' => 'empty-state card']) }}>
    <span class="empty-state-icon" aria-hidden="true">
        @switch($icon)
            @case('calendar')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                @break
            @case('people')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 11.5a3 3 0 1 0-2-5.3M21.5 20a5.5 5.5 0 0 0-4-5.3"/></svg>
                @break
            @case('inbox')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16v12H7l-3 3z"/><path d="M8 9h8M8 12.5h5"/></svg>
                @break
            @case('search')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                @break
            @case('check')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                @break
            @default
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/></svg>
        @endswitch
    </span>

    <h2>{{ $title }}</h2>
    @if($message)<p>{{ $message }}</p>@endif

    @if($actionUrl || $secondaryUrl)
        <div class="empty-state-actions">
            @if($actionUrl)<a class="button" href="{{ $actionUrl }}">{{ $actionLabel ?? 'Get started' }}</a>@endif
            @if($secondaryUrl)<a class="button ghost" href="{{ $secondaryUrl }}">{{ $secondaryLabel ?? 'Back' }}</a>@endif
        </div>
    @endif

    {{ $slot }}
</div>
