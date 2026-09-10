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

    public function test_only_events_marked_for_the_website_are_published(): void
    {
        $this->event(['name' => 'Public Ride', 'is_public' => true]);
        $this->event(['name' => 'Private Hall Booking', 'is_public' => false]);

        $response = $this->getJson('/client/calendar.json')->assertOk();

        $titles = collect($response->json('events'))->pluck('title');
        $this->assertContains('Public Ride', $titles);
        $this->assertNotContains('Private Hall Booking', $titles);
    }

    public function test_publishing_is_off_unless_somebody_asks_for_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', [
            'name' => 'Quiet Booking', 'category' => 'tambike', 'event_type' => 'in_house',
            'event_category' => 'others', 'event_date' => today()->addWeek()->toDateString(), 'status' => 'confirmed',
        ])->assertRedirect();

        $this->assertFalse(Event::where('name', 'Quiet Booking')->sole()->is_public);
    }

    public function test_unticking_takes_an_event_back_off_the_website(): void
    {
        $admin = $this->admin();
        $event = $this->event(['is_public' => true]);

        // A cleared checkbox posts nothing at all.
        $this->actingAs($admin)->put("/admin/events/{$event->id}", [
            'name' => $event->name, 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => $event->event_date->toDateString(), 'status' => 'confirmed',
        ])->assertRedirect();

        $this->assertFalse($event->fresh()->is_public);
        $this->assertEmpty($this->getJson('/client/calendar.json')->json('events'));
    }

    public function test_cancelled_and_archived_events_drop_off_the_website(): void
    {
        $this->event(['name' => 'Called Off', 'is_public' => true, 'status' => 'cancelled']);
        $this->event(['name' => 'Shelved', 'is_public' => true, 'archived_at' => now()]);
        $this->event(['name' => 'Still On', 'is_public' => true]);

        $titles = collect($this->getJson('/client/calendar.json')->json('events'))->pluck('title');

        $this->assertSame(['Still On'], $titles->all());
    }

    public function test_the_feed_gives_out_nothing_beyond_the_whitelist(): void
    {
        $this->event([
            'is_public' => true,
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
            ['id', 'title', 'date', 'end_date', 'start_time', 'end_time', 'venue', 'type', 'type_label', 'summary'],
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
        $this->event(['is_public' => true, 'duration_days' => 3]);

        $entry = $this->getJson('/client/calendar.json')->json('events.0');

        $this->assertSame(today()->addWeek()->toDateString(), $entry['date']);
        $this->assertSame(today()->addWeek()->addDays(2)->toDateString(), $entry['end_date']);
    }

    public function test_the_feed_can_be_read_from_another_site(): void
    {
        $this->event(['is_public' => true]);

        $this->getJson('/client/calendar.json')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_the_feed_needs_no_sign_in(): void
    {
        $this->event(['is_public' => true]);

        $this->assertGuest();
        $this->getJson('/client/calendar.json')->assertOk();
    }
}
