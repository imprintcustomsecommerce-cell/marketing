{{--
    A centred confirmation for anything destructive.

    Any form carrying data-confirm is intercepted and asked about here instead of
    through the browser's own confirm(), which cannot be styled, reads as a
    security warning in some browsers, and is suppressible after the first one.

    Usage:
        <form ... data-confirm="Delete this for good?"
              data-confirm-detail="What goes with it."
              data-confirm-action="Delete">

    With JavaScript off the form submits as normal, so nothing becomes unusable —
    the confirmation is a guard, not the mechanism.
--}}
<div class="confirm-backdrop" id="confirm-dialog" role="alertdialog" aria-modal="true"
     aria-labelledby="confirm-title" aria-describedby="confirm-detail" hidden>
    <div class="confirm-box">
        <div class="confirm-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 8v5"/><circle cx="12" cy="16.5" r=".8" fill="currentColor" stroke="none"/>
                <path d="M10.3 3.9 2.5 17.4A2 2 0 0 0 4.2 20.5h15.6a2 2 0 0 0 1.7-3.1L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
            </svg>
        </div>
        <h2 id="confirm-title">Are you sure?</h2>
        <p id="confirm-detail"></p>
        <div class="confirm-actions">
            <button type="button" class="confirm-cancel" data-confirm-cancel>Cancel</button>
            <button type="button" class="confirm-go" data-confirm-go>Delete</button>
        </div>
    </div>
</div>

<style>
    .confirm-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.62);display:flex;
        align-items:center;justify-content:center;padding:24px;z-index:120}
    /* An author display rule beats the hidden attribute's UA styling, so the
       dialog has to be told to stay down explicitly. */
    .confirm-backdrop[hidden]{display:none}
    .confirm-box{background:#fff;border-radius:calc(var(--radius) + 6px);box-shadow:0 24px 60px rgba(15,23,42,.32);
        max-width:430px;width:100%;padding:26px;text-align:center}
    .confirm-mark{width:46px;height:46px;margin:0 auto 13px;border-radius:50%;display:grid;place-items:center;
        background:#fef2f2;color:#dc2626}
    .confirm-mark svg{width:24px;height:24px}
    .confirm-box h2{margin:0 0 8px;font-size:19px;color:#0f172a}
    .confirm-box p{margin:0 0 21px;color:#475569;line-height:1.5;font-size:.92rem;white-space:pre-line}
    .confirm-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
    .confirm-actions button{border:0;border-radius:var(--radius);padding:11px 24px;font-weight:700;
        font-size:.95rem;cursor:pointer;font-family:inherit;min-height:0}
    .confirm-cancel{background:#f1f5f9;color:#334155}
    .confirm-cancel:hover{background:#e2e8f0}
    .confirm-go{background:#dc2626;color:#fff}
    .confirm-go:hover{background:#b91c1c}
    @media (prefers-reduced-motion:no-preference){
        .confirm-box{animation:confirm-in .16s ease-out}
        @keyframes confirm-in{from{opacity:0;transform:translateY(-8px) scale(.98)}to{opacity:1;transform:none}}
    }
</style>
<script>
(function () {
    var dialog = document.getElementById('confirm-dialog');
    if (! dialog) return;

    var titleEl = dialog.querySelector('#confirm-title');
    var detailEl = dialog.querySelector('#confirm-detail');
    var goButton = dialog.querySelector('[data-confirm-go]');
    var pending = null;
    var lastFocus = null;

    function close() {
        dialog.hidden = true;
        pending = null;
        if (lastFocus) lastFocus.focus();
    }

    // Delegated, so forms rendered after load are covered too.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (! form.dataset || ! form.dataset.confirm || form.dataset.confirmed === 'yes') return;

        event.preventDefault();
        pending = form;
        lastFocus = document.activeElement;

        titleEl.textContent = form.dataset.confirm;
        detailEl.textContent = form.dataset.confirmDetail || '';
        detailEl.hidden = ! form.dataset.confirmDetail;
        goButton.textContent = form.dataset.confirmAction || 'Delete';

        dialog.hidden = false;
        goButton.focus();
    });

    goButton.addEventListener('click', function () {
        if (! pending) return;
        // Marked before resubmitting, so the handler above lets it through
        // rather than asking again in a loop.
        pending.dataset.confirmed = 'yes';
        pending.submit();
        dialog.hidden = true;
    });

    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', close);

    // Clicking the dimmed area behind the box is a cancel, but a click that
    // started inside the box and drifted out is not.
    dialog.addEventListener('mousedown', function (event) {
        if (event.target === dialog) close();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && ! dialog.hidden) close();
    });
})();
</script>
