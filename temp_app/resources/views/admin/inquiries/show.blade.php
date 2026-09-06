@extends('layouts.admin')
@section('title', 'Inquiry · '.$inquiry->subject())
@section('content')
<style>
    .split{display:grid;grid-template-columns:1.7fr 1fr;gap:18px;align-items:start}
    @media(max-width:1000px){.split{grid-template-columns:1fr}}

    /* The submitted answers, laid out as a definition list so long free-text
       fields get room without wrecking the alignment of the short ones. */
    .answers{display:grid;grid-template-columns:200px 1fr;gap:0}
    @media(max-width:640px){.answers{grid-template-columns:1fr}}
    .answers dt{padding:11px 0;border-bottom:1px solid var(--line-soft);font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);font-weight:700}
    .answers dd{padding:11px 0;border-bottom:1px solid var(--line-soft);margin:0;white-space:pre-line;word-break:break-word}
    @media(max-width:640px){.answers dt{border-bottom:0;padding-bottom:2px}}
    .answers dd:last-of-type,.answers dt:last-of-type{border-bottom:0}
    .answers .empty-val{color:#9ca3af}
    .yes{color:#047857;font-weight:700}
    .no{color:#b91c1c;font-weight:700}

    .files{list-style:none;margin:0;padding:0;display:grid;gap:8px}
    .files li{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:11px;background:var(--canvas)}
    .files .nm{font-size:.86rem;font-weight:650;word-break:break-all}
    .files a{margin-left:auto;white-space:nowrap}

    .meta-list{list-style:none;margin:0;padding:0;font-size:.85rem}
    .meta-list li{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line-soft)}
    .meta-list li:last-child{border-bottom:0}
    .meta-list .k{color:var(--muted);flex:none;width:96px}
    .meta-list .v{word-break:break-word}

    .st{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid}
    .st-new_inquiry{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .st-reviewing{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .st-quoted{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
    .st-confirmed{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
    .st-declined{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
    .st-closed{background:var(--line-soft);color:var(--muted);border-color:var(--line)}

    .convert{border-color:#a7f3d0;background:#f6fdf9}
    .convert p{margin:0 0 14px;font-size:.88rem;color:var(--ink-soft)}
    .converted{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:.9rem}
    .converted .tick{width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:grid;place-items:center;flex:none}
    .converted .tick svg{width:15px;height:15px}
    .duplicate-warning{border-color:#fbbf24;background:#fffbeb}
    .duplicate-warning ul{margin:10px 0 0;padding-left:20px}
    .quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
    .quick-actions form{margin:0}
    .quick-actions .danger{color:#b91c1c;border-color:#fecaca;background:#fff}
    .handling-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}.handling-grid .full{grid-column:1/-1}
    @media(max-width:560px){.handling-grid{grid-template-columns:1fr}.handling-grid .full{grid-column:auto}}
    .timeline{list-style:none;margin:0;padding:0}.timeline li{position:relative;padding:0 0 18px 22px;border-left:2px solid var(--line)}
    .timeline li:last-child{padding-bottom:0;border-left-color:transparent}.timeline li::before{content:"";position:absolute;left:-6px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--accent)}
    .timeline .time{font-size:.75rem;color:var(--muted)}.timeline .note{white-space:pre-line;margin-top:3px}
    .template-box{width:100%;min-height:118px;margin-top:10px}
</style>

<div class="topline">
    <div>
        <h1>{{ $inquiry->subject() }}</h1>
        <div class="muted small">
            {{ App\Http\Controllers\Admin\InquiryController::TYPES[$inquiry->type] ?? str($inquiry->type)->replace('_', ' ')->title() }}
            · {{ $inquiry->referenceNumber() }}
            · received {{ $inquiry->submitted_at?->format('M j, Y \a\t g:ia') }}
            · <span class="st st-{{ $inquiry->status }}">{{ App\Http\Controllers\Admin\InquiryController::STATUSES[$inquiry->status] ?? $inquiry->status }}</span>
        </div>
    </div>
    <a class="edit" href="{{ route('admin.inquiries.index') }}">Back to inquiries</a>
</div>

<div class="split">
    <div>
        <section class="card">
            <div class="topline" style="margin-bottom:6px"><h2>What they sent</h2></div>
            <dl class="answers">
                @forelse($inquiry->data ?? [] as $field => $value)
                    <dt>{{ str($field)->replace('_', ' ')->title() }}</dt>
                    <dd>
                        @if($value === null || $value === '')
                            <span class="empty-val">Not answered</span>
                        @elseif(is_array($value))
                            {{ implode(', ', $value) }}
                        @elseif($value === 'yes')
                            <span class="yes">Yes</span>
                        @elseif($value === 'no')
                            <span class="no">No</span>
                        @elseif($field === 'email')
                            <a href="mailto:{{ $value }}">{{ $value }}</a>
                        @elseif($field === 'contact_number')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $value) }}">{{ $value }}</a>
                        @elseif(str_starts_with((string) $value, 'http'))
                            <a href="{{ $value }}" target="_blank" rel="noopener noreferrer">{{ $value }}</a>
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                @empty
                    <dd class="empty-val">This submission carried no answers.</dd>
                @endforelse
            </dl>
        </section>

        @php $attachments = $inquiry->attachments(); @endphp
        @if($attachments)
            <section class="card">
                <div class="topline" style="margin-bottom:12px">
                    <h2>Attachments</h2>
                    <span class="muted small">{{ count($attachments) }} {{ Str::plural('file', count($attachments)) }}</span>
                </div>
                <ul class="files">
                    @foreach($attachments as $index => $attachment)
                        <li>
                            <svg style="width:16px;height:16px;color:var(--muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v5h5M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/></svg>
                            <span class="nm">
                                {{ $attachment['name'] }}
                                @if($attachment['size'])<span class="muted small">({{ round($attachment['size'] / 1024) }} KB)</span>@endif
                            </span>
                            <a class="edit" href="{{ route('admin.inquiries.download', [$inquiry, $index]) }}">Download</a>
                        </li>
                    @endforeach
                </ul>
                <p class="muted small" style="margin:12px 0 0">Files are stored privately and only reachable while signed in.</p>
            </section>
        @endif

        <section class="card">
            <div class="topline" style="margin-bottom:12px"><h2>Contact timeline</h2></div>
            <form method="post" action="{{ route('admin.inquiries.contacts.store', $inquiry) }}">@csrf
                <div class="handling-grid">
                    <div><label for="contact_type">Activity</label><select id="contact_type" name="type">@foreach(App\Http\Controllers\Admin\InquiryController::CONTACT_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="full"><label for="contact_note">What happened?</label><textarea id="contact_note" name="note" required placeholder="Called the client; they will send the final guest count tomorrow."></textarea></div>
                </div>
                <div style="margin-top:12px"><button class="button" type="submit">Add activity</button></div>
            </form>

            @if($inquiry->contactLogs->isNotEmpty())
                <ul class="timeline" style="margin-top:22px">
                    @foreach($inquiry->contactLogs as $log)
                        <li><strong>{{ App\Http\Controllers\Admin\InquiryController::CONTACT_TYPES[$log->type] ?? str($log->type)->title() }}</strong><div class="time">{{ $log->user?->name ?? 'Former staff member' }} · {{ $log->created_at->format('M j, Y g:ia') }}</div><div class="note">{{ $log->note }}</div></li>
                    @endforeach
                </ul>
            @else
                <p class="muted small" style="margin:18px 0 0">No calls, messages, or follow-ups recorded yet.</p>
            @endif
        </section>
    </div>

    <div>
        @if($duplicateEndorsers->isNotEmpty() || $duplicateInquiries->isNotEmpty())
            <section class="card duplicate-warning">
                <div class="topline" style="margin-bottom:6px"><h2>Possible duplicate</h2></div>
                <p class="small" style="margin:0">The submitted email address or mobile number matches an existing record.</p>
                <ul class="small">
                    @foreach($duplicateEndorsers as $endorser)
                        <li>Endorser: <a class="edit" href="{{ route('admin.endorsers.edit', $endorser) }}">{{ $endorser->name }}</a></li>
                    @endforeach
                    @foreach($duplicateInquiries as $duplicate)
                        <li>Previous inquiry: <a class="edit" href="{{ route('admin.inquiries.show', $duplicate) }}">{{ $duplicate->referenceNumber() }} · {{ $duplicate->subject() }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($inquiry->convertedEvent || $inquiry->convertedEndorser)
            <section class="card convert">
                <div class="topline" style="margin-bottom:10px"><h2>Converted</h2></div>
                <div class="converted">
                    <span class="tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L19 8"/></svg></span>
                    @if($inquiry->convertedEvent)
                        <span>Now an event: <a class="edit" href="{{ route('admin.events.edit', $inquiry->convertedEvent) }}">{{ $inquiry->convertedEvent->name }}</a></span>
                    @else
                        <span>Now an endorser: <a class="edit" href="{{ route('admin.endorsers.edit', $inquiry->convertedEndorser) }}">{{ $inquiry->convertedEndorser->name }}</a></span>
                    @endif
                </div>
            </section>
        @elseif($canBecomeEvent || $canBecomeEndorser)
            <section class="card convert">
                <div class="topline" style="margin-bottom:10px">
                    <h2>{{ $canBecomeEvent ? 'Turn into an event' : 'Turn into an endorser' }}</h2>
                </div>
                <p>
                    @if($canBecomeEvent)
                        Copies the date, venue, headcount, and their notes onto a new event, then opens it so you can finish the details.
                    @else
                        Copies the rider's profile, achievements, and contact details onto a new endorser record.
                    @endif
                </p>
                @if($canBecomeEvent)
                    <form method="post" action="{{ route('admin.inquiries.convert', $inquiry) }}">@csrf
                        <button class="button" type="submit">Accept as event</button>
                    </form>
                @else
                    <div class="quick-actions">
                        <form method="post" action="{{ route('admin.inquiries.convert', $inquiry) }}">@csrf
                            <input type="hidden" name="conversion" value="endorser">
                            <button class="button" type="submit">Accept as endorser</button>
                        </form>
                        <form method="post" action="{{ route('admin.inquiries.convert', $inquiry) }}">@csrf
                            <input type="hidden" name="conversion" value="partner">
                            <button class="button ghost" type="submit">Accept as partner</button>
                        </form>
                    </div>
                @endif
            </section>
        @endif

        <section class="card">
            <div class="topline" style="margin-bottom:6px"><h2>Response templates</h2></div>
            <select id="response_template">
                @foreach($responseTemplates as $key => [$label, $message])<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <textarea class="template-box" id="response_text" readonly></textarea>
            <button class="button ghost" id="copy_response" type="button">Copy response</button>
            <p class="muted small" style="margin:10px 0 0">Copy, personalize, and send it using your usual email or messaging app.</p>
        </section>

        <form class="card" method="post" action="{{ route('admin.inquiries.update', $inquiry) }}">@csrf @method('put')
            <div class="topline" style="margin-bottom:6px"><h2>Handling</h2></div>

            <div class="handling-grid">
                <div><label for="status">Status</label><select id="status" name="status" required>@foreach(App\Http\Controllers\Admin\InquiryController::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $inquiry->status) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="priority">Priority</label><select id="priority" name="priority" required>@foreach(App\Http\Controllers\Admin\InquiryController::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected(old('priority', $inquiry->priority ?: 'normal') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="assigned_to">Assigned to</label><select id="assigned_to" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to', $inquiry->assigned_to) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                <div><label for="follow_up_at">Next follow-up</label><input id="follow_up_at" type="datetime-local" name="follow_up_at" value="{{ old('follow_up_at', $inquiry->follow_up_at?->format('Y-m-d\TH:i')) }}"></div>
            </div>

            <label for="internal_notes">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" placeholder="Quoted ₱18,000, waiting on their confirmation.">{{ old('internal_notes', $inquiry->internal_notes) }}</textarea>
            <label for="public_update">Update visible to client</label>
            <textarea id="public_update" name="public_update" maxlength="1000" placeholder="Example: We are checking venue availability and will update you tomorrow.">{{ old('public_update', $inquiry->public_update) }}</textarea>
            <div class="muted small">Shown only when the client tracks this inquiry using their reference and matching email. Do not include internal notes.</div>
            <span class="muted small">Only staff see this. The sender never does.</span>

            <div style="margin-top:16px"><button class="button" type="submit">Save</button></div>

            @if($inquiry->handled_at)
                <p class="muted small" style="margin:14px 0 0">
                    Last updated by {{ $inquiry->handler?->name ?? 'a removed account' }} {{ $inquiry->handled_at->diffForHumans() }}.
                </p>
            @endif
        </form>

        @if(! in_array($inquiry->status, ['confirmed', 'declined', 'closed'], true))
            <section class="card">
                <div class="topline" style="margin-bottom:6px"><h2>Quick actions</h2></div>
                <div class="quick-actions">
                    <form method="post" action="{{ route('admin.inquiries.action', $inquiry) }}">@csrf @method('patch')
                        <input type="hidden" name="action" value="request_information">
                        <button class="button ghost" type="submit">Request more information</button>
                    </form>
                    <form method="post" action="{{ route('admin.inquiries.action', $inquiry) }}">@csrf @method('patch')
                        <input type="hidden" name="action" value="decline">
                        <button class="button ghost danger" type="submit">Decline</button>
                    </form>
                </div>
            </section>
        @endif

        <section class="card">
            <div class="topline" style="margin-bottom:6px"><h2>Contact</h2></div>
            <ul class="meta-list">
                @if(data_get($inquiry->data, 'email'))
                    <li><span class="k">Email</span><span class="v"><a href="mailto:{{ data_get($inquiry->data, 'email') }}">{{ data_get($inquiry->data, 'email') }}</a></span></li>
                @endif
                @if(data_get($inquiry->data, 'contact_number'))
                    <li><span class="k">Phone</span><span class="v"><a href="tel:{{ preg_replace('/[^0-9+]/', '', data_get($inquiry->data, 'contact_number')) }}">{{ data_get($inquiry->data, 'contact_number') }}</a></span></li>
                @endif
                <li><span class="k">Received</span><span class="v">{{ $inquiry->submitted_at?->format('M j, Y g:ia') }}</span></li>
                <li><span class="k">From IP</span><span class="v">{{ $inquiry->ip_address ?: 'Not recorded' }}</span></li>
            </ul>
        </section>
    </div>
</div>
<script>
    (function () {
        var templates = @json($responseTemplates);
        var picker = document.getElementById('response_template');
        var text = document.getElementById('response_text');
        var copy = document.getElementById('copy_response');
        if (! picker || ! text || ! copy) return;
        function selectTemplate() { text.value = templates[picker.value][1]; }
        picker.addEventListener('change', selectTemplate);
        copy.addEventListener('click', function () {
            navigator.clipboard.writeText(text.value).then(function () {
                copy.textContent = 'Copied';
                setTimeout(function () { copy.textContent = 'Copy response'; }, 1400);
            });
        });
        selectTemplate();
    })();
</script>
@endsection
