<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarScreenTest extends TestCase
{
    use RefreshDatabase;

    private function joey(): User
    {
        return User::create([
            'name' => 'Joey',
            'email' => 'joey@example.test',
            'password' => 'secret1234',
            'role' => 'admin',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);
    }

    /**
     * One busy day carrying one of everything.
     */
    private function seedDay(): string
    {
        $day = today()->startOfMonth()->addDays(9);
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        $event = Event::create(['name' => 'Ride-Out Manila', 'category' => 'tambike', 'event_date' => $day, 'venue' => 'Clark', 'status' => 'confirmed']);

        PrKit::create(['recipient' => 'Team Redline', 'purpose' => 'endorser', 'endorser_id' => $endorser->id, 'delivery_date' => $day, 'status' => 'delivered']);
        PrKit::create(['recipient' => 'Expo raffle', 'purpose' => 'giveaway', 'event_id' => $event->id, 'quantity' => 40, 'delivery_date' => $day, 'status' => 'packed']);
        PrKit::create(['recipient' => 'Team Redline', 'purpose' => 'endorser', 'endorser_id' => $endorser->id, 'pickup_date' => $day, 'status' => 'awaiting_pickup']);
        Obligation::create(['endorser_id' => $endorser->id, 'title' => 'Post the reel', 'type' => 'content_video', 'due_date' => $day, 'status' => 'pending']);

        return $day->toDateString();
    }

    public function test_filters_hide_a_kind_without_losing_its_count(): void
    {
        $this->seedDay();
        $joey = $this->joey();

        // Only events: the kit and content entries drop out of the grid, but
        // their chips still report how much they would bring back.
        $this->actingAs($joey)->get('/admin/calendar?show=events')
            ->assertOk()
            ->assertSee('Ride-Out Manila')
            ->assertDontSee('Post the reel')
            ->assertDontSee('Giveaway · Expo raffle')
            ->assertSee('Show everything');

        $this->actingAs($joey)->get('/admin/calendar?show=content')
            ->assertOk()
            ->assertSee('Post the reel')
            ->assertDontSee('Ride-Out Manila');
    }

    public function test_an_unknown_filter_falls_back_to_showing_everything(): void
    {
        $this->seedDay();

        $this->actingAs($this->joey())->get('/admin/calendar?show=nonsense')
            ->assertOk()
            ->assertSee('Ride-Out Manila')
            ->assertSee('Post the reel');
    }

    public function test_a_day_can_be_opened_to_see_everything_on_it(): void
    {
        $day = $this->seedDay();

        // The cell itself only has room for three of the five entries.
        $response = $this->actingAs($this->joey())->get("/admin/calendar?date={$day}");

        $response->assertOk()
            ->assertSee('Ride-Out Manila')
            ->assertSee('Deliver · Team Redline')
            ->assertSee('Giveaway · Expo raffle (×40)')
            ->assertSee('Pick up · Team Redline')
            ->assertSee('Post the reel')
            // Labelled by kind, not colour alone.
            ->assertSee('PR kit out')
            ->assertSee('PR kit back')
            ->assertSee('Content due');
    }

    public function test_opening_a_day_keeps_the_current_filter(): void
    {
        $day = $this->seedDay();

        $this->actingAs($this->joey())->get("/admin/calendar?show=events&date={$day}")
            ->assertOk()
            ->assertSee('Ride-Out Manila')
            // The filter still applies inside the opened day.
            ->assertDontSee('Post the reel');
    }

    public function test_a_date_outside_the_shown_month_is_ignored(): void
    {
        $this->seedDay();

        $this->actingAs($this->joey())->get('/admin/calendar?date=1999-01-01')
            ->assertOk()
            ->assertDontSee('Nothing scheduled on this day');
    }

    public function test_rubbish_dates_and_months_do_not_break_the_page(): void
    {
        $joey = $this->joey();

        $this->actingAs($joey)->get('/admin/calendar?month=not-a-month&date=also-not-a-date')
            ->assertOk()
            ->assertSee(today()->format('F Y'));
    }
}
