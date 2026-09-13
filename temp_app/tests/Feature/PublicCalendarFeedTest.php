<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Sunday Ride-Out', 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed',
            'venue' => 'Imprint Customs', 'created_by' => $this->admin()->id,
        ], $overrides));
    }

    public function test_every_live_event_is_published(): void
    {
        $this->event(['name' => 'Public Ride']);
        $this->event(['name' => 'Hall Booking']);

        $titles = collect($this->getJson('/client/calendar.json')->assertOk()->json('events'))->pluck('title');

        $this->assertContains('Public Ride', $titles);
        $this->assertContains('Hall Booking', $titles);
    }

    public function test_an_event_reaches_the_website_with_no_extra_step(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', [
            'name' => 'New Booking', 'category' => 'tambike', 'event_type' => 'in_house',
            'event_category' => 'others', 'event_date' => today()->addWeek()->toDateString(), 'status' => 'confirmed',
        ])->assertRedirect();

        $titles = collect($this->getJson('/client/calendar.json')->json('events'))->pluck('title');

        $this->assertContains('New Booking', $titles, 'booking an event is what publishes it; there is no tick to forget');
    }

    public function test_archiving_takes_an_event_back_off_the_website(): void
    {
        $admin = $this->admin();
        $event = $this->event();

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive")->assertRedirect();

        $this->assertEmpty(
            $this->getJson('/client/calendar.json')->json('events'),
            'archiving is the way to take a booking off the website',
        );
    }

    public function test_cancelled_and_archived_events_drop_off_the_website(): void
    {
        $this->event(['name' => 'Called Off', 'status' => 'cancelled']);
        $this->event(['name' => 'Shelved', 'archived_at' => now()]);
        $this->event(['name' => 'Still On']);

        $titles = collect($this->getJson('/client/calendar.json')->json('events'))->pluck('title');

        $this->assertSame(['Still On'], $titles->all());
    }

    public function test_the_feed_gives_out_nothing_beyond_the_whitelist(): void
    {
        $this->event([
            'public_summary' => 'Meet at the shop, 7am.',
            'contact_person' => 'Ana Reyes',
            'contact_number' => '09171234567',
            'contact_email' => 'ana@example.test',
            'notes' => 'Client still owes a deposit',
            'deal_type' => 'cash',
            'cash_amount' => '15000',
        ]);

        $response = $this->getJson('/client/calendar.json')->assertOk();
        $entry = $response->json('events.0');

        $this->assertSame(
            ['id', 'title', 'date', 'end_date', 'start_time', 'end_time', 'venue', 'organization', 'type', 'type_label', 'summary'],
            array_keys($entry),
        );
        $this->assertSame('Meet at the shop, 7am.', $entry['summary']);

        $body = $response->getContent();
        foreach (['Ana Reyes', '09171234567', 'ana@example.test', 'owes a deposit', '15000'] as $private) {
            $this->assertStringNotContainsString($private, $body, "$private must never reach the website");
        }
    }

    public function test_a_multi_day_event_carries_its_last_day(): void
    {
        $this->event(['duration_days' => 3]);

        $entry = $this->getJson('/client/calendar.json')->json('events.0');

        $this->assertSame(today()->addWeek()->toDateString(), $entry['date']);
        $this->assertSame(today()->addWeek()->addDays(2)->toDateString(), $entry['end_date']);
    }

    public function test_the_feed_can_be_read_from_another_site(): void
    {
        $this->event();

        $this->getJson('/client/calendar.json')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_the_feed_needs_no_sign_in(): void
    {
        $this->event();

        $this->assertGuest();
        $this->getJson('/client/calendar.json')->assertOk();
    }
}
