@php
    // The storefront photo is optional: if the file has not been added yet the
    // page falls back to the plain ink background rather than breaking.
    $storefront = collect(['images/storefront.jpg', 'images/storefront.webp'])
        ->first(fn (string $path): bool => file_exists(public_path($path)));
    $storefront = $storefront ? asset($storefront) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Staff Login · Imprint Hub</title>
    <meta name="theme-color" content="#0b1020">
    <link rel="icon" href="{{ asset('images/imprint-customs-mark.png') }}" sizes="any">
    @if($storefront)<link rel="preload" as="image" href="{{ $storefront }}">@endif
    <style>
        :root{
            --ink:#0b1020;--muted:#6b7280;
            --accent:#f59e0b;--accent-soft:#fcd34d;
            --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;font-family:var(--font);color:#fff;background:var(--ink);
            min-height:100vh;display:grid;place-items:center;padding:24px;
            position:relative;overflow-x:hidden;
        }

        /* ------------------------------------------------------- backdrop */
        /* The photo is a 4:1 shopfront. Anchored right-of-centre so the IMPRINT
           CUSTOMS signage lands in the open left half rather than behind the
           panel, which sits on the right. */
        .bg{position:fixed;inset:0;z-index:0;background-size:cover;background-position:66% center;background-repeat:no-repeat;transform:scale(1.06);animation:drift 40s ease-in-out infinite alternate}
        @keyframes drift{from{transform:scale(1.06) translateX(0)}to{transform:scale(1.12) translateX(-14px)}}
        @media(prefers-reduced-motion:reduce){.bg{animation:none;transform:none}}
        .bg::after{
            content:"";position:absolute;inset:0;
            background:
                linear-gradient(100deg,#0b102040 0%,#0b102094 40%,#0b1020ee 66%,#0b1020 100%),
                linear-gradient(to bottom,#0b102066,transparent 26%,#0b1020cc 100%);
        }
        /* A soft warm wash where the signage is, so the shot keeps its garage glow. */
        .glow{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(46% 44% at 26% 46%,#f59e0b1f,transparent 70%)}
        .bg.plain{background-image:radial-gradient(70% 60% at 50% 0%,#f59e0b26,transparent 70%);animation:none;transform:none}
        .bg.plain::after{content:none}

        .stage{position:relative;z-index:1;width:min(1060px,100%);display:grid;grid-template-columns:1fr minmax(376px,404px);gap:60px;align-items:center}

        /* ---------------------------------------------------- brand half */
        .pitch{max-width:430px}
        .badge{display:inline-flex;align-items:center;gap:9px;background:#ffffff12;border:1px solid #ffffff26;backdrop-filter:blur(8px);padding:6px 15px 6px 6px;border-radius:999px;font-size:.68rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:var(--accent-soft)}
        .badge img{width:26px;height:26px;border-radius:50%}
        .pitch h1{font-size:clamp(2rem,3.5vw,2.8rem);line-height:1.06;letter-spacing:-.035em;margin:24px 0 14px;text-wrap:balance;text-shadow:0 2px 24px #0b1020}
        .pitch h1 em{font-style:normal;color:var(--accent)}
        .pitch .say{color:#cbd5e1;font-size:1.02rem;margin:0 0 26px;max-width:34ch}

        /* Three quiet capability lines — what the hub actually holds. */
        .feats{list-style:none;margin:0;padding:0;display:grid;gap:11px}
        .feats li{display:flex;align-items:center;gap:11px;color:#e2e8f0;font-size:.92rem}
        .feats svg{width:16px;height:16px;flex:none;color:var(--accent)}
        .est{margin-top:30px;font-size:.66rem;letter-spacing:.18em;text-transform:uppercase;color:#94a3b8}

        /* ----------------------------------------------------- form half */
        /* Dark glass rather than a white card: the panel stays part of the
           photograph instead of punching a hole in it. */
        .box{
            position:relative;background:#0f172ac4;backdrop-filter:blur(18px) saturate(130%);
            border:1px solid #ffffff1f;border-radius:22px;padding:36px 32px 32px;
            box-shadow:0 34px 80px -20px #000000cc,inset 0 1px 0 #ffffff1a;overflow:hidden;
        }
        /* A single bolt-coloured hairline along the top edge. */
        .box::before{content:"";position:absolute;top:0;left:26px;right:26px;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent)}
        .box h2{margin:0 0 5px;font-size:1.3rem;letter-spacing:-.02em}
        .sub{color:#94a3b8;margin:0 0 4px;font-size:.9rem}

        label{display:block;font-weight:600;margin:20px 0 7px;font-size:.78rem;letter-spacing:.09em;text-transform:uppercase;color:#94a3b8}
        .field{position:relative}
        .field svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:#64748b;pointer-events:none}
        input[type=email],input[type=password]{
            width:100%;padding:13px 14px 13px 42px;border:1px solid #ffffff26;border-radius:13px;
            font:inherit;font-size:.95rem;background:#0b102099;color:#fff;transition:border-color .15s,box-shadow .15s;
        }
        input::placeholder{color:#64748b}
        input[type=email]:focus,input[type=password]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px #f59e0b2e}
        input:focus + svg{color:var(--accent)}

        .remember{display:flex;align-items:center;gap:9px;margin:18px 0 0;font-size:.88rem;color:#94a3b8;font-weight:500;letter-spacing:0;text-transform:none}
        .remember input{width:16px;height:16px;accent-color:var(--accent)}

        button{
            width:100%;margin-top:24px;padding:14px;border:0;border-radius:999px;cursor:pointer;
            background:linear-gradient(180deg,var(--accent-soft),var(--accent));color:#1c1206;
            font:inherit;font-weight:800;font-size:.98rem;letter-spacing:.01em;
            box-shadow:0 10px 26px -8px #f59e0b99;transition:transform .12s,box-shadow .12s,filter .12s;
        }
        button:hover{filter:brightness(1.05);box-shadow:0 14px 32px -8px #f59e0bb3}
        button:active{transform:translateY(1px)}
        button:focus-visible{outline:3px solid #fff;outline-offset:3px}

        .error{color:#fecaca;background:#7f1d1d5c;border:1px solid #ef444459;padding:11px 14px;border-radius:12px;margin-top:18px;font-size:.88rem}
        .box-foot{margin-top:22px;padding-top:16px;border-top:1px solid #ffffff14;text-align:center;color:#64748b;font-size:.72rem;letter-spacing:.13em;text-transform:uppercase}
        .box-mark{display:none}

        /* Narrow screens: the signage cannot survive a portrait crop, so the
           pitch collapses and the photo becomes a plain dark backdrop. */
        @media(max-width:920px){
            .stage{grid-template-columns:1fr;gap:0;max-width:404px}
            .pitch{display:none}
            .bg{background-position:center;animation:none;transform:none}
            .bg::after{background:linear-gradient(to bottom,#0b1020d4,#0b1020f7)}
            .box-mark{display:block;width:58px;height:58px;border-radius:50%;margin:0 auto 14px}
            .box{text-align:center;padding:32px 24px 28px}
            label{text-align:left}
            .remember{justify-content:center}
        }
    </style>
</head>
<body>
<div class="bg {{ $storefront ? '' : 'plain' }}"
     @if($storefront) style="background-image:url('{{ $storefront }}')" @endif
     role="presentation"></div>
@if($storefront)<div class="glow" role="presentation"></div>@endif

<div class="stage">
    <section class="pitch">
        <span class="badge">
            <img src="{{ asset('images/imprint-customs-mark.png') }}" alt="" aria-hidden="true" width="26" height="26">
            Established 2013
        </span>
        <h1>The shop floor, <em>organised</em>.</h1>
        <p class="say">Every event, endorser, and inquiry that comes through the door — kept in one place.</p>
        <ul class="feats">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                Ride-outs, launches, and hall bookings
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 11.5a3 3 0 1 0-2-5.3"/></svg>
                Racers, teams, and partner endorsers
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 5h16v12H7l-3 3z"/></svg>
                Public inquiries, answered in order
            </li>
        </ul>
        <div class="est">Imprint Customs · Imprint Hub</div>
    </section>

    <form class="box" method="post" action="{{ route('login') }}">@csrf
        <img class="box-mark" src="{{ asset('images/imprint-customs-mark.png') }}" alt="Imprint Customs" width="58" height="58">
        <h2>Staff sign in</h2>
        <p class="sub">Manage events and endorsers.</p>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="email">Email</label>
        <div class="field">
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@imprintcustoms.ph" autocomplete="username" required autofocus>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        </div>

        <label for="password">Password</label>
        <div class="field">
            <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        </div>

        <label class="remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button type="submit">Sign in</button>
        <div class="box-foot">Internal use only</div>
    </form>
</div>
</body>
</html>
