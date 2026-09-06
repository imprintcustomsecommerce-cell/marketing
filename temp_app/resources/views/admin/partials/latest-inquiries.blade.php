<section class="card">
    <div class="panel-head">
        <h2>Latest inquiries</h2>
        <a class="button ghost" href="{{ route('admin.inquiries.index') }}">Read all</a>
    </div>
    @forelse($recentInquiries as $inquiry)
        <div class="row">
            <span class="dot" style="{{ $inquiry->status === 'new_inquiry' ? '' : 'background:var(--line)' }}"></span>
            <span>
                <a class="nm" href="{{ route('admin.inquiries.show', $inquiry) }}">{{ $inquiry->subject() }}</a>
                <div class="meta">{{ App\Http\Controllers\Admin\InquiryController::TYPES[$inquiry->type] ?? str($inquiry->type)->replace('_', ' ')->title() }}</div>
            </span>
            <span class="when">{{ optional($inquiry->submitted_at)->diffForHumans(short: true) }}</span>
        </div>
    @empty
        <p class="muted small" style="margin:0">No public inquiries yet. Share the forms to start collecting them.</p>
    @endforelse
</section>
