@extends('layouts.admin')
@section('title', 'Inquiries')
@section('content')
<style>
    /* Unread inquiries are the one count worth shouting about. */
    .periods a .n.hot{opacity:1;color:#b91c1c;font-weight:800}


    .type{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid;white-space:nowrap}
    .type-function-hall_inquiry{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
    .type-tambike_inquiry{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .type-sponsorship_inquiry{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .type-external-event_inquiry{background:#fdf2f8;color:#9d174d;border-color:#fbcfe8}
    .type-event_inquiry{background:var(--line-soft);color:var(--ink-soft);border-color:var(--line)}

    .st{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;border:1px solid;white-space:nowrap}
    .st-new_inquiry{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
    .st-reviewing{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .st-quoted{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
    .st-confirmed{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
    .st-declined{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
    .st-closed{background:var(--line-soft);color:var(--muted);border-color:var(--line)}
    .priority{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.priority-high{color:#b45309}.priority-urgent{color:#b91c1c}
    .clip{width:14px;height:14px;vertical-align:-2px;color:var(--muted)}
    /* A date is one thing; broken over three lines it stops being readable. */
    table td:first-child,table th:first-child{white-space:nowrap;width:1%}
    /* Same for the contact block: a name and a phone number each belong on
       one line, and the subject column has the width to spare. */
    table td:nth-child(4){white-space:nowrap}

    /* Seven columns do not survive a phone, so below this each inquiry becomes
       a block — the same fallback the other lists have. */
    .iq-card{padding:15px 0;border-bottom:1px solid var(--line-soft)}
    .iq-card:last-child{border-bottom:0}
    .iq-card .top{display:flex;align-items:flex-start;gap:10px;justify-content:space-between}
    .iq-card .nm{font-weight:700;font-size:.98rem;color:inherit;text-decoration:none}
    .iq-card .nm:hover{text-decoration:underline}
    .iq-card .meta{color:var(--muted);font-size:.83rem;margin-top:4px}
    .iq-card .foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}

    @container (max-width:760px){
        .table-card table{display:none}
        .table-card .iq-cards{display:block}
    }
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Public forms</span>
        <h1>Inquiries</h1>
        <div class="muted small">Everything sent in through the public forms</div>
    </div>
</div>

<div class="list-head">
    <div class="periods">
        <a class="{{ $status === '' ? 'on' : '' }}" href="{{ route('admin.inquiries.index', array_filter(['type' => $type, 'priority' => $priority, 'follow_up' => $followUp, 'q' => $search])) }}">
            All<span class="n">{{ $counts->sum() }}</span>
        </a>
        @foreach(App\Http\Controllers\Admin\InquiryController::STATUSES as $value => $label)
            <a class="{{ $status === $value ? 'on' : '' }}" href="{{ route('admin.inquiries.index', array_filter(['status' => $value, 'type' => $type, 'priority' => $priority, 'follow_up' => $followUp, 'q' => $search])) }}">
                {{ $label }}<span class="n {{ $value === 'new_inquiry' && ($counts[$value] ?? 0) ? 'hot' : '' }}">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.inquiries.index') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="hidden" name="follow_up" value="{{ $followUp }}">
        <select name="type" onchange="this.form.submit()" aria-label="Form">
            <option value="">All forms</option>
            @foreach(App\Http\Controllers\Admin\InquiryController::TYPES as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="priority" onchange="this.form.submit()" aria-label="Priority">
            <option value="">All priorities</option>
            @foreach(App\Http\Controllers\Admin\InquiryController::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Name, email, venue…" aria-label="Search inquiries">
        <button class="button ghost" type="submit">Search</button>
        @if($status || $type || $priority || $followUp || $search !== '')
            <a class="edit" href="{{ route('admin.inquiries.index') }}">Clear</a>
        @endif
    </form>
</div>

<div class="card table-card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Received</th><th>Form</th><th>Subject</th><th>Contact</th><th>Status</th><th>Handled by</th><th></th></tr></thead>
        <tbody>
        @forelse($inquiries as $inquiry)
            <tr>
                <td>
                    {{ $inquiry->submitted_at?->format('M j, Y') ?: '—' }}
                    <div class="muted small">{{ $inquiry->submitted_at?->diffForHumans() }}</div>
                    <div class="muted small">{{ $inquiry->referenceNumber() }}</div>
                </td>
                <td><span class="type type-{{ $inquiry->type }}">{{ App\Http\Controllers\Admin\InquiryController::TYPES[$inquiry->type] ?? str($inquiry->type)->replace('_', ' ')->title() }}</span></td>
                <td>
                    <strong>{{ $inquiry->subject() }}</strong>
                    @if($inquiry->uploads)
                        <svg class="clip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Has attachments"><path d="M21 11.5 12.5 20a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5"/></svg>
                    @endif
                    <div class="muted small">{{ data_get($inquiry->data, 'email') }}</div>
                </td>
                <td>
                    {{ $inquiry->contactName() ?: '—' }}
                    <div class="muted small">{{ data_get($inquiry->data, 'contact_number') }}</div>
                </td>
                <td><span class="st st-{{ $inquiry->status }}">{{ App\Http\Controllers\Admin\InquiryController::STATUSES[$inquiry->status] ?? str($inquiry->status)->replace('_', ' ')->title() }}</span><div class="priority priority-{{ $inquiry->priority }}">{{ $inquiry->priority ?: 'normal' }}</div></td>
                <td class="muted small">
                    {{ $inquiry->assignee?->name ?: 'Unassigned' }}
                    @if($inquiry->follow_up_at)<div class="{{ $inquiry->follow_up_at->isPast() ? 'priority priority-urgent' : '' }}">Follow up {{ $inquiry->follow_up_at->diffForHumans() }}</div>@endif
                    @if($inquiry->converted_event_id || $inquiry->converted_endorser_id)
                        <div class="st st-confirmed" style="margin-top:4px">Converted</div>
                    @endif
                </td>
                <td><a class="edit" href="{{ route('admin.inquiries.show', $inquiry) }}">Read</a></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="empty">
                    @if($status || $type || $search !== '')
                        No inquiries match these filters.
                    @else
                        No inquiries yet. They land here the moment someone submits a public form.
                    @endif
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{-- The same rows as cards, for a column the table cannot fit. --}}
    <div class="stack-cards iq-cards">
        @foreach($inquiries as $inquiry)
            <div class="iq-card">
                <div class="top">
                    <a class="nm" href="{{ route('admin.inquiries.show', $inquiry) }}">
                        {{ $inquiry->subject() }}
                        @if($inquiry->uploads)
                            <svg class="clip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Has attachments"><path d="M21 11.5 12.5 20a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7.5-7.5"/></svg>
                        @endif
                    </a>
                    <span><span class="st st-{{ $inquiry->status }}">{{ App\Http\Controllers\Admin\InquiryController::STATUSES[$inquiry->status] ?? str($inquiry->status)->replace('_', ' ')->title() }}</span><span class="priority priority-{{ $inquiry->priority }}"> {{ $inquiry->priority ?: 'normal' }}</span></span>
                </div>
                <div class="meta">
                    {{ $inquiry->submitted_at?->format('M j, Y') ?: '—' }}
                    @if($inquiry->submitted_at) · {{ $inquiry->submitted_at->diffForHumans() }} @endif
                </div>
                <div class="meta">
                    {{ $inquiry->contactName() ?: 'No contact name' }}
                    @if(data_get($inquiry->data, 'contact_number')) · {{ data_get($inquiry->data, 'contact_number') }} @endif
                    @if(data_get($inquiry->data, 'email')) · {{ data_get($inquiry->data, 'email') }} @endif
                </div>
                <div class="foot">
                    <span class="type type-{{ $inquiry->type }}">{{ App\Http\Controllers\Admin\InquiryController::TYPES[$inquiry->type] ?? str($inquiry->type)->replace('_', ' ')->title() }}</span>
                    <span class="muted small">{{ $inquiry->assignee ? 'Assigned to '.$inquiry->assignee->name : 'Unassigned' }}</span>
                    @if($inquiry->converted_event_id || $inquiry->converted_endorser_id)
                        <span class="st st-confirmed">Converted</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
{{ $inquiries->links() }}
@endsection
