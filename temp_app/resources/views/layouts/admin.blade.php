<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') · Imprint Hub</title>
    <link rel="icon" href="{{ asset('images/imprint-customs-mark.png') }}" sizes="any">
    <style>
        /* Imprint tokens (shared with the public pages and the promo site):
           ink shell, one amber accent. The layout follows a soft "app frame"
           pattern — a tinted desktop with a single rounded panel floating on
           it — so the workspace reads as one calm surface. */
        :root{
            --ink:#111827;--ink-soft:#374151;--muted:#6b7280;
            --line:#e5e7eb;--line-soft:#f1f3f5;--surface:#fff;
            --desk:#f3ede4;--canvas:#faf9f7;
            --accent:#f59e0b;--accent-ink:#7c2d12;--accent-bg:#fffbeb;
            --radius:12px;--radius-lg:18px;--radius-xl:26px;
            --shadow:0 1px 2px rgba(16,24,40,.05),0 1px 3px rgba(16,24,40,.06);
            --shadow-lg:0 4px 6px -1px rgba(16,24,40,.07),0 10px 24px -4px rgba(16,24,40,.1);
            --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{font-family:var(--font);color:var(--ink);background:var(--desk);line-height:1.55;font-size:15px;-webkit-font-smoothing:antialiased;padding:18px}
        a{color:inherit}
        h1{font-size:1.5rem;margin:0;letter-spacing:-.02em}
        h2{font-size:1.15rem;margin:0;letter-spacing:-.01em}
        .muted{color:var(--muted)}
        .small{font-size:.85rem}

        /* ------------------------------------------------------ app frame */
        .app{background:var(--canvas);border-radius:var(--radius-xl);box-shadow:0 18px 50px -12px rgba(16,24,40,.22);min-height:calc(100vh - 36px);display:flex;flex-direction:column}

        .topbar{display:flex;align-items:center;gap:18px;padding:14px 22px;border-bottom:1px solid var(--line);background:#fff;flex-wrap:wrap}
        .brand{display:flex;align-items:center;gap:10px;text-decoration:none;flex:none}
        .brand img{width:34px;height:34px;border-radius:50%}
        .brand-text{display:flex;flex-direction:column;line-height:1.1}
        .brand-name{font-weight:800;font-size:1rem}
        .brand-sub{font-size:.65rem;letter-spacing:.14em;text-transform:uppercase;color:var(--accent-ink)}
        .topbar form.search{flex:1 1 240px;max-width:340px;margin:0}
        .topbar .search input{width:100%;padding:9px 14px;border:1px solid var(--line);border-radius:999px;font:inherit;font-size:.9rem;background:var(--canvas)}
        .topbar .search input:focus{outline:3px solid #f59e0b33;border-color:var(--accent)}

        .body{display:grid;grid-template-columns:216px 1fr;flex:1 0 auto;min-height:0}
        .side{border-right:1px solid var(--line);padding:20px 14px;background:#fff}
        .nav a{display:flex;align-items:center;gap:11px;color:var(--muted);text-decoration:none;padding:10px 13px;border-radius:10px;margin:3px 0;font-weight:600;font-size:.9rem}
        .nav a:hover{background:var(--line-soft);color:var(--ink)}
        .nav a.active{background:var(--accent-bg);color:var(--accent-ink);box-shadow:inset 3px 0 0 var(--accent)}
        .nav svg{width:17px;height:17px;flex:none}
        .nav-badge{margin-left:auto;background:#fee2e2;color:#991b1b;border-radius:999px;padding:1px 8px;font-size:.72rem;font-weight:800}
        .nav-label{font-size:.68rem;letter-spacing:.13em;text-transform:uppercase;color:#9ca3af;margin:18px 13px 6px;font-weight:700}

        /* Cap the reading width on the container, not on each child: on a 1920
           monitor a full-bleed table stretches each row into an unreadable
           ribbon, but centring every row separately pulls them out of line. */
        .main{padding:26px 30px 40px;min-width:0;width:100%;max-width:1240px;margin:0 auto}
        .topline{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px;flex-wrap:wrap}

        /* ---------------------------------------------------------- parts */
        .button,button.button{display:inline-flex;align-items:center;gap:7px;background:var(--accent);color:var(--ink);border:0;border-radius:999px;padding:10px 18px;text-decoration:none;font:inherit;font-weight:700;font-size:.88rem;cursor:pointer;box-shadow:var(--shadow)}
        .button:hover{background:#d97706}
        .button.ghost{background:#fff;border:1px solid var(--line);color:var(--ink-soft)}
        .button.ghost:hover{border-color:var(--accent);background:var(--accent-bg)}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-lg);padding:22px;margin-bottom:18px;box-shadow:var(--shadow)}

        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
        
        table{width:100%;border-collapse:collapse;background:#fff;font-size:.9rem}
        th,td{text-align:left;padding:14px 16px;border-bottom:1px solid var(--line-soft)}
        th{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);background:var(--canvas);font-weight:700}
        tbody tr:hover{background:#fffdf6}
        tbody tr:last-child td{border-bottom:0}
        .edit{color:var(--accent-ink);font-weight:700;text-decoration:none;font-size:.85rem}
        .edit:hover{text-decoration:underline}

        /* Status pills. Colour carries meaning, so each keeps a text label. */
        .pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:.75rem;font-weight:700;border:1px solid transparent;white-space:nowrap}
        .pill-new{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
        .pill-pending{background:#fffbeb;color:#b45309;border-color:#fde68a}
        .pill-confirmed{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
        .pill-active{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
        .pill-completed{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
        .pill-cancelled{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
        .pill-inactive{background:var(--line-soft);color:var(--muted);border-color:var(--line)}
        .pill-scheduled{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
        .pill-packed{background:#fffbeb;color:#b45309;border-color:#fde68a}
        .pill-in_transit{background:#fff7ed;color:#c2410c;border-color:#fed7aa}
        .pill-delivered{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
        .pill-awaiting_pickup{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
        .pill-returned{background:var(--line-soft);color:var(--ink-soft);border-color:var(--line)}
        .pill-submitted{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
        .pill-waived{background:var(--line-soft);color:var(--muted);border-color:var(--line)}
        .pill-missed{background:#fef2f2;color:#b91c1c;border-color:#fecaca}

        .notice{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:13px 16px;border-radius:var(--radius);margin-bottom:18px;font-weight:600}
        /* A warning is the one flash the reader must not scroll past — the
           forced password change sits behind it — so it lands as a centred
           dialog over a dimmed workspace rather than a strip above the fold. */
        .flash-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.62);
            display:flex;align-items:center;justify-content:center;padding:24px;z-index:80}
        .flash-dialog{background:#fff;border-radius:calc(var(--radius) + 6px);
            box-shadow:0 24px 60px rgba(15,23,42,.32);max-width:440px;width:100%;
            padding:28px;text-align:center}
        .flash-dialog h2{margin:0 0 8px;font-size:19px;color:#0f172a}
        .flash-dialog p{margin:0 0 20px;color:#475569;line-height:1.5}
        .flash-dialog button{background:var(--accent,#f59e0b);color:#3f2d05;border:0;
            border-radius:var(--radius);padding:11px 26px;font-weight:700;
            font-size:15px;cursor:pointer;font-family:inherit}
        .flash-dialog button:hover{filter:brightness(.95)}
        @media (prefers-reduced-motion:no-preference){
            .flash-dialog{animation:flash-in .16s ease-out}
            @keyframes flash-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
        }
        .errors{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:13px 16px;border-radius:var(--radius);margin-bottom:18px}
        .errors ul{margin:8px 0 0;padding-left:20px}

        .grid{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}
        .full{grid-column:1/-1}
        label{display:block;font-weight:650;margin:16px 0 6px;font-size:.9rem}
        input,select,textarea{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:var(--radius);font:inherit;font-size:.92rem;background:#fff}
        input:focus,select:focus,textarea:focus{outline:3px solid #f59e0b33;border-color:var(--accent)}
        textarea{min-height:110px;resize:vertical}

        .empty{padding:38px 20px;text-align:center;color:var(--muted)}

        /* ------------------------------------------------------ empty state */
        .empty-state{text-align:center;padding:52px 28px}
        .empty-state-icon{width:66px;height:66px;border-radius:20px;display:grid;place-items:center;margin:0 auto 20px;background:var(--accent-bg);color:var(--accent-ink);border:1px solid #fde68a}
        .empty-state-icon svg{width:30px;height:30px}
        .empty-state h2{font-size:1.2rem;margin:0 0 8px}
        .empty-state p{color:var(--muted);margin:0 auto;max-width:44ch;font-size:.94rem;line-height:1.6}
        .empty-state-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:24px}
        @media(max-width:560px){.empty-state{padding:44px 20px}}

        @media(max-width:860px){
            body{padding:0}
            .app{border-radius:0;min-height:100vh}
            .body{display:block}
            .side{border-right:0;border-bottom:1px solid var(--line);padding:12px}
            .nav{display:flex;gap:6px;flex-wrap:wrap}
            .nav-label{display:none}
            .nav a.active{box-shadow:none}
            .main{padding:20px}
            .stats,.grid{grid-template-columns:1fr}
        }

        /* ------------------------------------------------ 2026 workspace */
        /* The internal product now shares the login screen's workshop feel:
           deep navy structure, warm metal accent, and a quiet work surface. */
        /* A warm off-white workspace rather than a cool grey one: the amber
           accent and the shop's own photography sit better on cream. */
        body{padding:0;background:#f7f5f0}
        /* No overflow clipping here. The frame is full-bleed now, so there is
           no rounded corner left to clip — and any overflow on an ancestor
           silently kills position:sticky, which is how the top bar and the
           sidebar used to scroll away on every page taller than the window. */
        .app{min-height:100vh;border-radius:0;box-shadow:none;background:#f7f5f0;overflow:visible}
        .topbar{
            position:sticky;top:0;z-index:30;min-height:72px;padding:14px 24px;
            border-bottom:1px solid #ece7dd;background:#fffefbee;
            backdrop-filter:blur(16px);box-shadow:0 1px 0 #ffffff inset;
        }
        .brand{width:188px;gap:12px}
        .brand img{width:40px;height:40px;box-shadow:0 0 0 3px #f59e0b24}
        .brand-name{font-size:1.02rem;letter-spacing:-.02em}
        .brand-sub{color:#b45309;font-weight:800}
        .topbar form.search{max-width:560px;position:relative;margin-right:auto}
        .topbar .search input{
            height:42px;padding:10px 18px 10px 42px;background:#f6f7f9;
            border-color:#e3e5e9;transition:background .15s,border-color .15s,box-shadow .15s;
        }
        .topbar .search::before{
            content:"";position:absolute;left:16px;top:12px;width:16px;height:16px;opacity:.55;
            background:no-repeat center/16px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23374151' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-4-4'/%3E%3C/svg%3E");
        }
        .topbar .search input:focus{background:#fff}

        .body{grid-template-columns:204px minmax(0,1fr)}
        .side{
            position:sticky;top:72px;height:calc(100vh - 72px);overflow-y:auto;
            /* A column, so the groups sit at the top and the account card can be
               pinned to the bottom instead of leaving a dead slab below them. */
            display:flex;flex-direction:column;
            padding:14px 12px;background:#fdfcf9;border:0;border-right:1px solid #ece7dd;color:var(--ink);
            scrollbar-width:thin;scrollbar-color:#00000018 transparent;
        }
        .side::-webkit-scrollbar{width:6px}
        .side::-webkit-scrollbar-thumb{background:#00000018;border-radius:99px}

        /* Group headings are signposts, not headlines: small, quiet, and close
           to the items they label. */
        .nav-group{padding:10px 0}
        .nav-group + .nav-group{border-top:1px solid #eee9e0}
        .nav-label{color:#a6a093;margin:2px 12px 6px;font-size:.62rem;letter-spacing:.15em;font-weight:800}
        .nav{display:flex;flex-direction:column;gap:1px}
        .nav a{
            position:relative;color:#6b6559;padding:9px 12px;border-radius:10px;margin:0;
            font-size:.875rem;font-weight:600;gap:11px;
            transition:background .14s,color .14s,box-shadow .14s;
        }
        .nav a:hover{background:#f3efe7;color:var(--ink)}
        /* The current page reads as a raised white card with an amber rail —
           the same treatment the content cards get, so the two agree. */
        .nav a.active{background:#fff;color:var(--ink);box-shadow:0 1px 2px #1018280d,0 6px 16px -12px #10182833}
        .nav a.active::before{content:"";position:absolute;left:-12px;top:8px;bottom:8px;width:3px;border-radius:0 3px 3px 0;background:var(--accent)}
        .nav svg{width:17px;height:17px;color:#98917f}
        .nav a:hover svg{color:#6b6559}
        .nav a.active svg{color:var(--accent-ink)}
        .nav-badge{margin-left:auto;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:999px;padding:0 7px;font-size:.68rem;font-weight:800;line-height:17px}

        /* Who is signed in, pinned to the foot of the column. */
        .side-foot{margin-top:auto;padding-top:12px;border-top:1px solid #eee9e0}
        .side-user{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;text-decoration:none;color:var(--ink);background:#fff;border:1px solid #ece7dd;transition:border-color .14s,box-shadow .14s}
        .side-user:hover{border-color:#e0d9cb;box-shadow:0 6px 16px -12px #10182840}
        .side-user .pic{width:32px;height:32px;border-radius:50%;background:linear-gradient(145deg,#fcd34d,#f59e0b);color:#1c1206;display:grid;place-items:center;font-weight:800;font-size:.78rem;flex:none;overflow:hidden}
        .side-user .pic img{width:100%;height:100%;object-fit:cover;display:block}
        .side-user .txt{min-width:0;line-height:1.25}
        .side-user .nm{font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .side-user .tm{font-size:.66rem;color:#a6a093;letter-spacing:.1em;text-transform:uppercase}
        .side-foot-links{margin-top:6px}
        .side-foot-links form{display:contents}
        .side-foot-links button{
            display:flex;align-items:center;gap:11px;width:100%;text-align:left;
            background:none;border:0;cursor:pointer;font:inherit;font-size:.875rem;font-weight:600;
            color:#6b6559;padding:9px 12px;border-radius:10px;transition:background .14s,color .14s;
        }
        .side-foot-links button:hover{background:#f3efe7;color:var(--ink)}
        .side-foot-links button svg{width:17px;height:17px;color:#98917f;flex:none}
        .side-foot-links button:hover svg{color:#6b6559}

        /* The menu button and drawer only exist on small screens; on a desktop
           the sidebar is always in view and neither is needed. */
        .nav-toggle{display:none;align-items:center;justify-content:center;width:40px;height:40px;flex:none;border:1px solid #ece7dd;background:#fff;border-radius:11px;color:var(--ink);cursor:pointer}
        .nav-toggle:hover{background:#faf8f4}
        .nav-toggle svg{width:20px;height:20px}
        .nav-backdrop{display:none}

        .main{padding:34px clamp(20px,2.5vw,40px) 56px;background:#f7f5f0;max-width:none;margin:0}
        h1{font-size:clamp(1.65rem,2.2vw,2.05rem);letter-spacing:-.035em;line-height:1.15}
        h2{letter-spacing:-.02em}
        .topline{align-items:center;margin-bottom:26px}
        .page-kicker{display:block;margin-bottom:5px;color:#b45309;font-size:.68rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}
        .button,button.button{min-height:42px;padding:10px 18px;box-shadow:0 5px 14px -6px #b4530980;transition:transform .14s,box-shadow .14s,background .14s}
        .button:hover{transform:translateY(-1px);box-shadow:0 8px 18px -7px #b45309a6}
        .button.ghost{box-shadow:none}
        .card{background:#fff;border-color:#ece7dd;border-radius:16px;box-shadow:0 1px 2px #1018280a,0 8px 24px -20px #10182840}
        .stats{gap:18px}
        table{font-size:.89rem}
        th{padding-top:12px;padding-bottom:12px;background:#faf8f4;color:#8a8375;border-bottom-color:#eee9e0}
        td{padding-top:15px;padding-bottom:15px}
        tbody tr{transition:background .12s}tbody tr:hover{background:#fffbeb70}
        .edit{display:inline-flex;align-items:center;min-height:32px;padding:5px 10px;border-radius:8px;color:#92400e;background:#fffbeb}
        .edit:hover{text-decoration:none;background:#fef3c7}
        .pill{font-size:.7rem;letter-spacing:.02em}
        input,select,textarea{min-height:44px;padding:11px 13px;border-color:#d9dde3;background:#fcfcfd;transition:border-color .15s,box-shadow .15s,background .15s}

        /* --------------------------------------------------- segmented view */
        /* Scope and search answer the same question — "which records am I
           looking at" — so they share a line instead of stacking two bars of
           chrome above every list. */
        .list-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        .list-head > .periods{margin-bottom:0}
        .list-head form{display:flex;align-items:center;gap:9px;margin:0 0 0 auto}
        .list-head input,.list-head select{width:auto;min-height:38px;padding:8px 12px;font-size:.86rem;border-radius:11px}
        .list-head input[type=search]{min-width:220px}
        .list-head select{min-width:170px}
        .list-head .button{min-height:38px;padding:8px 16px}
        @media(max-width:720px){
            /* Three controls abreast on a phone left the search box 110px wide,
               showing "Searc" and nothing typed in it. A picker gets its own
               full-width row; the search field then takes everything the button
               does not. */
            .list-head form{width:100%;margin-left:0;flex-wrap:wrap}
            .list-head select{flex:1 1 100%;min-width:0}
            .list-head input[type=search]{flex:1 1 0;min-width:120px}
            .list-head .button{flex:none}
        }

        .periods{display:flex;width:fit-content;max-width:100%;padding:4px;background:#f2eee6;border-radius:999px;gap:2px;margin-bottom:12px;flex-wrap:wrap}
        .periods a{padding:7px 16px;border-radius:999px;text-decoration:none;font-size:.85rem;font-weight:700;color:var(--muted);white-space:nowrap}
        .periods a:hover{color:var(--ink)}
        .periods a.on{background:#fff;color:var(--ink);box-shadow:0 1px 2px #1018280f}
        .periods .n{opacity:.6;font-weight:600;margin-left:5px}
        .periods a.on.warn{color:#991b1b}

        /* ------------------------------------------------- chips on a phone */
        /* Wrapped, a segmented control is no longer one control: its pill track
           breaks over two lines and the rows read as unrelated buttons. And
           three stacked rows of type filters push the actual records off the
           bottom of the screen. So on a narrow screen the chips stay on one
           line and the row scrolls sideways, the way filter chips behave in
           every phone app.

           The fade is drawn only on a side that can still be scrolled towards,
           which is what tells you the row continues past the edge; a row that
           fits gets no fade at all. */
        @media(max-width:720px){
            .periods,.filter-bar .tabs{
                --fade-start:0px;--fade-end:0px;
                flex-wrap:nowrap;width:auto;max-width:100%;
                overflow-x:auto;overscroll-behavior-x:contain;
                scroll-snap-type:x proximity;scroll-padding:0 30px;
                scrollbar-width:none;-ms-overflow-style:none;
                -webkit-mask-image:linear-gradient(90deg,transparent 0,#000 var(--fade-start),#000 calc(100% - var(--fade-end)),transparent 100%);
                mask-image:linear-gradient(90deg,transparent 0,#000 var(--fade-start),#000 calc(100% - var(--fade-end)),transparent 100%);
            }
            .periods::-webkit-scrollbar,.filter-bar .tabs::-webkit-scrollbar{display:none}
            .periods > a,.filter-bar .tabs > a{flex:none;scroll-snap-align:center}
            /* Matched to the selectors that set the defaults above, or the more
               specific `.filter-bar .tabs` would keep winning and the fade
               would never appear. */
            .periods.can-scroll-start,.filter-bar .tabs.can-scroll-start{--fade-start:26px}
            .periods.can-scroll-end,.filter-bar .tabs.can-scroll-end{--fade-end:26px}
        }

        /* ---------------------------------------------------- record lists */
        /* A list page switches between a table and stacked cards on the width of
           the content column, not the window: the sidebar takes 228px, so a
           1000px window leaves a table barely 700px wide and every viewport
           breakpoint that watched the window got it wrong. Each page sets its
           own threshold, since a nine-column table needs more room than a
           four-column one. */
        .table-card{container-type:inline-size}
        .table-card .stack-cards{display:none;padding:4px 18px}
        /* On the lists whose first column is a name — the thing people read and
           scan for — that column gets the generous share and the rest take what
           their content needs. Left alone the browser splits the width evenly
           and wraps "Night Ride: Antipolo Skyline" over three lines beside a
           half-empty column of two-digit guest counts. Opt-in, because a list
           that leads with a date wants the opposite. */
        .name-first th:first-child,.name-first td:first-child{width:30%;min-width:190px}

        /* ------------------------------------------------------- filter bar */
        .filter-bar{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
        .filter-bar form{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin:0}
        .filter-bar input,.filter-bar select{width:auto;min-height:38px;padding:8px 12px;font-size:.86rem;border-radius:11px}
        .filter-bar input[type=search]{min-width:220px}
        .filter-bar select{min-width:170px;padding-right:8px}
        .filter-bar .button{min-height:38px;padding:8px 16px}
        .filter-bar .spacer{margin-left:auto}
        @media(max-width:720px){
            /* Same shape as the list head above: the picker takes its own row,
               then the search field and its button share the next one, rather
               than the button being orphaned on a third line. */
            .filter-bar,.filter-bar form{width:100%}
            .filter-bar select{flex:1 1 100%;min-width:0}
            .filter-bar input[type=search]{flex:1 1 0;min-width:120px}
            .filter-bar .button{flex:none}
            .filter-bar .spacer{margin-left:0}
        }
        input:hover,select:hover,textarea:hover{border-color:#aeb4bd;background:#fff}
        label{color:#303744;font-size:.84rem}.field-help{display:block;margin-top:6px;color:var(--muted);font-size:.78rem}

        /* Record editors are grouped like a paper dossier, making long forms
           easier to scan and reducing accidental edits to the wrong field. */
        .record-form{display:grid;gap:18px}
        .form-section{background:#fff;border:1px solid #e4e7ec;border-radius:17px;padding:24px;box-shadow:0 6px 22px -20px #10182866}
        .form-section-head{display:flex;gap:13px;align-items:flex-start;padding-bottom:17px;margin-bottom:2px;border-bottom:1px solid #eef0f3}
        .section-icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:#fffbeb;color:#b45309;flex:none}
        .section-icon svg{width:19px;height:19px}
        .form-section-head h2{font-size:1rem;margin:0}.form-section-head p{font-size:.8rem;color:var(--muted);margin:3px 0 0}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}.form-grid .span-2{grid-column:1/-1}
        .required{color:#dc2626;margin-left:3px}
        .form-actions{position:sticky;bottom:14px;z-index:15;display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:13px 15px;background:#111827f2;border:1px solid #ffffff17;border-radius:15px;box-shadow:0 16px 36px -14px #111827a6;backdrop-filter:blur(14px)}
        .form-actions .button.ghost{background:#ffffff12;border-color:#ffffff24;color:#d1d5db}.form-actions .button.ghost:hover{background:#ffffff1f;color:#fff}

        @media(max-width:860px){
            .topbar{position:relative;padding:12px 16px}.brand{width:auto}.topbar form.search{order:3;max-width:none;flex-basis:100%}
            .body{display:block}
            .main{padding:24px 16px 44px}.form-grid{grid-template-columns:1fr}.form-grid .span-2{grid-column:auto}.form-section{padding:19px}.form-actions{bottom:8px}

            /* On a phone the sidebar becomes a strip above the content, so the
               groups run across it instead of stacking eleven rows deep. */
            .nav-toggle{display:flex}

            /* Off-canvas: the page opens on its own content, and the menu slides
               over it when asked for. */
            .side{
                position:fixed;top:0;left:0;bottom:0;z-index:60;
                width:min(290px,82vw);height:100%;transform:translateX(-100%);
                transition:transform .22s ease;border-right:1px solid #ece7dd;
                box-shadow:0 24px 60px -18px #10182866;padding:16px 12px;
            }
            .nav-open .side{transform:none}
            .nav-backdrop{display:block;position:fixed;inset:0;z-index:50;background:#0b1020a6}
            .nav-backdrop[hidden]{display:none}
            body.nav-open{overflow:hidden}
        }
        @media(max-width:560px){
            .brand-sub{display:none}.topbar{gap:10px}.main{padding-top:22px}
            input,select,textarea{font-size:16px;max-width:100%;min-width:0}
            .button,button[data-copy],.share-actions a,.share-actions button{min-height:44px}
            .topline>div{min-width:0;max-width:100%;flex-wrap:wrap}
            .card{min-width:0;overflow-wrap:anywhere}
            .form-actions{flex-wrap:wrap}.form-actions .button{flex:1 1 120px}
            .stats{grid-template-columns:1fr}.topline .button{width:100%;justify-content:center}
            .card.table-card{border:0;background:transparent;box-shadow:none;overflow:visible!important}
        }
    </style>
</head>
<body>
<div class="app">
    <header class="topbar">
        <button class="nav-toggle" type="button" aria-label="Menu" aria-controls="side-nav" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a class="brand" href="{{ route(auth()->user()->homeRoute()) }}">
            <img src="{{ asset('images/imprint-customs-mark.png') }}" alt="" aria-hidden="true" width="34" height="34">
            <span class="brand-text">
                <span class="brand-name">Imprint Hub</span>
                <span class="brand-sub">{{ auth()->user()->isAdmin() ? 'Administration' : (App\Models\User::TEAMS[auth()->user()->team] ?? 'Workspace') }}</span>
            </span>
        </a>
        @if(auth()->user()->canSeeMarketing())
        <form class="search" method="get" action="{{ route('admin.search') }}" role="search">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search everything…" aria-label="Search workspace">
        </form>
        @endif
    </header>

    <div class="body">
        <div class="nav-backdrop" hidden></div>
        <aside class="side" id="side-nav">
            <div class="nav-group">
            <div class="nav-label">Workspace</div>
            <nav class="nav">
                @if(auth()->user()->canSeeMarketing())
                <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Dashboard
                </a>
                @elseif(auth()->user()->team === App\Models\User::TEAM_MULTIMEDIA)
                <a class="{{ request()->routeIs('admin.multimedia') ? 'active' : '' }}" href="{{ route('admin.multimedia') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h12v12H3zM15 10l6-3v10l-6-3z"/></svg>
                    Multimedia Home
                </a>
                @endif
                <a class="{{ request()->routeIs('admin.notifications') ? 'active' : '' }}" href="{{ route('admin.notifications') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    Notifications
                    @if(($notificationBadge ?? 0) > 0)<span class="nav-badge">{{ $notificationBadge }}</span>@endif
                </a>
                <a class="{{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}" href="{{ route('admin.tasks.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><path d="M16 6h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h2"/><path d="M8.5 12.5h7M8.5 16h4"/></svg>
                    Daily Tasks
                    @if(($queuedTaskBadge ?? 0) > 0)<span class="nav-badge">{{ $queuedTaskBadge }}</span>@endif
                </a>
                @if(auth()->user()->canSeeMarketing())
                <a class="{{ request()->routeIs('admin.events.*') ? 'active' : '' }}" href="{{ route('admin.events.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                    Events
                </a>
                <a class="{{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}" href="{{ route('admin.inquiries.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v12H7l-3 3z"/><path d="M8 9h8M8 12.5h5"/></svg>
                    Inquiries
                    @if(($newInquiryBadge ?? 0) > 0)<span class="nav-badge">{{ $newInquiryBadge }}</span>@endif
                </a>
                <a class="{{ request()->routeIs('admin.endorsers.*') ? 'active' : '' }}" href="{{ route('admin.endorsers.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 11.5a3 3 0 1 0-2-5.3M21.5 20a5.5 5.5 0 0 0-4-5.3"/></svg>
                    Endorsers
                </a>
                <a class="{{ request()->routeIs('admin.calendar') ? 'active' : '' }}" href="{{ route('admin.calendar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M7 14h3M14 14h3M7 17.5h3"/></svg>
                    Calendar
                </a>
                @endif
            </nav>

            @if(auth()->user()->canSeeMarketing())
            </div>

            <div class="nav-group">
            <div class="nav-label">Fulfilment</div>
            <nav class="nav">
                <a class="{{ request()->routeIs('admin.pr-kits.*') ? 'active' : '' }}" href="{{ route('admin.pr-kits.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/></svg>
                    PR Kits
                </a>
                <a class="{{ request()->routeIs('admin.obligations.*') ? 'active' : '' }}" href="{{ route('admin.obligations.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><path d="M16 6h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h2"/><path d="m9 13 2 2 4-4"/></svg>
                    Obligations
                </a>
                <a class="{{ request()->routeIs('admin.kit-coverage') ? 'active' : '' }}" href="{{ route('admin.kit-coverage') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 1 1 9 9"/><path d="M12 7v5l3 2"/><path d="M3 12H1.5M3 12l2-2M3 12l2 2"/></svg>
                    Monthly Coverage
                </a>
                <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>
                    Reports
                </a>
            </nav>
            @endif

            @if(auth()->user()->canSeeMultimedia() || auth()->user()->isAdmin())
            </div>

            <div class="nav-group">
            <div class="nav-label">Multimedia</div>
            <nav class="nav">
                @if(auth()->user()->canSeeMultimedia())
                <a class="{{ request()->routeIs('admin.coverage.*') ? 'active' : '' }}" href="{{ route('admin.coverage.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 7.5h13v9H2z"/><path d="m15 12 6-3.5v7L15 12z"/><circle cx="7" cy="12" r="2.2"/></svg>
                    Event Coverage
                    @if(($newCoverageBadge ?? 0) > 0)<span class="nav-badge">{{ $newCoverageBadge }}</span>@endif
                </a>
                @endif
                @if(auth()->user()->isAdmin())
                <a class="{{ request()->routeIs('admin.team.*') ? 'active' : '' }}" href="{{ route('admin.team.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 11.5a3 3 0 1 0-2-5.3M21.5 20a5.5 5.5 0 0 0-4-5.3"/></svg>
                    Team
                </a>
                <a class="{{ request()->routeIs('admin.system.*') ? 'active' : '' }}" href="{{ route('admin.system.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 4 6v5c0 5 3.4 8.2 8 10 4.6-1.8 8-5 8-10V6z"/><path d="m9 12 2 2 4-5"/></svg>
                    System &amp; Security
                </a>
                @endif
            </nav>
            @endif

            </div>

            <div class="side-foot">
                <a class="side-user" href="{{ route('admin.account.edit') }}">
                    <span class="pic">
                        @if(auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}" alt="">
                        @else
                            {{ auth()->user()->initial() }}
                        @endif
                    </span>
                    <span class="txt">
                        <span class="nm">{{ auth()->user()->name }}</span>
                        <span class="tm">{{ App\Models\User::TEAMS[auth()->user()->team] ?? auth()->user()->team }}</span>
                    </span>
                </a>

                <nav class="nav side-foot-links">
                    <a href="{{ route('admin.guide') }}" class="{{ request()->routeIs('admin.guide') ? 'active' : '' }}">Getting started</a>
                    <a class="{{ request()->routeIs('admin.account.*') ? 'active' : '' }}" href="{{ route('admin.account.edit') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                        My Account
                    </a>
                    <form method="post" action="{{ route('admin.logout') }}">@csrf
                        <button type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2"/><path d="M19 12H9m10 0-3-3m3 3-3 3"/></svg>
                            Log out
                        </button>
                    </form>
                </nav>
            </div>
        </aside>

        <main class="main">
            @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
            @if(session('warning'))
                <div class="flash-backdrop" id="flash-warning" role="alertdialog" aria-modal="true" aria-labelledby="flash-warning-title">
                    <div class="flash-dialog">
                        <h2 id="flash-warning-title">Before you continue</h2>
                        <p>{{ session('warning') }}</p>
                        <button type="button" autofocus onclick="document.getElementById('flash-warning').remove()">Got it</button>
                    </div>
                </div>
            @endif
            @if($errors->any())
                <div class="errors"><strong>Please correct these fields:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>
    // Filter chip rows scroll sideways on a phone. The fade at an edge is the
    // only sign that the row continues past it, so it is drawn only where there
    // is actually somewhere to scroll to — and the chip you are filtered by is
    // brought into view, because landing on a row scrolled past your own filter
    // reads as though the filter is not set.
    //
    // Enhancement only: with the script gone the row still scrolls, just
    // without the fade.
    (function () {
        var strips = document.querySelectorAll('.periods, .filter-bar .tabs');
        if (! strips.length) return;

        function sync(strip) {
            // A pixel of slack: sub-pixel widths leave scrollLeft fractionally
            // short of the end and the fade would never turn off.
            var room = strip.scrollWidth - strip.clientWidth;
            strip.classList.toggle('can-scroll-start', strip.scrollLeft > 1);
            strip.classList.toggle('can-scroll-end', strip.scrollLeft < room - 1);
        }

        Array.prototype.forEach.call(strips, function (strip) {
            // Only when the row actually overflows: on a desktop, where it does
            // not, scrollIntoView would be free to scroll the page instead.
            var current = strip.querySelector('a.on');
            if (current && current.scrollIntoView && strip.scrollWidth > strip.clientWidth) {
                current.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }

            strip.addEventListener('scroll', function () { sync(strip); }, { passive: true });
            sync(strip);
        });

        window.addEventListener('resize', function () {
            Array.prototype.forEach.call(strips, sync);
        });
    })();

    // The sidebar is a drawer on small screens. Kept to class toggling so the
    // desktop layout is untouched and nothing depends on JS to render.
    (function () {
        var toggle = document.querySelector('.nav-toggle');
        var backdrop = document.querySelector('.nav-backdrop');
        var side = document.getElementById('side-nav');
        if (! toggle || ! backdrop || ! side) return;

        function setOpen(open) {
            document.body.classList.toggle('nav-open', open);
            backdrop.hidden = ! open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            setOpen(! document.body.classList.contains('nav-open'));
        });

        backdrop.addEventListener('click', function () { setOpen(false); });

        // Following a link should leave the menu closed behind you.
        side.addEventListener('click', function (event) {
            if (event.target.closest('a')) setOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false);
        });

        // Resizing back to a desktop must not leave the body scroll-locked.
        window.matchMedia('(min-width: 861px)').addEventListener('change', function (event) {
            if (event.matches) setOpen(false);
        });
    })();
</script>
@include('partials.password-reveal')
@include('partials.confirm-dialog')
</body>
</html>
