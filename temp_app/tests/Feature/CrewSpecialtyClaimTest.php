<?php
namespace Tests\Feature;
use App\Models\{User,Event,Task};
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewSpecialtyClaimTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $admin): Event
    {
        $event = Event::create(['name'=>'Ride Out','category'=>'tambike','event_type'=>'tambike',
            'event_category'=>'motorcycle','event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);
        app(CoverageDesk::class)->request($event, $admin);
        return $event;
    }

    private function accepts(string $specialty): array
    {
        $admin = User::factory()->create(['role'=>'admin','team'=>'marketing']);
        $crew = User::factory()->create(['role'=>'staff','team'=>'multimedia','multimedia_specialty'=>$specialty]);
        $event = $this->event($admin);

        $this->actingAs($crew)->post("/admin/coverage/{$event->id}/respond", ['answer'=>'accept','specialty'=>$specialty])
            ->assertRedirect();

        return [$event->fresh(), $crew];
    }

    public function test_a_photo_editor_claims_the_photo_work_and_is_named_on_the_coverage(): void
    {
        [$event, $crew] = $this->accepts('photo');

        $this->assertSame($crew->id, $event->coverage->photo_editor_id);
        $this->assertSame($crew->id, $event->tasks->firstWhere('title','Edit and deliver event photos')->user_id);
        $this->assertNull($event->tasks->firstWhere('title','Edit and deliver event videos')->user_id);
        $this->assertNull($event->tasks->firstWhere('title','Cover event')->user_id);
    }

    public function test_a_video_editor_claims_the_video_work(): void
    {
        [$event, $crew] = $this->accepts('video');

        $this->assertSame($crew->id, $event->coverage->video_editor_id);
        $this->assertSame($crew->id, $event->tasks->firstWhere('title','Edit and deliver event videos')->user_id);
        $this->assertNull($event->tasks->firstWhere('title','Edit and deliver event photos')->user_id);
    }

    public function test_a_shooter_claims_every_shooting_task_including_the_shot_list(): void
    {
        [$event, $crew] = $this->accepts('shooter');

        $this->assertSame($crew->id, $event->coverage->shooter_id);
        foreach (['Prepare brief and shot list','Check gear and coverage plan','Cover event'] as $title) {
            $this->assertSame($crew->id, $event->tasks->firstWhere('title',$title)->user_id, $title);
        }
        $this->assertNull($event->tasks->firstWhere('title','Edit and deliver event photos')->user_id);
    }

    public function test_the_coverage_screen_still_offers_the_roles_nobody_has_taken(): void
    {
        $admin = User::factory()->create(['role'=>'admin','team'=>'marketing']);
        $event = $this->event($admin);
        $shooter = User::factory()->create(['role'=>'staff','team'=>'multimedia','multimedia_specialty'=>'shooter']);

        $this->actingAs($shooter)->post("/admin/coverage/{$event->id}/respond", ['answer'=>'accept','specialty'=>'shooter']);

        // The job has been answered, but the editing roles are still going
        // begging — the crew must still be able to take them on.
        $editor = User::factory()->create(['role'=>'staff','team'=>'multimedia','multimedia_specialty'=>'photo']);
        $this->actingAs($editor)->get('/admin/coverage?show=all')
            ->assertOk()
            ->assertSee('name="specialty" value="photo"', false)
            ->assertSee('name="specialty" value="video"', false)
            ->assertDontSee('name="specialty" value="shooter"', false);
    }

    public function test_each_role_can_take_its_own_share_of_one_event(): void
    {
        $admin = User::factory()->create(['role'=>'admin','team'=>'marketing']);
        $event = $this->event($admin);

        foreach (['shooter','photo','video'] as $specialty) {
            $crew = User::factory()->create(['role'=>'staff','team'=>'multimedia','multimedia_specialty'=>$specialty]);
            $this->actingAs($crew)->post("/admin/coverage/{$event->id}/respond", ['answer'=>'accept','specialty'=>$specialty]);
        }

        $this->assertSame(0, Task::where('event_id',$event->id)->whereNull('user_id')->count(),
            'every generated task should end up with an owner');
    }
}
