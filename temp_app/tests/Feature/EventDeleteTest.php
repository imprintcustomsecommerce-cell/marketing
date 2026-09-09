<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\EventFile;
use App\Models\PublicLink;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function marketingStaff(): User
    {
        return User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function event(User $by): Event
    {
        $event = Event::create(['name' => 'Cancelled Ride', 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed', 'created_by' => $by->id]);
        app(CoverageDesk::class)->request($event, $by);

        return $event;
    }

    public function test_an_administrator_can_delete_an_event_and_everything_filed_under_it(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->post("/admin/events/{$event->id}/files", [
            'file' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf'),
        ]);
        $file = EventFile::sole();
        Storage::disk('local')->assertExists($file->path);

        PublicLink::create(['token' => (string) Str::uuid(), 'resource_type' => 'event', 'resource_id' => $event->id,
            'visible_data' => ['name' => $event->name], 'created_by' => $admin->id]);

        $this->assertSame(5, Task::where('event_id', $event->id)->count());

        $this->actingAs($admin)->delete("/admin/events/{$event->id}")
            ->assertRedirect('/admin/events');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertSame(0, Coverage::where('event_id', $event->id)->count(), 'coverage should go with it');
        $this->assertSame(0, Task::where('event_id', $event->id)->count(), 'production tasks must not be stranded');
        $this->assertSame(0, EventFile::where('event_id', $event->id)->count());
        $this->assertSame(0, PublicLink::where('resource_type', 'event')->where('resource_id', $event->id)->count(),
            'a shared link must not outlive the event');
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_marketing_staff_cannot_delete_an_event(): void
    {
        $event = $this->event($this->admin());

        $this->actingAs($this->marketingStaff())->delete("/admin/events/{$event->id}")->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_archiving_still_keeps_the_record(): void
    {
        $admin = $this->admin();
        $event = $this->event($admin);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive")->assertRedirect();

        $this->assertNotNull($event->fresh()->archived_at);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
