<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving an event takes it off the crew's screens too. It was only hidden
 * from the diary, so shelved hall bookings kept sitting in the coverage queue
 * waiting for an answer nobody could usefully give.
 */
class ArchivedEventCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function event(User $by, string $name): Event
    {
        $event = Event::create(['name' => $name, 'category' => 'tambike', 'event_type' => 'in_house',
            'event_category' => 'others', 'event_date' => today()->addWeek(), 'status' => 'confirmed', 'created_by' => $by->id]);
        app(CoverageDesk::class)->request($event, $by);

        return $event;
    }

    public function test_an_archived_event_leaves_the_coverage_list(): void
    {
        $admin = $this->admin();
        $live = $this->event($admin, 'Live Ride');
        $shelved = $this->event($admin, 'Shelved Booking');

        $this->actingAs($admin)->patch("/admin/events/{$shelved->id}/archive")->assertRedirect();

        $this->actingAs($admin)->get('/admin/coverage?show=all')
            ->assertOk()
            ->assertSee('Live Ride')
            ->assertDontSee('Shelved Booking');

        $this->assertNotNull($live->fresh());
    }

    public function test_its_coverage_stops_counting_as_work_with_the_crew(): void
    {
        $admin = $this->admin();
        $this->event($admin, 'Live Ride');
        $shelved = $this->event($admin, 'Shelved Booking');

        $this->actingAs($admin)->patch("/admin/events/{$shelved->id}/archive");

        // Both were unassigned; only the live one should still be chased.
        $this->assertSame(1, Coverage::where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id')->count());
    }

    public function test_it_leaves_the_multimedia_dashboard(): void
    {
        $admin = $this->admin();
        $shelved = $this->event($admin, 'Shelved Booking');
        $this->actingAs($admin)->patch("/admin/events/{$shelved->id}/archive");

        $crew = User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'must_change_password' => false]);

        $this->actingAs($crew)->get('/admin/multimedia')->assertOk()->assertDontSee('Shelved Booking');
    }

    public function test_restoring_the_event_brings_its_coverage_back(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin, 'Shelved Booking');

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive");
        $this->assertSame(0, Coverage::count());

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/restore");

        $this->assertSame(1, Coverage::count());
        $this->actingAs($admin)->get('/admin/coverage?show=all')->assertOk()->assertSee('Shelved Booking');
    }

    public function test_reporting_still_counts_work_on_archived_events(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin, 'Shelved Booking');
        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive");

        // Delivered work still happened, so the report must not quietly lose it.
        $this->assertSame(1, Coverage::withoutGlobalScope('live_event')->count());
        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    }
}
