<?php
namespace Tests\Feature;
use App\Models\{User,Event,PublicSubmission,Coverage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CompleteWorkflowTest extends TestCase {
 use RefreshDatabase;
 public function test_client_inquiry_flows_through_automatic_multimedia_work_to_delivery(): void {
  $this->post('/client/inquiry',['event_name'=>'Workflow rehearsal','organization'=>'Test Club','contact_person'=>'Test Client','contact_number'=>'09171234567','email'=>'workflow@example.test','date'=>today()->addWeek()->toDateString(),'venue'=>'Test venue','estimated_pax'=>50])->assertSessionHasNoErrors()->assertRedirect();
  $inquiry=PublicSubmission::sole();
  $admin=User::factory()->create(['role'=>'admin','team'=>'marketing']);
  $crew=User::factory()->create(['role'=>'staff','team'=>'multimedia']);
  $this->actingAs($admin)->post('/admin/inquiries/'.$inquiry->id.'/convert')->assertSessionHasNoErrors()->assertRedirect();
  $event=Event::findOrFail($inquiry->fresh()->converted_event_id);
  // Converting an inquiry hands the job to the crew, same as booking one.
  $this->assertSame('accepted',$event->coverage->stage);
  // Three shooting tasks plus one each for the photo and video editors.
  $this->assertCount(5,$event->tasks);
  $this->assertTrue($event->tasks->every(fn($t)=>$t->for_team==='multimedia'));
  $this->actingAs($crew)->post('/admin/coverage/'.$event->id.'/respond',['answer'=>'accept'])->assertRedirect();
  // Accepting claims the tasks for the specialty taken on, not the whole plan:
  // a shooter picks up the shoot and is named on the coverage, while the edit
  // work stays in the queue for whoever takes the photo and video roles.
  $this->assertSame($crew->id,$event->fresh()->coverage->shooter_id);
  $this->assertSame($crew->id,$event->fresh()->tasks->firstWhere('title','Cover event')->user_id);
  $this->put('/admin/coverage/'.$event->id,['photo_status'=>'posted','video_status'=>'posted','photo_posted_on'=>today()->toDateString(),'video_posted_on'=>today()->toDateString(),'checklist'=>array_keys(Coverage::CHECKLIST),'delivery_url'=>'https://example.com/final'])->assertSessionHasNoErrors()->assertRedirect();
  $this->post('/admin/coverage/'.$event->id.'/confirm-delivery')->assertRedirect();
  $this->assertSame('completed',$event->fresh()->coverage->stage);
  $this->assertSame($crew->id,$event->fresh()->coverage->delivery_sent_by);
  $this->assertNotNull($event->fresh()->coverage->delivery_sent_at);
  // A synthetic, authenticated render for visual review; no real records or session cookies.
  $html=$this->actingAs($admin)->get('/admin')->assertOk()->getContent();
  if (getenv('IMPRINT_VISUAL_REHEARSAL') === '1') {
   \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/qa-preview'));
   \Illuminate\Support\Facades\File::put(storage_path('app/qa-preview/index.html'), $html);
  }
 }
}
