<?php

namespace App\Http\Controllers;

use App\Models\PublicSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicInquiryController extends Controller
{
    private const FORMS = [
        'function-hall' => [
            'title' => 'Function Hall Inquiry',
            'lede' => 'Reserve the Imprint Customs function hall for your ride-out, launch, birthday, or company event.',
            'points' => ['Flexible layouts, from small meet-ups to a full hall', 'Rider-friendly parking and secure motorcycle space', 'Our team confirms availability before anything is locked in'],
            'cta' => 'Send hall inquiry',
            'success' => 'Thanks! Your function hall inquiry is in. Our team will check the date and get back to you.',
            'fields' => ['name_or_group', 'contact_number', 'email', 'event_type', 'preferred_date', 'start_time', 'end_time', 'estimated_pax', 'special_requests', 'notes'],
        ],
        'tambike' => [
            'title' => 'Tambike Inquiry',
            'lede' => 'Bring Tambike to your ride, rally, or community event: booth, raffle, and marketing support in one request.',
            'points' => ['Booth setup at your venue', 'Raffle prizes and giveaways for your riders', 'Marketing support before and during the event'],
            'cta' => 'Send Tambike inquiry',
            'success' => 'Thanks! Your Tambike inquiry is in. Our team will review it and reach out.',
            'fields' => ['event_name', 'group_name', 'contact_person', 'contact_number', 'email', 'date', 'start_time', 'estimated_pax', 'existing_client', 'relationship_type', 'relationship_period', 'previous_campaign', 'previous_contact', 'need_booth', 'booth_requirements', 'need_raffle', 'raffle_requirements', 'need_marketing_support', 'marketing_support_details', 'notes'],
        ],
        'sponsorship' => [
            'title' => 'Racer/Team Sponsorship',
            'lede' => 'Racing under the Imprint Customs banner starts here. Tell us who you are, what you ride, and what you have won.',
            'points' => ['Open to individual racers and full teams', 'Attach your race results, photos, and rate card', 'Every application is reviewed by our marketing team'],
            'cta' => 'Submit application',
            'success' => 'Thanks! Your sponsorship application is in. We review every application and will contact you if it is a fit.',
            'fields' => ['applicant_type', 'racer_team_name', 'address', 'contact_number', 'email', 'birthday', 'team', 'motorcycle', 'racing_category', 'profile', 'achievements', 'social_media_urls', 'requested_sponsorship'],
        ],
        'external-event' => [
            'title' => 'External Event Sponsorship',
            'lede' => 'Running a ride-out, race, or expo? Ask Imprint Customs to come on board as a sponsor or booth partner.',
            'points' => ['Booth presence, raffle items, or product support', 'Send your proposal deck and sponsorship packages', 'Best submitted at least four weeks before event day'],
            'cta' => 'Send sponsorship request',
            'success' => 'Thanks! Your event sponsorship request is in. Our team will review your proposal and respond.',
            'fields' => ['event_name', 'organizer', 'contact_person', 'contact_number', 'email', 'date', 'end_date', 'venue', 'location', 'estimated_pax', 'can_set_up_booth', 'available_booth_size', 'raffle_requirements', 'sponsorship_requirements', 'notes'],
        ],
        'event' => [
            'title' => 'General Event Inquiry',
            'lede' => 'Not sure which form fits? Send the details here and we will route your request to the right team.',
            'points' => ['One form for anything outside our other services', 'Goes straight to the Imprint Customs team', 'Include as much detail as you can so we can respond faster'],
            'cta' => 'Send inquiry',
            'success' => 'Thanks! Your inquiry is in. Our team will review it and get back to you.',
            'fields' => ['event_name', 'organization', 'contact_person', 'contact_number', 'email', 'date', 'venue', 'estimated_pax', 'requirements', 'notes'],
        ],
    ];

    private const LABELS = [
        'name_or_group' => 'Your name or group',
        'contact_number' => 'Mobile number',
        'email' => 'Email address',
        'event_type' => 'Type of event',
        'preferred_date' => 'Preferred date',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'estimated_pax' => 'Estimated guests',
        'special_requests' => 'Special requests',
        'notes' => 'Anything else we should know?',
        'event_name' => 'Event name',
        'group_name' => 'Group or club name',
        'contact_person' => 'Contact person',
        'date' => 'Event date',
        'end_date' => 'End date',
        'existing_client' => 'Have you been an Imprint Customs endorser or partner before?',
        'relationship_type' => 'Previous relationship',
        'relationship_period' => 'Year or partnership period',
        'previous_campaign' => 'Previous campaign or event',
        'previous_contact' => 'Imprint Customs contact person',
        'need_booth' => 'Do you need a Tambike booth?',
        'booth_requirements' => 'Booth requirements',
        'need_raffle' => 'Do you need raffle prizes?',
        'need_marketing_support' => 'Do you need marketing support?',
        'marketing_support_details' => 'Marketing support needed',
        'applicant_type' => 'Applying as',
        'racer_team_name' => 'Racer or team name',
        'address' => 'Address',
        'birthday' => 'Date of birth',
        'team' => 'Current team',
        'motorcycle' => 'Motorcycle (make, model, displacement)',
        'racing_category' => 'Racing category',
        'profile' => 'Racer or team profile',
        'achievements' => 'Race achievements',
        'social_media_urls' => 'Social media links',
        'requested_sponsorship' => 'What sponsorship are you requesting?',
        'organizer' => 'Organizer',
        'venue' => 'Venue',
        'location' => 'City or province',
        'can_set_up_booth' => 'Can we set up a booth on site?',
        'available_booth_size' => 'Available booth size',
        'raffle_requirements' => 'Raffle requirements',
        'sponsorship_requirements' => 'Sponsorship requirements',
        'organization' => 'Organization',
        'requirements' => 'What do you need from us?',
    ];

    private const HELP = [
        'contact_number' => 'Where we can reach you fastest, for example 0917 123 4567.',
        'estimated_pax' => 'A rough headcount is fine.',
        'relationship_period' => 'For example: 2024, or January–June 2025.',
        'previous_campaign' => 'Name the campaign, event, or activation you worked on.',
        'previous_contact' => 'Who from Imprint Customs coordinated with you?',
        'booth_requirements' => 'Space, power, tables, setup time, and other requirements.',
        'raffle_requirements' => 'Prize quantity, audience, mechanics, or preferred products.',
        'marketing_support_details' => 'Posts, artwork, event coverage, announcements, or other support.',
        'event_type' => 'Ride-out, birthday, launch, corporate, and so on.',
        'special_requests' => 'Layout, sound system, catering access, parking needs.',
        'applicant_type' => 'Individual racer or team.',
        'profile' => 'A short background on you or your team.',
        'achievements' => 'Recent podiums, championships, and notable finishes.',
        'social_media_urls' => 'Facebook, Instagram, or TikTok links, one per line.',
        'requested_sponsorship' => 'Parts, decals, fuel support, cash, or a mix. Be specific.',
        'sponsorship_requirements' => 'Packages, deliverables, and what sponsors receive in return.',
        'available_booth_size' => 'For example: 3m x 3m tent space.',
        'notes' => 'Optional.',
        'requirements' => 'Optional but helpful.',
    ];

    public function show(string $type): View
    {
        abort_unless(isset(self::FORMS[$type]), 404);

        return view('public.inquiry', [
            'type' => $type,
            'form' => self::FORMS[$type],
            'labels' => self::LABELS,
            'help' => self::HELP,
        ]);
    }

    public function track(): View
    {
        return view('public.track');
    }

    public function lookup(Request $request): View
    {
        $validated = $request->validate([
            'reference' => ['required', 'regex:/^IMP-\d{8}-\d{6}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ], ['reference.regex' => 'Enter the complete reference, for example IMP-20260906-000001.']);

        $id = (int) substr($validated['reference'], -6);
        $submission = PublicSubmission::find($id);
        $matches = $submission
            && hash_equals(strtolower((string) data_get($submission->data, 'email')), strtolower($validated['email']))
            && hash_equals($submission->referenceNumber(), strtoupper($validated['reference']));

        return view('public.track', ['submission' => $matches ? $submission : null, 'lookedUp' => true]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(isset(self::FORMS[$type]), 404);

        $rules = ['website' => ['nullable', 'max:0']];
        foreach (self::FORMS[$type]['fields'] as $field) {
            $rules[$field] = $this->rulesFor($field);
        }
        $rules['uploads'] = ['nullable', 'array', 'max:5'];
        $rules['uploads.*'] = ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'];

        $validated = $request->validate($rules);
        $uploads = [];
        foreach ($request->file('uploads', []) as $file) {
            // Stored under a random name, but the sender's own file name is kept
            // alongside it: staff need to see "proposal.pdf", not a hash.
            $uploads[] = [
                'path' => $file->store("public-submissions/$type", 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }

        $submission = PublicSubmission::create([
            'type' => $type.'_inquiry',
            'status' => 'new_inquiry',
            'data' => Arr::only($validated, self::FORMS[$type]['fields']),
            'uploads' => $uploads,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'submitted_at' => now(),
        ]);

        return back()
            ->with('success', self::FORMS[$type]['success'])
            ->with('reference', $submission->referenceNumber());
    }

    private function rulesFor(string $field): array
    {
        return match ($field) {
            'email' => ['required', 'email:rfc', 'max:255'],
            'contact_number' => ['required', 'string', 'max:40'],
            'date', 'preferred_date', 'birthday' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'start_time', 'end_time' => ['required', 'date_format:H:i'],
            'estimated_pax' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'existing_client', 'need_booth', 'need_raffle', 'need_marketing_support', 'can_set_up_booth' => ['required', Rule::in(['yes', 'no'])],
            'relationship_type' => ['nullable', 'required_if:existing_client,yes', Rule::in(['endorser', 'partner'])],
            'relationship_period', 'previous_campaign', 'previous_contact' => ['nullable', 'required_if:existing_client,yes', 'string', 'max:500'],
            'booth_requirements' => ['nullable', 'required_if:need_booth,yes', 'string', 'max:5000'],
            'raffle_requirements' => ['nullable', 'required_if:need_raffle,yes', 'string', 'max:5000'],
            'marketing_support_details' => ['nullable', 'required_if:need_marketing_support,yes', 'string', 'max:5000'],
            'notes', 'special_requests', 'profile', 'achievements', 'requested_sponsorship', 'sponsorship_requirements', 'requirements' => ['nullable', 'string', 'max:5000'],
            default => ['required', 'string', 'max:500'],
        };
    }
}
