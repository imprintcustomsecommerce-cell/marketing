<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\User;
use App\Models\Coverage;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'is_active' => true]);
    }

    public function test_event_creation_generates_the_multimedia_production_plan(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', [
            'name' => 'Launch Night', 'category' => 'tambike', 'event_date' => today()->addWeek()->toDateString(), 'status' => 'confirmed',
        ])->assertRedirect();

        $event = Event::firstOrFail();
        $this->assertCount(4, Task::where('event_id', $event->id)->where('for_team', User::TEAM_MULTIMEDIA)->get());
        $this->assertTrue($event->coverage->photo_due_on->isSameDay($event->event_date->copy()->addDays(2)));
        $this->assertTrue($event->coverage->video_due_on->isSameDay($event->event_date->copy()->addDays(4)));
    }

    public function test_client_can_track_only_with_matching_reference_and_email(): void
    {
        $submission = PublicSubmission::create(['type' => 'event_inquiry', 'status' => 'contacted', 'data' => ['email' => 'client@example.com', 'event_name' => 'Launch'], 'submitted_at' => now()]);

        $this->post('/client/track', ['reference' => $submission->referenceNumber(), 'email' => 'client@example.com'])
            ->assertOk()->assertSee('Under review')->assertSee('Launch');
        $this->post('/client/track', ['reference' => $submission->referenceNumber(), 'email' => 'wrong@example.com'])
            ->assertOk()->assertSee('could not match');
    }

    public function test_archived_events_leave_the_active_list_and_can_be_restored(): void
    {
        $admin = $this->admin();
        $event = Event::create(['name' => 'Old Event', 'category' => 'tambike', 'event_date' => today(), 'status' => 'completed', 'created_by' => $admin->id]);

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/archive")->assertRedirect('/admin/events');
        $this->actingAs($admin)->get('/admin/events?when=all')->assertDontSee('Old Event');
        $this->actingAs($admin)->get('/admin/events?when=archived')->assertSee('Old Event');
        $this->actingAs($admin)->patch("/admin/events/{$event->id}/restore")->assertRedirect("/admin/events/{$event->id}");
    }

    public function test_public_update_is_visible_only_through_matching_tracking_lookup(): void
    {
        $admin = $this->admin();
        $submission = PublicSubmission::create(['type' => 'event_inquiry', 'status' => 'new_inquiry', 'priority' => 'normal', 'data' => ['email' => 'client@example.com'], 'submitted_at' => now()]);

        $this->actingAs($admin)->put("/admin/inquiries/{$submission->id}", [
            'status' => 'reviewing', 'priority' => 'normal', 'public_update' => 'We are checking the requested date now.',
        ])->assertRedirect();

        $this->post('/client/track', ['reference' => $submission->referenceNumber(), 'email' => 'client@example.com'])
            ->assertSee('We are checking the requested date now.');
    }

    public function test_finished_checklist_completes_production_and_delivery_is_attributed(): void
    {
        $admin = $this->admin();
        $event = Event::create(['name' => 'Media Launch', 'category' => 'tambike', 'event_date' => today(), 'status' => 'confirmed', 'created_by' => $admin->id]);
        $coverage = Coverage::create(['event_id' => $event->id, 'stage' => CoverageDesk::ACCEPTED, 'delivery_url' => 'https://example.com/final', 'created_by' => $admin->id]);

        $this->actingAs($admin)->put("/admin/coverage/{$event->id}", [
            'photo_status' => 'posted', 'video_status' => 'posted', 'checklist' => array_keys(Coverage::CHECKLIST),
            'photo_posted_on' => today()->toDateString(), 'video_posted_on' => today()->toDateString(), 'delivery_url' => 'https://example.com/final',
        ])->assertRedirect();
        $this->assertSame(CoverageDesk::COMPLETED, $coverage->fresh()->stage);

        $this->actingAs($admin)->post("/admin/coverage/{$event->id}/confirm-delivery")->assertRedirect();
        $this->assertSame($admin->id, $coverage->fresh()->delivery_sent_by);
        $this->assertNotNull($coverage->fresh()->delivery_sent_at);
    }

    public function test_event_date_changes_move_unfinished_production_work(): void
    {
        $admin=$this->admin(); $event=Event::create(['name'=>'Move Me','category'=>'tambike','event_date'=>today()->addDays(5),'status'=>'confirmed','created_by'=>$admin->id]);
        $coverage=app(CoverageDesk::class)->request($event,$admin); $task=$event->tasks()->first(); $oldTaskDate=$task->task_date->copy();
        $this->actingAs($admin)->put("/admin/events/{$event->id}",['name'=>'Move Me','category'=>'tambike','event_date'=>today()->addDays(8)->toDateString(),'status'=>'confirmed'])->assertRedirect();
        $this->assertTrue($task->fresh()->task_date->isSameDay($oldTaskDate->addDays(3)));
        $this->assertTrue($coverage->fresh()->photo_due_on->isSameDay(today()->addDays(10)));
    }

    public function test_revisions_summary_and_security_screen_work(): void
    {
        $admin=$this->admin(); $event=Event::create(['name'=>'Revision Event','category'=>'tambike','event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);
        $coverage=Coverage::create(['event_id'=>$event->id,'stage'=>CoverageDesk::ACCEPTED,'created_by'=>$admin->id]);
        $this->actingAs($admin)->post("/admin/coverage/{$event->id}/revisions",['notes'=>'Shorten the reel','due_on'=>today()->addDay()->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('coverage_revisions',['coverage_id'=>$coverage->id,'round'=>1,'notes'=>'Shorten the reel']);
        $this->actingAs($admin)->get("/admin/events/{$event->id}/summary")->assertOk()->assertSee('Revision Event')->assertSee('Shorten the reel');
        $this->actingAs($admin)->get('/admin/system')->assertOk()->assertSee('System &amp; security',false);
    }

    public function test_default_password_accounts_are_sent_to_change_their_password(): void
    {
        $user=User::factory()->create(['must_change_password'=>true]);
        $this->actingAs($user)->get('/admin')->assertRedirect('/admin/account');
        $this->actingAs($user)->get('/admin/account')->assertOk();
    }

    public function test_a_status_change_is_recorded_and_global_search_finds_records(): void
    {
        $admin=$this->admin(); $event=Event::create(['name'=>'Searchable Launch','category'=>'tambike','event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);
        $submission=PublicSubmission::create(['type'=>'event_inquiry','status'=>'new_inquiry','priority'=>'normal','data'=>['email'=>'client@example.com','contact_person'=>'Client'],'submitted_at'=>now(),'converted_event_id'=>$event->id]);
        $this->actingAs($admin)->put("/admin/inquiries/{$submission->id}",['status'=>'reviewing','priority'=>'normal','public_update'=>'We are reviewing your schedule.'])->assertRedirect();
        $this->assertSame('reviewing', $submission->fresh()->status);
        $this->actingAs($admin)->get('/admin/search?q=Searchable')->assertOk()->assertSee('Searchable Launch');
    }
}
