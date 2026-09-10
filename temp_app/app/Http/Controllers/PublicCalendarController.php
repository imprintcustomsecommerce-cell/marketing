<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The events feed the website calendar reads.
 *
 * Read-only and unauthenticated by design: it is fetched by a shop front the
 * Hub has no session on. Everything it returns is named in
 * Event::toPublicCalendarEntry(), so publishing is a decision made in one
 * place rather than an accident of which columns exist.
 */
class PublicCalendarController extends Controller
{
    /** Far enough ahead to fill a calendar, short enough to stay a small file. */
    private const MONTHS_AHEAD = 12;

    private const MONTHS_BEHIND = 1;

    public function __invoke(Request $request): JsonResponse
    {
        $from = CarbonImmutable::today()->subMonths(self::MONTHS_BEHIND)->startOfMonth();
        $to = CarbonImmutable::today()->addMonths(self::MONTHS_AHEAD)->endOfMonth();

        $events = Event::publiclyListed()
            ->whereBetween('event_date', [$from, $to])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Event $event) => $event->toPublicCalendarEntry())
            ->values();

        return response()->json([
            'events' => $events,
            'generated_at' => now()->toIso8601String(),
        ])
            // The storefront is a different origin, so it has to be allowed to
            // read this. Only this one route is opened up.
            ->header('Access-Control-Allow-Origin', '*')
            // A calendar changes a few times a week at most. Caching keeps the
            // shop machine out of the path of every page view, and leaves the
            // storefront working for a while if the Hub goes offline.
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=86400');
    }
}
