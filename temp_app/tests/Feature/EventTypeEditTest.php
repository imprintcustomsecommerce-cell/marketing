<?php
namespace Tests\Feature;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTypeEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    public function test_the_edit_form_preselects_the_stored_event_type(): void
    {
        $admin = $this->admin();
        $event = Event::create(['name'=>'Hall Booking','category'=>'function_hall','event_type'=>'in_house',
            'event_category'=>'car','event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);

        $this->actingAs($admin)->get("/admin/events/{$event->id}/edit")->assertOk()
            ->assertSee('value="in_house" selected', false)
            ->assertSee('value="car" selected', false)
            ->assertSee('name="category" value="function_hall"', false);
    }

    public function test_changing_the_event_type_is_saved(): void
    {
        $admin = $this->admin();
        $event = Event::create(['name'=>'Hall Booking','category'=>'function_hall','event_type'=>'tambike',
            'event_category'=>'others','event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);

        $this->actingAs($admin)->put("/admin/events/{$event->id}", [
            'name'=>'Hall Booking','category'=>'function_hall','event_type'=>'outside_event',
            'event_category'=>'automotive','event_date'=>today()->format('Y-m-d'),'status'=>'confirmed',
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('outside_event', $event->event_type);
        $this->assertSame('automotive', $event->event_category);
        $this->assertSame('function_hall', $event->category, 'editing must not retag the event as tambike');
    }
}
