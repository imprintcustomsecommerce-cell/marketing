{{--
    The pre-event checklist, tickable in place.

    Shown on the event record for marketing and on the coverage screen for the
    crew, so whoever actually sorts an item is the one who can mark it done.
    Saving posts the whole list, so an unticked box is stored as unticked rather
    than left at whatever it was before.
--}}
@php
    $done = $event->preparationDone();
    $total = $event->preparationTotal();
    $custom = $event->customPreparation();
    $needed = $event->preparationNeeded();
@endphp

{{-- Nothing was asked for when the event was booked, so there is nothing to
     tick off. Showing all twelve boxes here would invent work. --}}
@if($event->hasPreparation())
<section class="card prep-panel">
    <div class="topline" style="margin-bottom:6px">
        <h2>Before the event</h2>
        <span class="muted small">{{ $done }} of {{ $total }} ready</span>
    </div>
    <div class="prep-bar" aria-hidden="true">
        <span style="width:{{ $total ? round(($done / $total) * 100) : 0 }}%"></span>
    </div>

    <form method="post" action="{{ route('admin.events.preparation', $event) }}">
        @csrf @method('patch')
        <div class="prep-check-list">
            @foreach($needed as $key => $label)
                @php
                    $ticked = $event->isPreparationItemDone($key);
                @endphp
                <label class="prep-check {{ $ticked ? 'on' : '' }}">
                    <input type="checkbox" name="preparation_done[]" value="{{ $key }}" @checked($ticked)>
                    <span>{{ $label }}</span>
                </label>
            @endforeach

            @foreach($custom as $index => $item)
                <label class="prep-check {{ ! empty($item['done']) ? 'on' : '' }}">
                    <input type="checkbox" name="custom_done[]" value="{{ $index }}" @checked(! empty($item['done']))>
                    <span>{{ $item['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;gap:12px;margin-top:13px;flex-wrap:wrap">
            <button class="button" type="submit">Save checklist</button>
            <span class="muted small">Items are added or removed on the event form.</span>
        </div>
    </form>
</section>
@endif

<style>
    .prep-panel .prep-bar{height:7px;border-radius:999px;background:var(--line-soft);overflow:hidden;margin-bottom:14px}
    .prep-panel .prep-bar span{display:block;height:100%;background:#10b981;transition:width .2s}
    .prep-check-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px}
    .prep-check{display:flex;align-items:center;gap:9px;padding:8px 11px;border:1px solid var(--line);
        border-radius:var(--radius);background:#fff;cursor:pointer;font-size:.87rem;font-weight:550;line-height:1.3}
    .prep-check:hover{border-color:var(--accent)}
    .prep-check.on{background:#ecfdf5;border-color:#a7f3d0;color:#047857}
    .prep-check input{width:16px;height:16px;min-height:0;flex:none;accent-color:#10b981;margin:0}
</style>
