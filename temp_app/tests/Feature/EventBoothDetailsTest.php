<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBoothDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bike Expo', 'category' => 'tambike', 'event_type' => 'outside_event',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek()->toDateString(), 'status' => 'confirmed',
        ], $overrides);
    }

    public function test_the_booth_details_are_saved(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', $this->fields([
            'organization' => 'Cavite Riders',
            'estimated_pax' => 800,
            'ingress_date' => today()->addDays(6)->toDateString(),
            'egress_date' => today()->addDays(9)->toDateString(),
            'duration_days' => 3,
            'venue' => 'Expo Centre',
            'booth_size' => '3m x 3m',
            'venue_type' => 'outdoor',
            'contact_person' => 'Ana',
            'contact_number' => '09171234567',
            'deal_type' => 'cash',
            'cash_amount' => '15000.50',
            'preparation' => ['tent', 'stock'],
        ]))->assertRedirect();

        $event = Event::sole();
        $this->assertSame('3m x 3m', $event->booth_size);
        $this->assertSame('outdoor', $event->venue_type);
        $this->assertSame(3, $event->duration_days);
        $this->assertSame('15000.50', $event->cash_amount);
        $this->assertSame(today()->addDays(6)->toDateString(), $event->ingress_date->toDateString());
        $this->assertSame(['tent', 'stock'], $event->preparation);
        // Picking an item on the form says it is needed, not that it is sorted.
        $this->assertSame(0, $event->preparationDone());
        $this->assertSame(2, $event->preparationTotal());
        $this->assertFalse($event->isPreparationComplete());
    }

    public function test_an_ex_deal_carries_no_cash_amount(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', $this->fields(['deal_type' => 'cash', 'cash_amount' => '5000']))
            ->assertRedirect();
        $event = Event::sole();

        $this->actingAs($admin)->put("/admin/events/{$event->id}", $this->fields(['deal_type' => 'exdeal', 'cash_amount' => '5000']))
            ->assertRedirect();

        $this->assertNull($event->fresh()->cash_amount, 'a leftover figure must not stay on an ex-deal');
    }

    public function test_a_cash_deal_must_say_how_much(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', $this->fields(['deal_type' => 'cash']))
            ->assertSessionHasErrors('cash_amount');
    }

    public function test_load_out_cannot_come_before_load_in(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', $this->fields([
            'ingress_date' => today()->addDays(9)->toDateString(),
            'egress_date' => today()->addDays(6)->toDateString(),
        ]))->assertSessionHasErrors('egress_date');
    }

    public function test_unticking_everything_clears_the_checklist(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/events', $this->fields(['preparation' => ['tent', 'stock']]));
        $event = Event::sole();

        // An all-clear form posts no preparation key at all.
        $this->actingAs($admin)->put("/admin/events/{$event->id}", $this->fields())->assertRedirect();

        $this->assertSame([], $event->fresh()->preparation);
    }

    public function test_items_typed_in_for_one_event_are_saved_and_counted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', $this->fields([
            'preparation' => ['tent'],
            'custom_preparation' => [
                ['label' => 'Borrowed generator', 'done' => '1'],
                ['label' => 'Ice and water for the crew', 'done' => '0'],
            ],
        ]))->assertRedirect();

        $event = Event::sole();
        $this->assertCount(2, $event->customPreparation());
        $this->assertSame('Borrowed generator', $event->customPreparation()[0]['label']);
        $this->assertTrue($event->customPreparation()[0]['done']);
        $this->assertFalse($event->customPreparation()[1]['done']);

        // One standing item asked for, plus the two typed in.
        $this->assertSame(1, $event->preparationDone(), 'only the custom item marked done counts');
        $this->assertSame(3, $event->preparationTotal());
    }

    public function test_a_blank_row_is_not_stored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', $this->fields([
            'custom_preparation' => [
                ['label' => '  ', 'done' => '0'],
                ['label' => 'Extra tarpaulin', 'done' => '0'],
                ['label' => '', 'done' => '1'],
            ],
        ]))->assertRedirect();

        $custom = Event::sole()->customPreparation();
        $this->assertCount(1, $custom, 'clicking Add and changing your mind should leave nothing behind');
        $this->assertSame('Extra tarpaulin', $custom[0]['label']);
    }

    public function test_everything_ticked_including_custom_items_reads_as_complete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/events', $this->fields([
            'preparation' => ['tent', 'stock'],
            'custom_preparation' => [['label' => 'Borrowed generator', 'done' => '0']],
        ]))->assertRedirect();
        $event = Event::sole();

        $this->assertFalse($event->isPreparationComplete(), 'nothing has been sorted yet');

        $this->actingAs($admin)->patch("/admin/events/{$event->id}/preparation", [
            'preparation_done' => ['tent', 'stock'],
            'custom_done' => [0],
        ])->assertRedirect();

        $this->assertTrue($event->fresh()->isPreparationComplete());
    }

    public function test_the_form_offers_a_way_to_add_an_item(): void
    {
        $this->actingAs($this->admin())->get('/admin/events/create')
            ->assertOk()
            ->assertSee('id="add-prep"', false)
            ->assertSee('+ Add item');
    }

    public function test_the_event_page_shows_the_booth_details_and_checklist(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/events', $this->fields([
            'booth_size' => '3m x 3m', 'venue_type' => 'indoor',
            'deal_type' => 'cash', 'cash_amount' => '15000',
            'duration_days' => 2, 'preparation' => ['tent'],
        ]));

        $this->actingAs($admin)->get('/admin/events/'.Event::sole()->id)
            ->assertOk()
            ->assertSee('3m x 3m')
            ->assertSee('Indoor')
            ->assertSee('Full cash')
            ->assertSee('2 days')
            ->assertSee('0 of 1 ready')
            ->assertSee('Tent, tables, and chairs');
    }
}
