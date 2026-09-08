<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Imprint Customs</title>
    <meta name="description" content="@yield('meta_description', 'Imprint Customs — custom builds, events, and rider sponsorships. Send your inquiry and our team will get back to you.')">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111827">
    <link rel="icon" href="{{ asset('images/imprint-customs-mark.png') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/imprint-customs-mark-512.png') }}">
    <meta property="og:site_name" content="Imprint Customs">
    <meta property="og:title" content="@yield('title') · Imprint Customs">
    <meta property="og:description" content="@yield('meta_description', 'Send your inquiry to the Imprint Customs team.')">
    <meta property="og:image" content="{{ asset('images/imprint-customs-mark-512.png') }}">
    <style>
        /* Design tokens mirrored from the Imprint Customs promo site so both
           properties read as one brand: ink shell, single amber accent, no
           gradients. Mobile-first — these pages are opened on a phone. */
        :root{
            --ink:#111827;--ink-soft:#374151;--muted:#6b7280;
            --line:#e5e7eb;--line-soft:#f1f3f5;--surface:#fff;--canvas:#f6f7f9;
            --brand:#111827;--accent:#f59e0b;--accent-ink:#7c2d12;--accent-bg:#fffbeb;
            --danger:#b91c1c;--danger-bg:#fef2f2;--ok:#047857;--ok-bg:#ecfdf5;
            --radius:12px;--radius-lg:16px;
            --shadow:0 1px 2px rgba(16,24,40,.05),0 1px 3px rgba(16,24,40,.06);
            --shadow-lg:0 4px 6px -1px rgba(16,24,40,.07),0 10px 24px -4px rgba(16,24,40,.1);
            --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{
            font-family:var(--font);color:var(--ink);background:var(--canvas);
            font-size:16px;line-height:1.55;-webkit-font-smoothing:antialiased;
            -webkit-text-size-adjust:100%;min-height:100dvh;display:flex;flex-direction:column;
        }
        img{max-width:100%}
        a{color:inherit}
        h1,h2{line-height:1.25;margin:0 0 .5em;font-weight:700;letter-spacing:-.01em}
        h1{font-size:1.75rem}
        h2{font-size:1.3rem}
        @media(min-width:700px){h1{font-size:2.05rem}}

        /* ---------------------------------------------------------- shell */
        .masthead{background:var(--brand);color:#fff;border-bottom:3px solid var(--accent)}
        .masthead .inner{max-width:880px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
        .logo{display:flex;align-items:center;gap:11px;text-decoration:none;color:#fff}
        .logo img{width:42px;height:42px;display:block;border-radius:50%;background:#fff}
        .logo-text{display:flex;flex-direction:column;line-height:1.15}
        .logo-name{font-weight:800;letter-spacing:.01em;font-size:1.02rem}
        .logo-tagline{font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:var(--accent)}
        .masthead nav{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap}
        .masthead nav a{color:#d1d5db;text-decoration:none;font-size:.85rem;font-weight:600;padding:6px 10px;border-radius:8px}
        .masthead nav a:hover{color:#fff;background:#ffffff14}
        .masthead nav a[aria-current="page"]{color:var(--ink);background:var(--accent)}

        #main{flex:1 0 auto;min-width:0}
        .wrap{max-width:880px;margin:0 auto;padding:28px 20px 48px}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow-lg)}
        @media(min-width:700px){.wrap{padding:40px 20px 64px}.card{padding:40px}}

        .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--accent-ink);background:var(--accent-bg);border:1px solid #fde68a;padding:5px 11px;border-radius:999px;margin-bottom:14px}
        .lede{color:var(--ink-soft);margin:0 0 4px;font-size:1.05rem}
        .small{font-size:.88rem}
        .muted{color:var(--muted)}
        hr.sep{border:0;border-top:1px solid var(--line);margin:28px 0}

        .points{list-style:none;margin:20px 0 0;padding:0;display:grid;gap:10px}
        .points li{position:relative;padding-left:28px;color:var(--ink-soft);font-size:.95rem}
        .points li::before{content:"";position:absolute;left:0;top:.34em;width:16px;height:16px;border-radius:50%;background:var(--accent-bg);border:2px solid var(--accent)}

        /* ----------------------------------------------------------- form */
        label{display:block;font-weight:650;margin:20px 0 6px;font-size:.95rem}
        .req{color:var(--accent-ink)}
        .help{display:block;font-weight:400;color:var(--muted);font-size:.85rem;margin-top:3px}
        input,textarea,select{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:var(--radius);font:inherit;background:#fff;color:inherit;box-shadow:var(--shadow)}
        input:focus,textarea:focus,select:focus{outline:3px solid #f59e0b40;border-color:var(--accent)}
        textarea{min-height:120px;resize:vertical}
        .grid{display:grid;gap:0}
        .grid>div{min-width:0}
        @media(max-width:639px){
            input,textarea,select{font-size:16px;min-width:0;max-width:100%}
            .actions>button{width:100%;min-height:44px}
            .card{overflow-wrap:anywhere}
            .site-header a{min-height:44px;display:inline-flex;align-items:center}
        }
        @media(min-width:640px){
            .grid{grid-template-columns:1fr 1fr;column-gap:22px}
            .grid .full{grid-column:1/-1}
            .grid>div:not(.full)>label{min-height:3.35rem}
        }
        .actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:28px}
        button{background:var(--accent);color:var(--ink);border:0;border-radius:var(--radius);padding:13px 26px;font:inherit;font-weight:750;cursor:pointer;box-shadow:var(--shadow)}
        button:hover{background:#d97706}
        button.alt{background:var(--ink);color:#fff}
        button.alt:hover{background:#000}

        .notice{padding:14px 16px;border-radius:var(--radius);background:var(--ok-bg);color:var(--ok);border:1px solid #a7f3d0;margin-bottom:20px;font-weight:600}
        .errors{background:var(--danger-bg);color:var(--danger);padding:14px 16px;border-radius:var(--radius);border:1px solid #fecaca;margin-bottom:20px}
        .errors ul{margin:8px 0 0;padding-left:20px}
        .hp{position:absolute;left:-10000px}
        .field{border-bottom:1px solid var(--line-soft);padding:12px 0}
        .field strong{display:block;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:3px}

        /* ---------------------------------------------------------- tiles */
        .tiles{display:grid;gap:14px;margin-top:24px}
        @media(min-width:640px){.tiles{grid-template-columns:1fr 1fr}.tiles .full{grid-column:1/-1}}
        .tile{display:block;text-decoration:none;color:inherit;background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);transition:border-color .15s,box-shadow .15s,transform .15s}
        .tile:hover{border-color:var(--accent);box-shadow:var(--shadow-lg);transform:translateY(-2px)}
        .tile strong{display:block;font-size:1.02rem;margin-bottom:4px}
        .tile span{color:var(--muted);font-size:.9rem}

        /* --------------------------------------------------------- footer */
        .site-footer{flex-shrink:0;background:var(--brand);color:#9ca3af;margin-top:8px}
        .site-footer .inner{max-width:880px;margin:0 auto;padding:22px 20px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:.84rem}
        .site-footer img{width:30px;height:30px;border-radius:50%;background:#fff}
        .site-footer .est{margin-left:auto;letter-spacing:.12em;text-transform:uppercase;font-size:.7rem}
    </style>
</head>
<body>
<header class="masthead">
    <div class="inner">
        <a class="logo" href="{{ route('client.home') }}">
            <img src="{{ asset('images/imprint-customs-mark.png') }}" alt="" aria-hidden="true" width="42" height="42">
            <span class="logo-text">
                <span class="logo-name">Imprint Customs</span>
                <span class="logo-tagline">Established 2013</span>
            </span>
        </a>
        <nav>
            <a href="{{ route('client.function-hall') }}" @if(request()->routeIs('client.function-hall')) aria-current="page" @endif>Function Hall</a>
            <a href="{{ route('client.tambike') }}" @if(request()->routeIs('client.tambike')) aria-current="page" @endif>Tambike</a>
            <a href="{{ route('client.sponsorship') }}" @if(request()->routeIs('client.sponsorship')) aria-current="page" @endif>Sponsorship</a>
            <a href="{{ route('client.inquiry') }}" @if(request()->routeIs('client.inquiry')) aria-current="page" @endif>General</a>
            <a href="{{ route('client.track') }}" @if(request()->routeIs('client.track*')) aria-current="page" @endif>Track</a>
        </nav>
    </div>
</header>

<main id="main" class="wrap">
    <div class="card">
        @if(session('success'))
            <div class="notice">
                {{ session('success') }}
                @if(session('reference'))
                    <span style="display:block;margin-top:6px;color:var(--ink)">Reference: <strong>{{ session('reference') }}</strong></span>
                    <span style="display:block;font-size:.82rem;font-weight:400;color:var(--ink-soft)">Keep this number if you need to follow up.</span>
                @endif
            </div>
        @endif
        @if($errors->any())
            <div class="errors"><strong>Please check the highlighted information.</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div>
</main>

<footer class="site-footer">
    <div class="inner">
        <img src="{{ asset('images/imprint-customs-mark.png') }}" alt="" aria-hidden="true" width="30" height="30">
        <span>Sent securely to the Imprint Customs team. We use your details only to respond to this inquiry.</span>
        <span class="est">Imprint Customs · Est. 2013</span>
    </div>
</footer>
@include('partials.password-reveal')
</body>
</html>
