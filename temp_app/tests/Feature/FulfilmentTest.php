<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret1234',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_a_pr_kit_with_delivery_and_pickup(): void
    {
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);

        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-10',
            'pickup_date' => '2026-09-20',
            'courier' => 'Lalamove',
            'status' => 'scheduled',
        ])->assertRedirect('/admin/pr-kits');

        $kit = PrKit::sole();
        $this->assertSame('Team Redline', $kit->recipient);
        $this->assertSame('2026-09-20', $kit->pickup_date->toDateString());
    }

    public function test_pickup_cannot_be_scheduled_before_delivery(): void
    {
        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Rider',
            'delivery_date' => '2026-09-10',
            'pickup_date' => '2026-09-01',
            'status' => 'scheduled',
        ])->assertSessionHasErrors('pickup_date');

        $this->assertSame(0, PrKit::count());
    }

    public function test_obligation_past_its_due_date_reads_as_overdue(): void
    {
        $endorser = Endorser::create(['name' => 'Rider One', 'type' => 'racer', 'status' => 'active']);

        $overdue = Obligation::create([
            'endorser_id' => $endorser->id,
            'title' => 'Race day reel',
            'type' => 'social_post',
            'due_date' => today()->subWeek(),
            'status' => 'pending',
        ]);

        $done = Obligation::create([
            'endorser_id' => $endorser->id,
            'title' => 'Podium photo',
            'type' => 'social_post',
            'due_date' => today()->subWeek(),
            'status' => 'completed',
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($done->isOverdue());

        $this->actingAs($this->admin())->get('/admin/obligations?show=overdue')
            ->assertOk()
            ->assertSee('Race day reel')
            ->assertDontSee('Podium photo');
    }

    public function test_calendar_shows_events_kits_and_obligations_for_the_month(): void
    {
        $endorser = Endorser::create(['name' => 'Rider One', 'type' => 'racer', 'status' => 'active']);
        $day = today()->startOfMonth()->addDays(9);

        Event::create(['name' => 'Ride-Out Manila', 'event_date' => $day, 'status' => 'confirmed']);
        PrKit::create(['recipient' => 'Rider One', 'endorser_id' => $endorser->id, 'delivery_date' => $day, 'status' => 'scheduled']);
        PrKit::create(['recipient' => 'Rider One', 'endorser_id' => $endorser->id, 'pickup_date' => $day, 'status' => 'awaiting_pickup']);
        Obligation::create(['endorser_id' => $endorser->id, 'title' => 'Post the reel', 'type' => 'social_post', 'due_date' => $day, 'status' => 'pending']);

        $this->actingAs($this->admin())->get('/admin/calendar')
            ->assertOk()
            ->assertSee('Ride-Out Manila')
            ->assertSee('Deliver · Rider One')
            ->assertSee('Pick up · Rider One')
            ->assertSee('Post the reel');
    }

    public function test_endorser_group_chat_link_is_saved_and_listed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/endorsers', [
            'name' => 'Team Redline',
            'type' => 'team',
            'status' => 'active',
            'group_chat_url' => 'https://m.me/j/AbCdEf123/',
        ])->assertRedirect('/admin/endorsers');

        $this->assertSame('https://m.me/j/AbCdEf123/', Endorser::sole()->group_chat_url);

        $this->actingAs($admin)->get('/admin/endorsers')
            ->assertOk()
            ->assertSee('Open chat')
            ->assertSee('https://m.me/j/AbCdEf123/', false);
    }

    public function test_event_group_chat_link_is_saved_and_listed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', [
            'name' => 'Sunday Ride Meet',
            'category' => 'tambike',
            'event_type' => 'tambike',
            'event_category' => 'motorcycle',
            'event_date' => '2026-10-04',
            'status' => 'confirmed',
            'group_chat_url' => 'https://m.me/j/RideMeet99/',
        ])->assertRedirect('/admin/events');

        $this->assertSame('https://m.me/j/RideMeet99/', Event::sole()->group_chat_url);

        $this->actingAs($admin)->get('/admin/events')
            ->assertOk()
            ->assertSee('Open chat')
            ->assertSee('https://m.me/j/RideMeet99/', false);
    }

    public function test_an_event_group_chat_link_must_be_a_url(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', [
            'name' => 'Sunday Ride Meet',
            'category' => 'tambike',
            'event_type' => 'tambike',
            'event_category' => 'motorcycle',
            'event_date' => '2026-10-04',
            'status' => 'confirmed',
            'group_chat_url' => 'the viber group',
        ])->assertSessionHasErrors('group_chat_url');

        $this->assertSame(0, Event::count());
    }

    public function test_a_group_chat_link_must_be_a_url(): void
    {
        $this->actingAs($this->admin())->post('/admin/endorsers', [
            'name' => 'Team Redline',
            'type' => 'team',
            'status' => 'active',
            'group_chat_url' => 'messenger group (ask Rico)',
        ])->assertSessionHasErrors('group_chat_url');

        $this->assertSame(0, Endorser::count());
    }

    public function test_calendar_falls_back_to_this_month_when_given_rubbish(): void
    {
        $this->actingAs($this->admin())->get('/admin/calendar?month=not-a-month')
            ->assertOk()
            ->assertSee(today()->format('F Y'));
    }
}
