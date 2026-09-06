<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\PublicSubmission;
use App\Models\User;
use App\Support\InquiryConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const CONTACT_TYPES = ['call' => 'Phone call', 'email' => 'Email', 'message' => 'Message', 'meeting' => 'Meeting', 'note' => 'Internal note'];

    public const RESPONSE_TEMPLATES = [
        'received' => ['Inquiry received', 'Hi! We received your inquiry and our team is reviewing the details. We will get back to you shortly.'],
        'more_info' => ['More information required', 'Hi! Thank you for your inquiry. Before we proceed, could you please send the additional details we discussed?'],
        'accepted' => ['Partnership accepted', 'Great news—your partnership application has been accepted. Our team will contact you with the next steps.'],
        'declined' => ['Application declined', 'Thank you for considering Imprint Customs. We are unable to proceed with this application at this time, but we appreciate your interest.'],
        'confirmed' => ['Event confirmed', 'Your event has been confirmed. Our team will coordinate the remaining requirements and schedule with you.'],
    ];

    /**
     * Where an inquiry has got to. `new_inquiry` is what the public forms write,
     * so it stays spelled that way rather than being migrated for tidiness.
     */
    public const STATUSES = [
        'new_inquiry' => 'New',
        'reviewing' => 'Reviewing',
        'quoted' => 'Quoted',
        'confirmed' => 'Confirmed',
        'declined' => 'Declined',
        'closed' => 'Closed',
    ];

    /** The public form each submission came from. */
    public const TYPES = [
        'function-hall_inquiry' => 'Function Hall',
        'tambike_inquiry' => 'Tambike',
        'sponsorship_inquiry' => 'Sponsorship',
        'external-event_inquiry' => 'External Event',
        'event_inquiry' => 'General',
    ];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');
        $search = trim((string) $request->query('q', ''));
        $priority = (string) $request->query('priority', '');
        $followUp = (string) $request->query('follow_up', '');

        $inquiries = PublicSubmission::with(['handler', 'assignee'])
            ->whereNotNull('type')
            ->when(array_key_exists($status, self::STATUSES), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($type, self::TYPES), fn ($query) => $query->where('type', $type))
            ->when(array_key_exists($priority, self::PRIORITIES), fn ($query) => $query->where('priority', $priority))
            ->when($followUp === 'overdue', fn ($query) => $query->whereNotNull('follow_up_at')->where('follow_up_at', '<', now())->whereNotIn('status', ['confirmed', 'declined', 'closed']))
            // The answers live in a JSON blob, so searching means looking inside it.
            ->when($search !== '', fn ($query) => $query->where('data', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inquiries.index', [
            'inquiries' => $inquiries,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'priority' => $priority,
            'followUp' => $followUp,
            'counts' => PublicSubmission::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'newCount' => PublicSubmission::where('status', 'new_inquiry')->count(),
        ]);
    }

    public function show(PublicSubmission $inquiry, InquiryConverter $converter): View
    {
        return view('admin.inquiries.show', [
            'inquiry' => $inquiry->load(['handler', 'assignee', 'contactLogs.user', 'convertedEvent', 'convertedEndorser']),
            'canBecomeEvent' => $converter->canBecomeEvent($inquiry),
            'canBecomeEndorser' => $converter->canBecomeEndorser($inquiry),
            'duplicateEndorsers' => $this->duplicateEndorsers($inquiry),
            'duplicateInquiries' => $this->duplicateInquiries($inquiry),
            'staff' => User::where('is_active', true)->where('team', User::TEAM_MARKETING)->orderBy('name')->get(),
            'responseTemplates' => self::RESPONSE_TEMPLATES,
        ]);
    }

    /**
     * Turn the inquiry into the record the team works from, then drop the user
     * on that record so they can finish anything the form did not ask for.
     */
    public function convert(Request $request, PublicSubmission $inquiry, InquiryConverter $converter): RedirectResponse
    {
        if ($converter->canBecomeEvent($inquiry)) {
            $event = $converter->toEvent($inquiry, $request->user());
            $inquiry->contactLogs()->create(['user_id' => $request->user()->id, 'type' => 'note', 'note' => 'Accepted and converted to event: '.$event->name]);

            return redirect()->route('admin.events.edit', $event)
                ->with('success', 'Event created from the inquiry. Check the details below before confirming it.');
        }

        if ($converter->canBecomeEndorser($inquiry)) {
            $conversion = $request->validate([
                'conversion' => ['nullable', Rule::in(['endorser', 'partner'])],
            ])['conversion'] ?? 'endorser';
            $endorser = $converter->toEndorser($inquiry, $request->user(), $conversion);
            $inquiry->contactLogs()->create(['user_id' => $request->user()->id, 'type' => 'note', 'note' => 'Accepted as '.$conversion.' and converted to: '.$endorser->name]);

            return redirect()->route('admin.endorsers.edit', $endorser)
                ->with('success', str($conversion)->title().' created from the application. Check the details below.');
        }

        // Already converted, or a kind of inquiry that does not become a record.
        return back()->with('success', 'This inquiry has already been converted.');
    }

    public function update(Request $request, PublicSubmission $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'priority' => ['sometimes', 'required', Rule::in(array_keys(self::PRIORITIES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->where('team', User::TEAM_MARKETING))],
            'follow_up_at' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'public_update' => ['nullable', 'string', 'max:1000'],
        ]);

        $originalStatus = $inquiry->status;

        $inquiry->update($validated + [
            // Whoever last moved it is who to ask about it.
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        if ($originalStatus !== $inquiry->status) {
            $inquiry->contactLogs()->create([
                'user_id' => $request->user()->id,
                'type' => 'note',
                'note' => 'Status changed from '.(self::STATUSES[$originalStatus] ?? $originalStatus).' to '.self::STATUSES[$inquiry->status].'.',
            ]);
        }

        return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'Inquiry updated.');
    }

    public function quickAction(Request $request, PublicSubmission $inquiry): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['request_information', 'decline'])],
        ])['action'];

        $inquiry->update([
            'status' => $action === 'decline' ? 'declined' : 'reviewing',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        $inquiry->contactLogs()->create([
            'user_id' => $request->user()->id,
            'type' => 'note',
            'note' => $action === 'decline' ? 'Inquiry declined.' : 'Requested more information from the client.',
        ]);

        $message = $action === 'decline'
            ? 'Inquiry declined.'
            : 'Inquiry marked as waiting for more information.';

        return back()->with('success', $message);
    }

    public function addContact(Request $request, PublicSubmission $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::CONTACT_TYPES))],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $inquiry->contactLogs()->create($validated + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Contact activity added.');
    }

    /**
     * Attachments live on the private disk and are never publicly reachable, so
     * they are streamed through here for signed-in staff only.
     */
    public function download(PublicSubmission $inquiry, int $index): StreamedResponse
    {
        $attachment = $inquiry->attachments()[$index] ?? null;

        abort_if($attachment === null || ! Storage::disk('local')->exists($attachment['path']), 404);

        // Served under the name the sender gave it, not the random storage name.
        return Storage::disk('local')->download($attachment['path'], $attachment['name']);
    }

    private function duplicateEndorsers(PublicSubmission $inquiry)
    {
        $email = mb_strtolower(trim((string) data_get($inquiry->data, 'email')));
        $phone = $this->normalizedPhone(data_get($inquiry->data, 'contact_number'));

        return Endorser::query()->get()->filter(function (Endorser $endorser) use ($email, $phone): bool {
            $sameEmail = $email !== '' && mb_strtolower(trim((string) $endorser->email)) === $email;
            $samePhone = $phone !== '' && $this->normalizedPhone($endorser->contact_number) === $phone;

            return $sameEmail || $samePhone;
        })->values();
    }

    private function duplicateInquiries(PublicSubmission $inquiry)
    {
        $email = mb_strtolower(trim((string) data_get($inquiry->data, 'email')));
        $phone = $this->normalizedPhone(data_get($inquiry->data, 'contact_number'));

        if ($email === '' && $phone === '') {
            return collect();
        }

        return PublicSubmission::whereKeyNot($inquiry->id)->latest('submitted_at')->get()
            ->filter(function (PublicSubmission $other) use ($email, $phone): bool {
                $sameEmail = $email !== '' && mb_strtolower(trim((string) data_get($other->data, 'email'))) === $email;
                $samePhone = $phone !== '' && $this->normalizedPhone(data_get($other->data, 'contact_number')) === $phone;

                return $sameEmail || $samePhone;
            })->take(10)->values();
    }

    private function normalizedPhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }
}
