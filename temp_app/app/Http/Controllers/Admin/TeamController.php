<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coverage;
use App\Models\Event;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The people screen. The administrator oversees both sides of the shop, so
 * this lists everyone with what they currently have on their plate rather than
 * just their account details.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('team')->orderBy('name')->get();

        // Multimedia workload comes off the coverage log; marketing workload
        // off the records each person has been creating.
        $shoots = Coverage::selectRaw('shooter_id, count(*) as total')->whereNotNull('shooter_id')->groupBy('shooter_id')->pluck('total', 'shooter_id');
        $openPhoto = Coverage::selectRaw('photo_editor_id, count(*) as total')->whereNotNull('photo_editor_id')->whereNotIn('photo_status', ['posted', 'not_required'])->groupBy('photo_editor_id')->pluck('total', 'photo_editor_id');
        $openVideo = Coverage::selectRaw('video_editor_id, count(*) as total')->whereNotNull('video_editor_id')->whereNotIn('video_status', ['posted', 'not_required'])->groupBy('video_editor_id')->pluck('total', 'video_editor_id');
        $eventsMade = Event::selectRaw('created_by, count(*) as total')->groupBy('created_by')->pluck('total', 'created_by');
        $kitsMade = PrKit::selectRaw('created_by, count(*) as total')->groupBy('created_by')->pluck('total', 'created_by');

        return view('admin.team.index', [
            'groups' => $users->groupBy('team'),
            'shoots' => $shoots,
            'openPhoto' => $openPhoto,
            'openVideo' => $openVideo,
            'eventsMade' => $eventsMade,
            'kitsMade' => $kitsMade,
            'headcount' => $users->where('is_active', true)->groupBy('team')->map->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.team.form', ['user' => new User(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = $request->input('password');

        User::create($data);

        return redirect()->route('admin.team.index')->with('success', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('admin.team.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // A blank password field means "leave the current one alone".
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        // Never let the last active administrator lock everyone out.
        if (($data['role'] !== 'admin' || ! $data['is_active']) && $this->isLastActiveAdmin($user)) {
            return back()->withInput()->withErrors([
                'role' => 'This is the only active administrator, so the role cannot be removed.',
            ]);
        }

        $user->update($data);

        return redirect()->route('admin.team.index')->with('success', 'Account updated.');
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->role === 'admin'
            && $user->is_active
            && User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'team' => ['required', Rule::in(array_keys(User::TEAMS))],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ];

        $request->mergeIfMissing(['is_active' => 0]);

        return collect($request->validate($rules))->except('password')->all();
    }
}
