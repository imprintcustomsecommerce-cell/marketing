@php
    $publicPortal = app(App\Support\PublicPortal::class);
    $publicBase = $publicPortal->url();
    $tunnelOnline = $publicPortal->tunnelIsActive();
    $portalStarted = $publicPortal->startedAt();
@endphp
<section class="card share-panel">
    <div class="share-heading">
        <span class="share-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.6 6.6-3.8M8.7 13.4l6.6 3.8"/></svg></span>
        <div>
            <h2>Share with clients</h2>
            <p class="share-copy">Send one link so clients can choose the form that fits their request.</p>
        </div>
    </div>
    @unless($tunnelOnline)
        <p class="meta" style="margin:0 0 12px">Restart RUN_IMPRINT_HUB.bat to create a shareable Cloudflare link.</p>
    @endunless
    @if($tunnelOnline)
        <div class="temporary-note"><strong>Temporary public address</strong><span>This link changes whenever the launcher restarts. Copy the latest one before sending.</span>@if($portalStarted)<time title="{{ $portalStarted->format('M j, Y g:i A') }}">Started {{ $portalStarted->diffForHumans() }}</time>@endif</div>
    @endif
    <div class="share-link-box">
        <span class="share-link-label">Client forms link</span>
        <a href="{{ $publicBase }}/client" target="_blank" rel="noopener">{{ $publicBase }}/client</a>
        <button type="button" data-copy="{{ $publicBase }}/client"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h3"/></svg><span>Copy link</span></button>
    </div>
    <details class="direct-links">
        <summary><span>Need a specific form?</span><strong>Show direct links</strong></summary>
        <ul class="links">
        @foreach([
            'Function Hall' => '/client/function-hall',
            'Tambike' => '/client/tambike',
            'Sponsorship' => '/client/sponsorship',
            'External Event' => '/client/external-event',
            'General Inquiry' => '/client/inquiry',
        ] as $name => $path)
            @php $url = $publicBase.$path; @endphp
            <li>
                <span>
                    <span class="nm">{{ $name }}</span>
                    <div class="pt"><a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></div>
                </span>
                <button type="button" data-copy="{{ $url }}">Copy</button>
            </li>
        @endforeach
        </ul>
    </details>
</section>
