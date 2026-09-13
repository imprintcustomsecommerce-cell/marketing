<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop runs several events in a week, so sharing a date is ordinary and
 * must not raise anything. Only the same place at the same time is a clash.
 */
class EventScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    /** @param array<string,mixed> $attributes */
    private function event(User $admin, array $attributes): Event
    {
        return Event::create($attributes + [
            'category' => 'function_hall', 'event_type' => 'tambike', 'event_category' => 'others',
            'event_date' => today(), 'status' => 'confirmed', 'created_by' => $admin->id,
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function save(User $admin, array $attributes)
    {
        return $this->actingAs($admin)->post('/admin/events', $attributes + [
            'category' => 'function_hall', 'event_type' => 'tambike', 'event_category' => 'others',
            'event_date' => today()->format('Y-m-d'), 'status' => 'confirmed',
        ]);
    }

    public function test_two_events_on_one_date_at_different_venues_are_not_a_clash(): void
    {
        $admin = $this->admin();
        $this->event($admin, ['name' => 'Ride Out', 'venue' => 'Tanay, Rizal']);

        $this->save($admin, ['name' => 'Blood Drive', 'venue' => 'Virtuosity Playground'])
            ->assertSessionMissing('warning');
    }

    public function test_the_same_venue_at_a_different_hour_is_not_a_clash(): void
    {
        $admin = $this->admin();
        $this->event($admin, ['name' => 'Morning Shoot', 'venue' => 'SMX MOA', 'start_time' => '08:00', 'end_time' => '11:00']);

        $this->save($admin, ['name' => 'Evening Shoot', 'venue' => 'SMX MOA', 'start_time' => '18:00', 'end_time' => '21:00'])
            ->assertSessionMissing('warning');
    }

    public function test_the_same_venue_at_an_overlapping_hour_is_a_clash(): void
    {
        $admin = $this->admin();
        $this->event($admin, ['name' => 'Morning Shoot', 'venue' => 'SMX MOA', 'start_time' => '08:00', 'end_time' => '12:00']);

        $this->save($admin, ['name' => 'Overlapping Shoot', 'venue' => 'smx moa ', 'start_time' => '11:00', 'end_time' => '14:00'])
            ->assertSessionHas('warning');
    }

    public function test_the_same_venue_with_no_times_given_is_still_raised(): void
    {
        $admin = $this->admin();
        $this->event($admin, ['name' => 'All Day Expo', 'venue' => 'SMX MOA']);

        $this->save($admin, ['name' => 'Second Booking', 'venue' => 'SMX MOA'])
            ->assertSessionHas('warning');
    }

    public function test_an_archived_event_is_not_counted_as_a_clash(): void
    {
        $admin = $this->admin();
        $this->event($admin, ['name' => 'Cancelled Expo', 'venue' => 'SMX MOA', 'archived_at' => now()]);

        $this->save($admin, ['name' => 'Real Expo', 'venue' => 'SMX MOA'])
            ->assertSessionMissing('warning');
    }
}
