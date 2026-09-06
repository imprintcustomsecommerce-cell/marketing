@extends('layouts.admin')
@section('title', 'Team')
@section('content')
<style>
    .team-head{display:flex;align-items:baseline;gap:10px;margin:26px 0 12px}
    .team-head h2{font-size:1.05rem}
    .team-head .n{font-size:.78rem;color:var(--muted)}
    .avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--ink);color:#fff;display:grid;place-items:center;font-weight:800;font-size:.75rem;flex:none;overflow:hidden}
    .avatar-sm img{width:100%;height:100%;object-fit:cover;display:block}
    .person{display:flex;align-items:center;gap:11px;white-space:nowrap}
    .load{display:flex;gap:6px;flex-wrap:wrap}
    .load span{font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:999px;background:var(--line-soft);color:var(--ink-soft);white-space:nowrap}
    .load .hot{background:#fffbeb;color:#b45309;border:1px solid #fde68a}
    .load .idle{color:var(--muted)}
</style>

<div class="topline">
    <div>
        <span class="page-kicker">Accounts</span>
        <h1>Team</h1>
        <div class="muted small">Everyone across marketing and multimedia, and what they have on</div>
    </div>
    <a class="button" href="{{ route('admin.team.create') }}">+ Add account</a>
</div>

@foreach(App\Models\User::TEAMS as $key => $label)
    <div class="team-head">
        <h2>{{ $label }}</h2>
        <span class="n">{{ $headcount[$key] ?? 0 }} active</span>
    </div>

    <div class="card" style="padding:0;overflow:auto">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Currently on</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($groups[$key] ?? [] as $person)
                <tr>
                    <td>
                        <span class="person">
                            <span class="avatar-sm">
                                @if($person->avatarUrl())<img src="{{ $person->avatarUrl() }}" alt="">@else{{ $person->initial() }}@endif
                            </span>
                            <strong>{{ $person->name }}</strong>
                        </span>
                    </td>
                    <td class="muted small">{{ $person->email }}</td>
                    <td>{{ App\Models\User::ROLES[$person->role] ?? $person->role }}</td>
                    <td>
                        <span class="load">
                            @if($key === App\Models\User::TEAM_MULTIMEDIA)
                                <span class="{{ ($shoots[$person->id] ?? 0) ? '' : 'idle' }}">{{ $shoots[$person->id] ?? 0 }} shoots</span>
                                <span class="{{ ($openPhoto[$person->id] ?? 0) ? 'hot' : 'idle' }}">{{ $openPhoto[$person->id] ?? 0 }} photo open</span>
                                <span class="{{ ($openVideo[$person->id] ?? 0) ? 'hot' : 'idle' }}">{{ $openVideo[$person->id] ?? 0 }} video open</span>
                            @else
                                <span class="{{ ($eventsMade[$person->id] ?? 0) ? '' : 'idle' }}">{{ $eventsMade[$person->id] ?? 0 }} events</span>
                                <span class="{{ ($kitsMade[$person->id] ?? 0) ? '' : 'idle' }}">{{ $kitsMade[$person->id] ?? 0 }} PR kits</span>
                            @endif
                        </span>
                    </td>
                    <td><span class="pill pill-{{ $person->is_active ? 'active' : 'inactive' }}">{{ $person->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="edit" href="{{ route('admin.team.edit', $person) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No {{ strtolower($label) }} accounts yet. <a class="edit" href="{{ route('admin.team.create') }}">Add one</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endforeach
@endsection
