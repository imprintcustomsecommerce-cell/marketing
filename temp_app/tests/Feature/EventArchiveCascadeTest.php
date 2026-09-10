<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventArchiveCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function event(User $by, string $name = 'Tanay Loop Run'): Event
    {
        $event = Event::create(['name' => $name, 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed', 'created_by' => $by->id]);
        app(CoverageDesk::class)->request($event, $by);

        return $event;
    }

    public function test_archiving_an_event_takes_its_tasks_off_the_boards(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);
        $this->assertSame(5, Task::where('event_id', $event->id)->count());

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive")->assertRedirect();

        $this->assertSame(0, Task::where('event_id', $event->id)->count(), 'archived work must not stay on boards');
        $this->assertSame(5, Task::withoutGlobalScope('not_archived')->where('event_id', $event->id)->count(),
            'the rows are kept, just hidden');
    }

    public function test_restoring_the_event_brings_the_tasks_back(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive");
        $this->actingAs($admin)->patch("/admin/events/{$event->id}/restore")->assertRedirect();

        $this->assertSame(5, Task::where('event_id', $event->id)->count());
    }

    public function test_an_archived_events_work_leaves_the_crew_queue_and_the_counts(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);
        $crew = User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'must_change_password' => false]);

        $this->actingAs($crew)->get('/admin/tasks')->assertOk()->assertSee('Cover event');

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive");

        $this->actingAs($crew)->get('/admin/tasks')->assertOk()->assertDontSee('Cover event');
        $this->assertSame(0, Task::unclaimedFor(User::TEAM_MULTIMEDIA)->count());
    }

    public function test_an_archived_event_is_not_offered_when_raising_a_task(): void
    {
        $admin = $this->admin();
        $live = $this->event($admin, 'Live Ride');
        $gone = $this->event($admin, 'Shelved Ride');

        $this->actingAs($admin)->patch("/admin/events/{$gone->id}/archive");

        $this->actingAs($admin)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Live Ride')
            ->assertDontSee('Shelved Ride');

        $this->assertNotNull($live->fresh());
    }
}
