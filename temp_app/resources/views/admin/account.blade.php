@extends('layouts.admin')
@section('title', 'My Account')
@section('content')
<style>
    .who-card{display:flex;align-items:center;gap:16px;margin-bottom:18px}
    .who-card .avatar-lg{width:72px;height:72px;border-radius:50%;background:var(--ink);color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.5rem;flex:none;overflow:hidden}
    .who-card .avatar-lg img{width:100%;height:100%;object-fit:cover;display:block}
    .pic{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
    .pic .frame{width:96px;height:96px;border-radius:50%;background:var(--ink);color:#fff;display:grid;place-items:center;font-weight:800;font-size:2rem;flex:none;overflow:hidden;border:3px solid #fff;box-shadow:var(--shadow-lg)}
    .pic .frame img{width:100%;height:100%;object-fit:cover;display:block}
    .pic .controls{flex:1 1 240px;min-width:0}
    .pic input[type=file]{padding:9px}
    .who-card .tags{display:flex;gap:7px;margin-top:5px;flex-wrap:wrap}
    .who-card .tags span{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;background:var(--accent-bg);color:var(--accent-ink);border:1px solid #fde68a}
</style>

<div class="topline">
    <div>
        <h1>My account</h1>
        <div class="muted small">Your own details and password</div>
    </div>
</div>

<div class="card">
    <div class="who-card">
        <span class="avatar-lg">
            @if(auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="">
            @else
                {{ auth()->user()->initial() }}
            @endif
        </span>
        <div>
            <strong>{{ auth()->user()->name }}</strong>
            <div class="tags">
                <span>{{ App\Models\User::TEAMS[auth()->user()->team] ?? auth()->user()->team }}</span>
                <span>{{ App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}</span>
            </div>
        </div>
    </div>
    <p class="muted small" style="margin:0">Your team and role are set by the administrator. Everything below is yours to change.</p>
</div>

<form class="card grid" method="post" action="{{ route('admin.account.update') }}" enctype="multipart/form-data">@csrf @method('put')
    <div class="full"><h2>Your details</h2></div>

    <div class="full pic">
        <span class="frame">
            @if(auth()->user()->avatarUrl())
                <img id="avatar-preview" src="{{ auth()->user()->avatarUrl() }}" alt="Your profile picture">
            @else
                <img id="avatar-preview" alt="" hidden>
                <span id="avatar-initial">{{ auth()->user()->initial() }}</span>
            @endif
        </span>
        <div class="controls">
            <label for="avatar">Profile picture</label>
            <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
            <span class="muted small">JPG, PNG, or WebP up to 5 MB. It is cropped to a square and resized for you.</span>
        </div>
    </div>
    <div><label for="name">Name</label><input id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required><span class="muted small">This is what you sign in with.</span></div>
    <div class="full" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <button class="button" type="submit">Save details</button>
    </div>
</form>

@if(auth()->user()->avatarUrl())
    <form method="post" action="{{ route('admin.account.avatar.destroy') }}" data-confirm="Remove your profile picture?" data-confirm-detail="You can upload a new one at any time." data-confirm-action="Remove" style="margin:-8px 0 18px">@csrf @method('delete')
        <button class="button ghost" type="submit">Remove profile picture</button>
    </form>
@endif

<form class="card grid" method="post" action="{{ route('admin.account.password') }}">@csrf @method('put')
    <div class="full"><h2>Change password</h2></div>
    <div class="full">
        <label for="current_password">Current password</label>
        <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
    </div>
    <div><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required><span class="muted small">At least 8 characters.</span></div>
    <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
    <div class="full"><button class="button" type="submit">Change password</button></div>
</form>
<script>
    // Show the chosen file straight away, so nobody saves a picture blind.
    document.getElementById('avatar')?.addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (! file) return;

        var preview = document.getElementById('avatar-preview');
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        document.getElementById('avatar-initial')?.remove();
    });
</script>
@endsection
