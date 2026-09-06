<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AvatarStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service account settings. Every account has this, whatever their team
 * or role — it only ever edits the signed-in user's own details.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account');
    }

    public function update(Request $request, AvatarStore $avatars): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'avatar.image' => 'The profile picture must be an image.',
            'avatar.max' => 'The profile picture may not be larger than 5 MB.',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $avatars->store($user, $request->file('avatar'));
        }

        unset($validated['avatar']);

        $user->update($validated);

        return redirect()->route('admin.account.edit')->with('success', 'Your details were saved.');
    }

    public function removeAvatar(Request $request, AvatarStore $avatars): RedirectResponse
    {
        $user = $request->user();
        $avatars->forget($user);
        $user->update(['avatar_path' => null]);

        return redirect()->route('admin.account.edit')->with('success', 'Your profile picture was removed.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            // Knowing the current password is what stops someone changing it at
            // an unattended machine.
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }

        $user->update(['password' => $validated['password'], 'must_change_password' => false]);

        return redirect()->route('admin.account.edit')->with('success', 'Your password was changed.');
    }
}
