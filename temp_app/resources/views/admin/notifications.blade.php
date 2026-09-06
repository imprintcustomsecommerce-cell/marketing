@extends('layouts.admin')
@section('title', 'Notifications')
@section('content')
<style>.notification{display:flex;align-items:center;gap:15px;text-decoration:none;padding:18px;margin-bottom:12px;border:1px solid var(--line);border-radius:15px;background:#fff}.notification:hover{border-color:var(--accent);box-shadow:var(--shadow)}.notification-count{width:46px;height:46px;display:grid;place-items:center;border-radius:13px;background:var(--accent-bg);color:var(--accent-ink);font-size:1.2rem;font-weight:850}.notification-body{flex:1}.notification-body strong{display:block}.notification-arrow{font-size:1.4rem;color:var(--muted)}</style>
<div class="topline"><div><span class="page-kicker">Attention center</span><h1>Notifications</h1><div class="muted small">Only alerts relevant to your role and team appear here.</div></div></div>
@forelse($notifications as $item)<a class="notification" href="{{ $item['url'] }}"><span class="notification-count">{{ $item['count'] }}</span><span class="notification-body"><strong>{{ $item['title'] }}</strong><span class="muted small">{{ $item['detail'] }}</span></span><span class="notification-arrow">›</span></a>@empty<div class="card empty"><h2>You’re all caught up</h2><p class="muted">There are no outstanding alerts for your role.</p></div>@endforelse
@endsection
