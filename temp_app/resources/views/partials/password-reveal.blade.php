{{--
    Adds a reveal toggle to every password box on the page.

    Enhancement rather than markup: the script finds the inputs itself, so the
    forms stay plain <input type="password"> and any field added later is
    covered without being wired up by hand. With JavaScript off the fields keep
    working exactly as before, just without the toggle.
--}}
<style>
    .pw-field{position:relative;min-width:0}
    .pw-field > input{display:block}
    /* Room for the button, so long passwords do not run underneath it. */
    .pw-field input{padding-right:44px!important}
    .pw-toggle{
        position:absolute;right:6px;top:50%;transform:translateY(-50%);
        display:flex;align-items:center;justify-content:center;
        width:34px;height:34px;padding:0;margin:0;border:0;border-radius:8px;
        background:none;color:#94a3b8;cursor:pointer;line-height:0;
        min-height:0;min-width:0;box-shadow:none;font:inherit;
    }
    .pw-toggle:hover{color:#475569;background:#94a3b81f}
    .pw-toggle:focus-visible{outline:2px solid var(--accent,#f59e0b);outline-offset:1px}
    .pw-toggle svg{position:static;transform:none;width:18px;height:18px;display:block;flex:none;pointer-events:none}
</style>
<script>
(function () {
    var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6.4 0 10 7 10 7a17 17 0 0 1-3 3.9M6.5 7.6C3.9 9.3 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 4.3-1M3 3l18 18"/><path d="M9.9 10.1a3 3 0 0 0 4.2 4.2"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        var parent = input.parentElement;
        if (!parent || parent.classList.contains('pw-field')) return;
        // Only the input belongs in the positioning box, not its label or hint.
        // Login already has an input-only field with a leading lock icon.
        var wrap;
        if (parent.classList.contains('field')) {
            wrap = parent;
            wrap.classList.add('pw-field');
        } else {
            wrap = document.createElement('div');
            wrap.className = 'pw-field';
            parent.insertBefore(wrap, input);
            wrap.appendChild(input);
        }

        var button = document.createElement('button');
        // Inside a <form> an untyped button submits it, which would post the
        // login form every time someone peeked at what they had typed.
        button.type = 'button';
        button.className = 'pw-toggle';
        button.innerHTML = EYE;
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
        button.title = 'Show password';

        button.addEventListener('click', function () {
            var shown = input.type === 'text';
            // Swapping type moves the caret to the end, so put it back where
            // the typist left it.
            var start = input.selectionStart;
            var end = input.selectionEnd;

            input.type = shown ? 'password' : 'text';
            button.innerHTML = shown ? EYE : EYE_OFF;
            button.setAttribute('aria-pressed', shown ? 'false' : 'true');
            button.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
            button.title = button.getAttribute('aria-label');

            input.focus();
            try { input.setSelectionRange(start, end); } catch (e) { /* number-ish inputs */ }
        });

        wrap.appendChild(button);
    });
})();
</script>
