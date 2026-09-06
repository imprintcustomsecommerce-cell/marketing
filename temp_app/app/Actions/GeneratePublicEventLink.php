<?php

namespace App\Actions;

use App\Models\PublicLink;
use Illuminate\Support\Arr;

class GeneratePublicEventLink
{
    private const ALLOWED_FIELDS = [
        'event_name', 'organization', 'date', 'time', 'venue', 'estimated_pax',
        'contact_person', 'program', 'booth_details', 'raffle_details', 'requirements',
        'client_notes',
    ];

    public function execute(int $eventId, array $eventData, array $selectedFields, array $options = []): PublicLink
    {
        $fields = array_values(array_intersect($selectedFields, self::ALLOWED_FIELDS));

        return PublicLink::create([
            'resource_type' => 'event',
            'resource_id' => $eventId,
            'visible_data' => Arr::only($eventData, $fields),
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'max_submissions' => $options['max_submissions'] ?? null,
            'password' => $options['password'] ?? null,
            'allow_confirmation' => $options['allow_confirmation'] ?? true,
            'allow_change_request' => $options['allow_change_request'] ?? true,
        ]);
    }
}
