@extends('layouts.admin')
@section('title', $event->exists ? 'Edit Event' : 'New Event')
@section('content')
<div class="topline">
    <div>
        <span class="page-kicker">{{ $event->exists ? 'Event record' : 'New record' }}</span>
        <h1>{{ $event->exists ? 'Edit '.$event->name : 'Add a new event' }}</h1>
        <div class="muted small">Capture the schedule, venue, attendance, and coordination details.</div>
    </div>
</div>

<form class="record-form" method="post" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
    @csrf
    @if($event->exists)@method('put')@endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v14H4zM4 9h16M8 3v4M16 3v4"/></svg></span>
            <span><h2>Event information</h2><p>The core information staff use to identify and categorize this event.</p></span>
        </div>
        <div class="form-grid">
            <div class="span-2"><label for="name">Event name <span class="required">*</span></label><input id="name" name="name" value="{{ old('name', $event->name) }}" placeholder="e.g. Tambike Night Ride" required autofocus></div>
            @php
                $eventTypes = ['in_house' => 'IN-HOUSE', 'outside_event' => 'OUTSIDE EVENT', 'tambike' => 'TAMBIKE'];
                $eventCategories = ['motorcycle' => 'Motorcycle', 'automotive' => 'Automotive', 'car' => 'Car', 'others' => 'Others'];
            @endphp
            <div><label for="event_type">Event type</label><select id="event_type" name="event_type" required>@foreach($eventTypes as $value => $label)<option value="{{ $value }}" @selected(old('event_type', $event->event_type ?: 'tambike') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="event_category">Category</label><select id="event_category" name="event_category" required>@foreach($eventCategories as $value => $label)<option value="{{ $value }}" @selected(old('event_category', $event->event_category ?: 'others') === $value)>{{ $label }}</option>@endforeach</select></div>
            {{-- Not on screen, but still posted on every save: carry the stored value
                 through or editing an event silently retags it as a tambike. --}}
            <input type="hidden" name="category" value="{{ old('category', $event->category ?: 'tambike') }}">
            <div><label for="status">Status <span class="required">*</span></label><select id="status" name="status" required>@foreach(['new','pending','confirmed','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', $event->status ?: 'new') === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>
            <div><label for="organization">Organization / group name</label><input id="organization" name="organization" value="{{ old('organization', $event->organization) }}" placeholder="Client, club, group, or partner"></div>
            <div><label for="estimated_pax">Estimated attendance</label><input id="estimated_pax" type="number" min="1" name="estimated_pax" value="{{ old('estimated_pax', $event->estimated_pax) }}" placeholder="Expected guests"></div>
            <div><label for="contact_person">Contact person</label><input id="contact_person" name="contact_person" value="{{ old('contact_person', $event->contact_person) }}" placeholder="Client representative"></div>
            <div><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $event->contact_number) }}" placeholder="09XX XXX XXXX"></div>
            <div class="span-2"><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $event->contact_email) }}" placeholder="client@example.com"></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <span><h2>Schedule and location</h2><p>When and where the team needs to be.</p></span>
        </div>
        <div class="form-grid">
            <div><label for="event_date">Event date <span class="required">*</span></label><input id="event_date" type="date" name="event_date" value="{{ old('event_date', optional($event->event_date)->format('Y-m-d')) }}" required></div>
            <div><label for="venue">Venue</label><input id="venue" name="venue" value="{{ old('venue', $event->venue) }}" placeholder="Venue or meeting point"></div>
            <div><label for="start_time">Start time</label><input id="start_time" type="time" name="start_time" value="{{ old('start_time', $event->start_time) }}"></div>
            <div><label for="end_time">End time</label><input id="end_time" type="time" name="end_time" value="{{ old('end_time', $event->end_time) }}"></div>
            <div><label for="ingress_date">Ingress date</label><input id="ingress_date" type="date" name="ingress_date" value="{{ old('ingress_date', optional($event->ingress_date)->format('Y-m-d')) }}"><span class="field-help">Load-in day, if the booth goes up before event day.</span></div>
            <div><label for="egress_date">Egress date</label><input id="egress_date" type="date" name="egress_date" value="{{ old('egress_date', optional($event->egress_date)->format('Y-m-d')) }}"><span class="field-help">Load-out day.</span></div>
            <div><label for="duration_days">How many days</label><input id="duration_days" type="number" min="1" max="60" name="duration_days" value="{{ old('duration_days', $event->duration_days) }}" placeholder="1"><span class="field-help">How long the event runs.</span></div>
            <div><label for="booth_size">Booth size</label><input id="booth_size" name="booth_size" value="{{ old('booth_size', $event->booth_size) }}" placeholder="e.g. 3m x 3m"></div>
            <div><label for="venue_type">Indoor or outdoor</label><select id="venue_type" name="venue_type"><option value="">Not stated</option>@foreach(App\Models\Event::VENUE_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('venue_type', $event->venue_type) === $value)>{{ $label }}</option>@endforeach</select></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10h18M6 6h12l2 4v9H4v-9z"/><path d="M10 14h4"/></svg></span>
            <span><h2>Terms and preparation</h2><p>What the deal is worth, and what has to be ready before the van leaves.</p></span>
        </div>
        <div class="form-grid">
            <div><label for="deal_type">Ex-deal or full cash</label><select id="deal_type" name="deal_type"><option value="">Not stated</option>@foreach(App\Models\Event::DEAL_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('deal_type', $event->deal_type) === $value)>{{ $label }}</option>@endforeach</select></div>
            {{-- Only means anything on a cash deal, so it follows that choice. --}}
            <div data-show-when="deal_type" data-show-value="cash">
                <label for="cash_amount">How much <span class="required">*</span></label>
                <input id="cash_amount" type="number" step="0.01" min="0" name="cash_amount" value="{{ old('cash_amount', $event->cash_amount) }}" placeholder="0.00">
                <span class="field-help">Agreed cash amount, in pesos.</span>
            </div>
            <div class="span-2">
                <label>What this event needs</label>
                <span class="field-help" style="margin:2px 0 8px">Pick what has to be sorted. Tick them off as done later, on the event page.</span>
                <div class="prep-list">
                    @foreach(App\Models\Event::PREPARATION as $key => $label)
                        @php
                            $ticked = in_array($key, old('preparation', $event->preparation ?? []), true);
                        @endphp
                        <label class="prep-item"><input type="checkbox" name="preparation[]" value="{{ $key }}" @checked($ticked)> <span>{{ $label }}</span></label>
                    @endforeach
                </div>
                {{-- Anything this one event needs that the standing list does not
                     cover. Rows are plain markup so they submit without script;
                     the button below only clones one. --}}
                <div class="prep-list" id="custom-prep">
                    @php
                        $customItems = old('custom_preparation', $event->customPreparation());
                    @endphp
                    @foreach($customItems as $index => $item)
                        @php
                            $label = is_array($item) ? ($item['label'] ?? '') : '';
                            $done = is_array($item) && ! empty($item['done']);
                        @endphp
                        @if(filled($label))
                            <label class="prep-item custom">
                                {{-- Carried through untouched: whether it is sorted
                                     is decided on the checklist, not here. --}}
                                <input type="hidden" name="custom_preparation[{{ $index }}][done]" value="{{ $done ? 1 : 0 }}">
                                <input class="prep-text" type="text" name="custom_preparation[{{ $index }}][label]" value="{{ $label }}" maxlength="120">
                                <button type="button" class="prep-remove" aria-label="Remove item" title="Remove">&times;</button>
                            </label>
                        @endif
                    @endforeach
                </div>
                <button type="button" class="button ghost" id="add-prep" style="margin-top:9px">+ Add item</button>
                <span class="field-help">Leave everything unticked if none of it applies. Clearing an item's text removes it on save.</span>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L7 21l.6-3.2A8.4 8.4 0 1 1 21 11.5z"/></svg></span>
            <span><h2>Coordination</h2><p>Keep the working conversation and internal context attached to the event.</p></span>
        </div>
        <div class="form-grid">
            <div class="span-2">
                <label class="publish-toggle">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $event->is_public))>
                    <span>
                        <strong>Show on the website calendar</strong>
                        <span class="field-help">Customers see the name, date, time, venue, and the blurb below. Contact details, notes, terms, and the checklist never leave the Hub.</span>
                    </span>
                </label>
            </div>
            <div class="span-2"><label for="public_summary">Website blurb</label><textarea id="public_summary" name="public_summary" maxlength="600" placeholder="A line or two for customers reading the website…">{{ old('public_summary', $event->public_summary) }}</textarea><span class="field-help">Optional. Shown under the event on the website.</span></div>
            <div class="span-2"><label for="group_chat_url">Group chat link</label><input id="group_chat_url" type="url" name="group_chat_url" value="{{ old('group_chat_url', $event->group_chat_url) }}" placeholder="https://m.me/j/..."><span class="field-help">Messenger, Viber, or WhatsApp coordination thread.</span></div>
            <div class="span-2"><label for="notes">Internal notes</label><textarea id="notes" name="notes" placeholder="Requirements, reminders, or important context…">{{ old('notes', $event->notes) }}</textarea></div>
        </div>
    </section>

    <div class="form-actions">
        <a class="button ghost" href="{{ route('admin.events.index') }}">Cancel</a>
        <button class="button" type="submit">{{ $event->exists ? 'Save event' : 'Create event' }}</button>
    </div>
</form>

@if($event->exists)
    {{-- The events list sends "Edit" straight here, so somebody wanting rid of
         an event never passes the detail page where these used to be the only
         place they lived. Outside the form above: a nested form is invalid
         markup and the browser drops it. --}}
    <div class="danger-row">
        <div>
            <strong>Finished with this event?</strong>
            <div class="muted small">Archiving hides it from the lists and reminders but keeps the record.</div>
        </div>
        <div class="danger-actions">
            @unless($event->archived_at)
                <form method="post" action="{{ route('admin.events.archive', $event) }}">
                    @csrf @method('patch')
                    <button class="button ghost" type="submit">Archive event</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.events.restore', $event) }}">
                    @csrf @method('patch')
                    <button class="button ghost" type="submit">Restore event</button>
                </form>
            @endunless
            @if(auth()->user()->isAdmin())
                <form method="post" action="{{ route('admin.events.destroy', $event) }}"
                      data-confirm="Delete &quot;{{ $event->name }}&quot; for good?"
                      data-confirm-detail="Its coverage, production tasks, and uploaded files go with it. This cannot be undone. Archive it instead if you only want it out of the way."
                      data-confirm-action="Delete">
                    @csrf @method('delete')
                    <button class="button ghost danger" type="submit">Delete permanently</button>
                </form>
            @endif
        </div>
    </div>
@endif
<style>
    .prep-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px 18px;margin-top:6px}
    .prep-item{display:flex;align-items:center;gap:9px;font-weight:500;font-size:.9rem;
        padding:7px 10px;border:1px solid var(--line);border-radius:var(--radius);background:#fff;cursor:pointer}
    .prep-item:hover{border-color:var(--accent)}
    .prep-item input{width:16px;height:16px;min-height:0;flex:none;accent-color:var(--accent);margin:0}
    .prep-item span{line-height:1.3}
    .publish-toggle{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;border:1px solid var(--line);
        border-radius:var(--radius);background:#fff;cursor:pointer}
    .publish-toggle:hover{border-color:var(--accent)}
    .publish-toggle input{width:17px;height:17px;min-height:0;flex:none;margin:2px 0 0;accent-color:var(--accent)}
    .publish-toggle .field-help{margin-top:3px}
    .danger-row{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
        margin-top:20px;padding:16px 18px;border:1px solid #fecaca;border-radius:var(--radius);background:#fef2f2}
    .danger-actions{display:flex;gap:9px;flex-wrap:wrap}
    .danger-row .button.ghost{background:#fff}
    .danger-row .button.ghost.danger{color:#b91c1c;border-color:#fecaca}
    .danger-row .button.ghost.danger:hover{background:#fee2e2}
    .prep-item.custom{gap:7px}
    .prep-text{flex:1;min-width:0;border:0;background:none;padding:0;font:inherit;font-size:.9rem;font-weight:500;min-height:0}
    .prep-text:focus{outline:none;box-shadow:none}
    .prep-remove{border:0;background:none;color:var(--muted);font-size:1.15rem;line-height:1;cursor:pointer;padding:0 2px;min-height:0}
    .prep-remove:hover{color:#b91c1c}
</style>
<script>
    // Adding a row is a browser convenience; the rows themselves are ordinary
    // inputs, so a saved checklist survives with script switched off.
    (function () {
        var list = document.getElementById('custom-prep');
        var add = document.getElementById('add-prep');
        if (! list || ! add) return;

        // Carry on from the highest index already on the page, so a new row
        // never collides with an existing one and overwrite it.
        var next = list.querySelectorAll('.prep-item.custom').length;

        add.addEventListener('click', function () {
            var row = document.createElement('label');
            row.className = 'prep-item custom';
            row.innerHTML =
                '<input type="hidden" name="custom_preparation[' + next + '][done]" value="0">' +
                '<input class="prep-text" type="text" name="custom_preparation[' + next + '][label]" maxlength="120" placeholder="What else is needed?">' +
                '<button type="button" class="prep-remove" aria-label="Remove item" title="Remove">&times;</button>';
            list.appendChild(row);
            row.querySelector('.prep-text').focus();
            next++;
        });

        // One listener on the list, so it covers rows added after page load.
        list.addEventListener('click', function (event) {
            if (! event.target.classList.contains('prep-remove')) return;
            event.preventDefault();
            event.target.closest('.prep-item').remove();
        });
    })();

    // "How much" only applies to a cash deal. Disabled as well as hidden, so an
    // amount typed and then switched to ex-deal is not posted anyway.
    document.querySelectorAll('[data-show-when]').forEach(function (section) {
        var controller = document.querySelector('[name="' + section.dataset.showWhen + '"]');
        if (! controller) return;

        function sync() {
            var visible = controller.value === section.dataset.showValue;
            section.hidden = ! visible;
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = ! visible;
            });
        }

        controller.addEventListener('change', sync);
        sync();
    });
</script>
@endsection




