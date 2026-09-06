<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrKitListTest extends TestCase
{
    use RefreshDatabase;

    private function joey(): User
    {
        return User::create([
            'name' => 'Joey',
            'email' => 'joey@example.test',
            'password' => 'secret1234',
            'role' => 'admin',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);
    }

    private function endorser(): Endorser
    {
        return Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
    }

    public function test_a_kit_on_its_way_out_shows_the_delivery_as_the_next_step(): void
    {
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $this->endorser()->id,
            'delivery_date' => today()->addDays(3),
            'status' => 'packed',
        ]);

        $step = $kit->nextStep();
        $this->assertSame('Deliver', $step['label']);
        $this->assertFalse($step['late']);

        $this->actingAs($this->joey())->get('/admin/pr-kits')->assertOk()->assertSee('Deliver');
    }

    public function test_a_delivered_kit_shows_the_pickup_next(): void
    {
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $this->endorser()->id,
            'delivery_date' => today()->subDays(5),
            'pickup_date' => today()->addDays(2),
            'status' => 'awaiting_pickup',
        ]);

        $this->assertSame('Pick up', $kit->nextStep()['label']);
    }

    public function test_a_pickup_that_has_slipped_is_flagged_late(): void
    {
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $this->endorser()->id,
            'delivery_date' => today()->subDays(20),
            'pickup_date' => today()->subDays(4),
            'status' => 'awaiting_pickup',
        ]);

        $this->assertTrue($kit->nextStep()['late']);

        $this->actingAs($this->joey())->get('/admin/pr-kits')->assertOk()->assertSee('Late');
    }

    public function test_a_returned_kit_is_settled_and_needs_nothing(): void
    {
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $this->endorser()->id,
            'delivery_date' => today()->subMonth(),
            'pickup_date' => today()->subDays(10),
            'status' => 'returned',
        ]);

        $this->assertSame('Back on the shelf', $kit->nextStep()['label']);
        $this->assertFalse($kit->nextStep()['late']);
    }

    public function test_closed_kits_are_out_of_the_way_until_asked_for(): void
    {
        $joey = $this->joey();
        $endorser = $this->endorser();

        PrKit::create([
            'recipient' => 'Live kit', 'purpose' => PrKit::PURPOSE_ENDORSER, 'endorser_id' => $endorser->id,
            'delivery_date' => today()->addDay(), 'status' => 'scheduled',
        ]);
        PrKit::create([
            'recipient' => 'Finished kit', 'purpose' => PrKit::PURPOSE_ENDORSER, 'endorser_id' => $endorser->id,
            'delivery_date' => today()->subMonth(), 'status' => 'returned',
        ]);

        // In flight is the default view.
        $this->actingAs($joey)->get('/admin/pr-kits')
            ->assertOk()->assertSee('Live kit')->assertDontSee('Finished kit');

        $this->actingAs($joey)->get('/admin/pr-kits?state=settled')
            ->assertOk()->assertSee('Finished kit')->assertDontSee('Live kit');

        $this->actingAs($joey)->get('/admin/pr-kits?state=all')
            ->assertOk()->assertSee('Live kit')->assertSee('Finished kit');
    }

    public function test_the_two_kinds_can_be_told_apart_and_filtered(): void
    {
        $joey = $this->joey();
        $endorser = $this->endorser();
        $event = Event::create(['name' => 'Expo', 'category' => 'external_sponsorship', 'event_date' => today()->addWeek(), 'status' => 'new']);

        PrKit::create([
            'recipient' => 'Team Redline', 'purpose' => PrKit::PURPOSE_ENDORSER, 'endorser_id' => $endorser->id,
            'delivery_date' => today()->addDay(), 'status' => 'scheduled',
        ]);
        PrKit::create([
            'recipient' => 'Expo raffle', 'purpose' => PrKit::PURPOSE_GIVEAWAY, 'event_id' => $event->id,
            'quantity' => 40, 'delivery_date' => today()->addDays(2), 'status' => 'packed',
        ]);

        $this->actingAs($joey)->get('/admin/pr-kits?purpose=giveaway')
            ->assertOk()->assertSee('Expo raffle')->assertDontSee('Team Redline');

        $this->actingAs($joey)->get('/admin/pr-kits?purpose=endorser')
            ->assertOk()->assertSee('Team Redline')->assertDontSee('Expo raffle');
    }

    public function test_kits_can_be_found_by_tracking_number(): void
    {
        PrKit::create([
            'recipient' => 'Team Redline', 'purpose' => PrKit::PURPOSE_ENDORSER, 'endorser_id' => $this->endorser()->id,
            'delivery_date' => today()->addDay(), 'status' => 'in_transit',
            'courier' => 'Lalamove', 'tracking_number' => 'LM-99213',
        ]);

        $this->actingAs($this->joey())->get('/admin/pr-kits?q=LM-99213')
            ->assertOk()
            ->assertSee('Team Redline');
    }
}
