<?php

namespace App\Http\Controllers;

use App\Models\PublicLink;
use App\Models\PublicSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $link = $this->findAvailable($token);

        return view('public.event', [
            'link' => $link,
            'unlocked' => ! $link->password || $request->session()->get("public_link.$token") === true,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = $this->findAvailable($token);
        $validated = $request->validate(['password' => ['required', 'string', 'max:255']]);

        if (! $link->password || ! Hash::check($validated['password'], $link->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $request->session()->put("public_link.$token", true);

        return redirect()->route('client.event.show', $token);
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:confirmed,change_requested'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'max:0'],
        ]);

        DB::transaction(function () use ($request, $token, $validated): void {
            $link = PublicLink::where('token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($link->isAvailable(), 410, 'This public link is no longer available.');
            abort_if($link->password && $request->session()->get("public_link.$token") !== true, 403);
            abort_if($validated['response'] === 'confirmed' && ! $link->allow_confirmation, 403);
            abort_if($validated['response'] === 'change_requested' && ! $link->allow_change_request, 403);

            PublicSubmission::create([
                'public_link_id' => $link->id,
                'type' => 'event_response',
                'status' => 'new_inquiry',
                'response' => $validated['response'],
                'comment' => $validated['comment'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'submitted_at' => now(),
            ]);
            $link->increment('submission_count');
        });

        return back()->with('success', 'Thank you. Your response has been recorded.');
    }

    private function findAvailable(string $token): PublicLink
    {
        $link = PublicLink::where('token', $token)->firstOrFail();
        abort_unless($link->isAvailable(), 410, 'This public link is no longer available.');

        return $link;
    }
}
