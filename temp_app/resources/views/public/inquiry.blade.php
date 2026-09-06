@extends('layouts.public')
@section('title', $form['title'])
@section('meta_description', $form['lede'])
@section('content')
<span class="eyebrow">Inquiry form</span>
<h1>{{ $form['title'] }}</h1>
<p class="lede">{{ $form['lede'] }}</p>
<ul class="points">
    @foreach($form['points'] as $point)<li>{{ $point }}</li>@endforeach
</ul>
<hr class="sep">

<h2>Your details</h2>
<p class="lede" style="font-size:.92rem">Fields marked <span class="req">*</span> are required. Sending this form starts a conversation; it does not confirm a booking or sponsorship.</p>

<form id="client-inquiry-form" method="post" enctype="multipart/form-data" data-draft-key="imprint-draft:{{ $type }}" data-submitted="{{ session('success') ? 'yes' : 'no' }}">@csrf
    <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="grid">
    @foreach($form['fields'] as $field)
        @php
            $isLong = in_array($field, ['notes','special_requests','profile','achievements','requested_sponsorship','booth_requirements','raffle_requirements','marketing_support_details','sponsorship_requirements','requirements','address','social_media_urls']);
            $isDate = in_array($field, ['date','end_date','preferred_date','birthday']);
            $isTime = in_array($field, ['start_time','end_time']);
            $isBool = in_array($field, ['existing_client','need_booth','need_raffle','need_marketing_support','can_set_up_booth']);
            $isChoice = $field === 'relationship_type';
            $optional = in_array($field, ['notes','special_requests','estimated_pax','end_date','requirements']);
            $label = $labels[$field] ?? str($field)->replace('_', ' ')->title();
            $hint = $help[$field] ?? null;
            $type_attr = $field === 'email' ? 'email' : ($field === 'contact_number' ? 'tel' : ($isDate ? 'date' : ($isTime ? 'time' : ($field === 'estimated_pax' ? 'number' : 'text'))));
            $wide = $isLong || $isBool;
            $condition = $type === 'tambike' ? match ($field) {
                'relationship_type', 'relationship_period', 'previous_campaign', 'previous_contact' => ['existing_client', 'yes'],
                'booth_requirements' => ['need_booth', 'yes'],
                'raffle_requirements' => ['need_raffle', 'yes'],
                'marketing_support_details' => ['need_marketing_support', 'yes'],
                default => null,
            } : null;
        @endphp
        <div class="{{ $wide ? 'full' : '' }}" @if($condition) data-show-when="{{ $condition[0] }}" data-show-value="{{ $condition[1] }}" @endif>
            <label for="{{ $field }}">{{ $label }} @unless($optional)<span class="req" aria-hidden="true">*</span>@endunless
                @if($hint)<span class="help">{{ $hint }}</span>@endif
            </label>
            @if($isLong)
                <textarea id="{{ $field }}" name="{{ $field }}" @unless($optional) required @endunless>{{ old($field) }}</textarea>
            @elseif($isBool)
                <select id="{{ $field }}" name="{{ $field }}" required>
                    <option value="">Select an answer</option>
                    <option value="yes" @selected(old($field) === 'yes')>Yes</option>
                    <option value="no" @selected(old($field) === 'no')>No</option>
                </select>
            @elseif($isChoice)
                <select id="{{ $field }}" name="{{ $field }}" required>
                    <option value="">Select one</option>
                    <option value="endorser" @selected(old($field) === 'endorser')>Endorser</option>
                    <option value="partner" @selected(old($field) === 'partner')>Partner</option>
                </select>
            @else
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" type="{{ $type_attr }}"
                    @if($field === 'email') autocomplete="email" @elseif($field === 'contact_number') autocomplete="tel" inputmode="tel" @endif
                    @unless($optional) required @endunless>
            @endif
        </div>
    @endforeach
    </div>

    @if(in_array($type, ['sponsorship','external-event']))
        <label for="uploads">Supporting files
            <span class="help">Proposal deck, rate card, race results, or photos. PDF, image, or Word, up to 5 files at 10 MB each.</span>
        </label>
        <input id="uploads" type="file" name="uploads[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
    @endif

    <div class="actions"><button type="submit">{{ $form['cta'] }}</button><span id="draft-status" class="muted small" aria-live="polite"></span></div>
</form>

<script>
    document.querySelectorAll('[data-show-when]').forEach(function (section) {
        var controller = document.querySelector('[name="' + section.dataset.showWhen + '"]');
        if (! controller) return;

        function updateConditionalField() {
            var visible = controller.value === section.dataset.showValue;
            section.hidden = ! visible;
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = ! visible;
            });
        }

        controller.addEventListener('change', updateConditionalField);
        updateConditionalField();
    });

    (function () {
        var form = document.getElementById('client-inquiry-form');
        var status = document.getElementById('draft-status');
        if (!form || !window.localStorage) return;
        var key = form.dataset.draftKey;

        if (form.dataset.submitted === 'yes') {
            localStorage.removeItem(key);
            return;
        }

        try {
            var saved = JSON.parse(localStorage.getItem(key) || '{}');
            var restored = false;
            Object.keys(saved).forEach(function (name) {
                var field = form.elements.namedItem(name);
                if (!field || field.type === 'file' || field.value) return;
                field.value = saved[name]; restored = true;
                field.dispatchEvent(new Event('change'));
            });
            if (restored) status.textContent = 'Draft restored on this device.';
        } catch (error) { localStorage.removeItem(key); }

        var timer;
        form.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var draft = {};
                new FormData(form).forEach(function (value, name) {
                    if (name !== '_token' && name !== 'website' && !(value instanceof File)) draft[name] = value;
                });
                try { localStorage.setItem(key, JSON.stringify(draft)); status.textContent = 'Draft saved on this device.'; } catch (error) {}
            }, 350);
        });
    })();
</script>
@endsection
