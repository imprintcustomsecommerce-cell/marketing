<?php

namespace App\Support;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\PublicSubmission;
use App\Models\User;

/**
 * Turns a public inquiry into the record the team actually works from.
 *
 * The mapping is deliberately generous: it fills in whatever the form gave us
 * and leaves the rest blank for staff to finish, rather than refusing to
 * convert because one optional answer is missing.
 */
class InquiryConverter
{
    /** Which public form becomes which kind of event. */
    private const CATEGORIES = [
        'tambike_inquiry' => 'tambike',
        'function-hall_inquiry' => 'function_hall',
        'external-event_inquiry' => 'external_sponsorship',
        'event_inquiry' => 'tambike',
    ];

    /** A racer's sponsorship application is a person, not an event. */
    public const ENDORSER_TYPE = 'sponsorship_inquiry';

    public function canBecomeEvent(PublicSubmission $inquiry): bool
    {
        return array_key_exists($inquiry->type, self::CATEGORIES) && $inquiry->converted_event_id === null;
    }

    public function canBecomeEndorser(PublicSubmission $inquiry): bool
    {
        return $inquiry->type === self::ENDORSER_TYPE && $inquiry->converted_endorser_id === null;
    }

    public function toEvent(PublicSubmission $inquiry, User $actor): Event
    {
        $data = $inquiry->data ?? [];

        $event = Event::create([
            'name' => $inquiry->subject(),
            'category' => self::CATEGORIES[$inquiry->type] ?? 'tambike',
            'organization' => $this->first($data, ['organizer', 'organization', 'group_name']),
            // A date is required on an event but optional on some forms, so fall
            // back to today and let staff correct it on the screen that opens.
            'event_date' => $this->first($data, ['date', 'preferred_date']) ?: today()->toDateString(),
            'start_time' => $this->time($data['start_time'] ?? null),
            'end_time' => $this->time($data['end_time'] ?? null),
            'venue' => $this->first($data, ['venue', 'location']),
            'estimated_pax' => is_numeric($data['estimated_pax'] ?? null) ? (int) $data['estimated_pax'] : null,
            'status' => 'new',
            'notes' => $this->notes($inquiry, $data),
            'created_by' => $actor->id,
        ]);

        // An event that arrived through a public form still needs shooting, so
        // it reaches the crew the same way one typed in by hand does.
        // CoverageDesk is a neighbour in App\Support, so it needs no import.
        app(CoverageDesk::class)->request($event, $actor);

        $inquiry->update([
            'converted_event_id' => $event->id,
            'status' => 'confirmed',
            'handled_by' => $actor->id,
            'handled_at' => now(),
        ]);

        return $event;
    }

    public function toEndorser(PublicSubmission $inquiry, User $actor, string $conversion = 'endorser'): Endorser
    {
        $data = $inquiry->data ?? [];

        $endorser = Endorser::create([
            'name' => $data['racer_team_name'] ?? $inquiry->subject(),
            'type' => $conversion === 'partner'
                ? 'organization'
                : (str_contains(mb_strtolower($data['applicant_type'] ?? ''), 'team') ? 'team' : 'racer'),
            'contact_number' => $data['contact_number'] ?? null,
            'email' => $data['email'] ?? null,
            'team_or_group' => $data['team'] ?? null,
            // The form takes several links, one per line; the record holds one.
            'social_media_url' => $this->firstUrl($data['social_media_urls'] ?? null),
            'status' => 'new',
            'profile' => trim(implode("\n\n", array_filter([
                $data['profile'] ?? null,
                ($data['achievements'] ?? null) ? 'Achievements: '.$data['achievements'] : null,
            ]))) ?: null,
            'notes' => $this->notes($inquiry, $data, ['address', 'birthday', 'motorcycle', 'racing_category', 'requested_sponsorship']),
            'created_by' => $actor->id,
        ]);

        $inquiry->update([
            'converted_endorser_id' => $endorser->id,
            'status' => 'confirmed',
            'handled_by' => $actor->id,
            'handled_at' => now(),
        ]);

        return $endorser;
    }

    private function first(array $data, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (! empty($data[$field])) {
                return $data[$field];
            }
        }

        return null;
    }

    /**
     * Times arrive as "08:00" from the form, but the column stores H:i and a
     * malformed value should not sink the whole conversion.
     */
    private function time(?string $value): ?string
    {
        return $value && preg_match('/^\d{1,2}:\d{2}/', $value) ? mb_substr($value, 0, 5) : null;
    }

    private function firstUrl(?string $value): ?string
    {
        foreach (preg_split('/[\s,]+/', (string) $value) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                return mb_substr($candidate, 0, 500);
            }
        }

        return null;
    }

    /**
     * Carries the sender's free text across, plus a line saying where the record
     * came from, so nobody has to guess later.
     */
    private function notes(PublicSubmission $inquiry, array $data, array $extra = []): string
    {
        $lines = ['Created from inquiry #'.$inquiry->id.' received '.$inquiry->submitted_at?->format('M j, Y').'.'];

        foreach (array_merge($extra, ['special_requests', 'requirements', 'raffle_requirements', 'sponsorship_requirements', 'notes']) as $field) {
            if (! empty($data[$field])) {
                $lines[] = str($field)->replace('_', ' ')->title().': '.$data[$field];
            }
        }

        if ($inquiry->internal_notes) {
            $lines[] = 'Internal: '.$inquiry->internal_notes;
        }

        return implode("\n", $lines);
    }
}
