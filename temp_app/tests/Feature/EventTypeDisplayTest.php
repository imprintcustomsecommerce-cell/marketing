<?php
namespace Tests\Feature;
use App\Models\Event; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class EventTypeDisplayTest extends TestCase {
    use RefreshDatabase;
    public function test_the_list_shows_the_chosen_event_type_not_the_hidden_category(): void {
        $admin = User::factory()->create(['role'=>'admin','team'=>User::TEAM_MARKETING,'must_change_password'=>false]);
        Event::create(['name'=>'Hall Job','category'=>'tambike','event_type'=>'in_house','event_category'=>'car',
            'event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);
        Event::create(['name'=>'Expo Job','category'=>'tambike','event_type'=>'outside_event','event_category'=>'automotive',
            'event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);

        $this->actingAs($admin)->get('/admin/events')->assertOk()
            ->assertSee('IN-HOUSE')->assertSee('OUTSIDE EVENT')
            ->assertDontSee('Tambike Event');
    }
    public function test_the_detail_page_shows_type_and_category(): void {
        $admin = User::factory()->create(['role'=>'admin','team'=>User::TEAM_MARKETING,'must_change_password'=>false]);
        $e = Event::create(['name'=>'Hall Job','category'=>'tambike','event_type'=>'in_house','event_category'=>'car',
            'event_date'=>today(),'status'=>'confirmed','created_by'=>$admin->id]);
        $this->actingAs($admin)->get("/admin/events/{$e->id}")->assertOk()
            ->assertSee('IN-HOUSE')->assertSee('Car');
    }
}
