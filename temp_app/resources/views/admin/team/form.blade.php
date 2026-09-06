@extends('layouts.admin')
@section('title', $user->exists ? 'Edit Account' : 'New Account')
@section('content')
<div class="topline">
    <h1>{{ $user->exists ? 'Edit account' : 'Add an account' }}</h1>
    <a class="edit" href="{{ route('admin.team.index') }}">Back to team</a>
</div>

<form class="card grid" method="post" action="{{ $user->exists ? route('admin.team.update', $user) : route('admin.team.store') }}">@csrf
    @if($user->exists)@method('put')@endif

    <div><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>

    <div>
        <label for="team">Team</label>
        <select id="team" name="team" required>
            @foreach(App\Models\User::TEAMS as $value => $label)
                <option value="{{ $value }}" @selected(old('team', $user->team ?: 'multimedia') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="muted small">Which side of the shop they work on.</span>
    </div>
    <div>
        <label for="role">Role</label>
        <select id="role" name="role" required>
            @foreach(App\Models\User::ROLES as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role ?: 'staff') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="muted small">Administrators see and manage everything, on either team.</span>
    </div>

    <div>
        <label for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
        <input id="password" type="password" name="password" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
        <span class="muted small">{{ $user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</span>
    </div>
    <div>
        <label for="is_active">Account status</label>
        <select id="is_active" name="is_active" required>
            <option value="1" @selected((int) old('is_active', $user->is_active ?? 1) === 1)>Active — can sign in</option>
            <option value="0" @selected((int) old('is_active', $user->is_active ?? 1) === 0)>Inactive — cannot sign in</option>
        </select>
    </div>

    <div class="full"><button class="button" type="submit">{{ $user->exists ? 'Save changes' : 'Create account' }}</button></div>
</form>
@endsection
