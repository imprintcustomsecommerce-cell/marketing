<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPreparationChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function marketing(): User
    {
        return User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function crew(): User
    {
        return User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'must_change_password' => false]);
    }

    private function event(User $by): Event
    {
        $event = Event::create(['name' => 'Bike Expo', 'category' => 'tambike', 'event_type' => 'outside_event',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed',
            'created_by' => $by->id, 'preparation' => ['tent'],
            'custom_preparation' => [['label' => 'Borrowed generator', 'done' => false]]]);
        app(CoverageDesk::class)->request($event, $by);

        return $event;
    }

    public function test_marketing_can_tick_items_from_the_event_page(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", [
            'preparation_done' => ['tent'],
            'custom_done' => [0],
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame(['tent'], $event->preparation_done);
        $this->assertSame(['tent'], $event->preparation, 'what the event needs is not changed by ticking');
        $this->assertTrue($event->customPreparation()[0]['done']);
        $this->assertTrue($event->isPreparationComplete());
    }

    public function test_the_crew_can_tick_items_too(): void
    {
        $event = $this->event($this->admin());

        // The crew cannot open the event record at all, which is why the
        // checklist lives on their coverage screen as well.
        $crew = $this->crew();
        $this->actingAs($crew)->get("/admin/events/{$event->id}")->assertForbidden();

        $this->actingAs($crew)->patch("/admin/events/{$event->id}/preparation", [
            'preparation_done' => ['tent'],
        ])->assertRedirect();

        $this->assertSame(['tent'], $event->fresh()->preparation_done);
    }

    public function test_unticking_an_item_is_stored_as_unticked(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        // A cleared checkbox posts nothing, so an absent list has to mean none.
        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", [])->assertRedirect();

        $event->refresh();
        $this->assertSame([], $event->preparation_done);
        $this->assertFalse($event->customPreparation()[0]['done']);
        $this->assertSame(['tent'], $event->preparation, 'the list of what is needed survives');
    }

    public function test_ticking_never_reworded_a_custom_item(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", ['custom_done' => [0]]);

        $this->assertSame('Borrowed generator', $event->fresh()->customPreparation()[0]['label']);
    }

    public function test_a_tick_for_something_no_longer_needed_is_dropped(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", [
            'preparation_done' => ['tent', 'sound'],
        ])->assertRedirect();

        // "sound" was never on this event's list, so it cannot be marked done.
        $this->assertSame(['tent'], $event->fresh()->preparation_done);
    }

    public function test_an_event_that_needs_nothing_shows_no_checklist(): void
    {
        $admin = $this->admin();
        $bare = Event::create(['name' => 'Hall Booking', 'category' => 'function_hall', 'event_type' => 'in_house',
            'event_category' => 'others', 'event_date' => today()->addWeek(), 'status' => 'confirmed', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get("/admin/events/{$bare->id}")
            ->assertOk()
            ->assertDontSee('Before the event');
    }

    public function test_the_panel_appears_on_both_screens(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->get("/admin/events/{$event->id}")
            ->assertOk()->assertSee('Before the event')->assertSee('Borrowed generator');

        $this->actingAs($this->crew())->get("/admin/coverage/{$event->id}")
            ->assertOk()->assertSee('Before the event')->assertSee('Borrowed generator');
    }

    public function test_an_unknown_item_is_rejected(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", ['preparation_done' => ['not_a_real_item']])
            ->assertSessionHasErrors('preparation_done.0');
    }
}
