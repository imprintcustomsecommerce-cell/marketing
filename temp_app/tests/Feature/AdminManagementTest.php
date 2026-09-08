<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_and_admin_can_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => true, 'password' => 'secret-pass']);

        $this->get('/admin')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_authenticated_admin_can_add_event(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post('/admin/events', [
            'name' => 'Dealer Conference', 'category' => 'function_hall',
            'event_type' => 'in_house', 'event_category' => 'others',
            'organization' => 'Imprint Customs',
            'event_date' => '2026-09-12', 'status' => 'new', 'venue' => 'Main Hall',
        ])->assertRedirect('/admin/events');

        $this->assertDatabaseHas(Event::class, ['name' => 'Dealer Conference', 'category' => 'function_hall', 'created_by' => $user->id]);
    }

    public function test_authenticated_admin_can_add_endorser(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post('/admin/endorsers', [
            'name' => 'Juan Rider', 'type' => 'racer', 'status' => 'new',
            'contact_number' => '09171234567',
        ])->assertRedirect('/admin/endorsers');

        $this->assertDatabaseHas(Endorser::class, ['name' => 'Juan Rider', 'created_by' => $user->id]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => 'secret-pass']);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
