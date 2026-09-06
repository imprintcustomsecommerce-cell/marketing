<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\PublicSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GiveawayAndFormFieldsTest extends TestCase
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

    public function test_a_giveaway_goes_to_an_event_and_owes_no_content(): void
    {
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        $event = Event::create(['name' => 'Makina Expo Cebu', 'category' => 'external_sponsorship', 'event_date' => '2026-09-04', 'status' => 'confirmed']);

        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Makina Expo Cebu raffle',
            'purpose' => 'giveaway',
            'quantity' => 50,
            'event_id' => $event->id,
            // Even if an endorser and a quota are posted, a giveaway takes
            // neither: the server strips them.
            'endorser_id' => $endorser->id,
            'content_quota' => 2,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
        ])->assertRedirect('/admin/pr-kits');

        $kit = PrKit::sole();
        $this->assertTrue($kit->isGiveaway());
        $this->assertSame(50, $kit->quantity);
        $this->assertSame($event->id, $kit->event_id);
        $this->assertNull($kit->endorser_id);
        $this->assertSame(0, $kit->content_quota);
        $this->assertSame(0, Obligation::count());
        $this->assertSame(['done' => 0, 'quota' => 0], $kit->contentProgress());
    }

    public function test_an_endorser_kit_is_never_tied_to_an_event(): void
    {
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        $event = Event::create(['name' => 'Sunday Ride Meet', 'category' => 'tambike', 'event_date' => '2026-09-04', 'status' => 'confirmed']);

        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Team Redline',
            'purpose' => 'endorser',
            'quantity' => 1,
            'endorser_id' => $endorser->id,
            // Posted anyway — the server drops it.
            'event_id' => $event->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ])->assertRedirect('/admin/pr-kits');

        $kit = PrKit::sole();
        $this->assertNull($kit->event_id);
        $this->assertSame($endorser->id, $kit->endorser_id);
        $this->assertSame(2, $kit->content_quota);
        $this->assertSame(2, Obligation::count());
        // The generated content is not pinned to an event either.
        $this->assertNull(Obligation::first()->event_id);
    }

    public function test_an_endorser_kit_requires_an_endorser(): void
    {
        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Somebody',
            'purpose' => 'endorser',
            'quantity' => 1,
            'status' => 'scheduled',
        ])->assertSessionHasErrors('endorser_id');

        $this->assertSame(0, PrKit::count());
    }

    public function test_a_giveaway_requires_an_event(): void
    {
        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Raffle stock',
            'purpose' => 'giveaway',
            'quantity' => 20,
            'status' => 'scheduled',
        ])->assertSessionHasErrors('event_id');

        $this->assertSame(0, PrKit::count());
    }

    public function test_an_endorser_kit_in_the_same_batch_still_owes_content(): void
    {
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);

        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Team Redline',
            'purpose' => 'endorser',
            'quantity' => 1,
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);

        $this->assertSame(2, Obligation::count());
    }

    public function test_giveaways_are_excluded_from_monthly_coverage(): void
    {
        Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        $event = Event::create(['name' => 'Expo', 'category' => 'external_sponsorship', 'event_date' => today(), 'status' => 'confirmed']);

        PrKit::create([
            'recipient' => 'Expo raffle',
            'purpose' => 'giveaway',
            'quantity' => 30,
            'event_id' => $event->id,
            'delivery_date' => today()->startOfMonth()->addDays(2),
            'status' => 'delivered',
        ]);

        // The giveaway must not be mistaken for this endorser's monthly kit.
        $this->actingAs($this->admin())->get('/admin/kit-coverage')
            ->assertOk()
            ->assertSee('No kit');
    }

    public function test_giveaway_shows_on_the_calendar_as_its_own_kind(): void
    {
        PrKit::create([
            'recipient' => 'Makina Expo Cebu',
            'purpose' => 'giveaway',
            'quantity' => 50,
            'delivery_date' => today()->startOfMonth()->addDays(8),
            'status' => 'delivered',
        ]);

        $this->actingAs($this->admin())->get('/admin/calendar')
            ->assertOk()
            ->assertSee('Giveaway · Makina Expo Cebu (×50)')
            ->assertSee('chip-giveaway', false);
    }

    public function test_events_are_categorised_and_filterable_by_type(): void
    {
        $admin = $this->admin();

        foreach (Event::CATEGORIES as $category => $label) {
            $this->actingAs($admin)->post('/admin/events', [
                'name' => "Sample {$category}",
                'category' => $category,
                'event_date' => '2026-10-04',
                'status' => 'confirmed',
            ])->assertRedirect('/admin/events');
        }

        $this->assertSame(3, Event::count());

        // Filtering shows only that type.
        $this->actingAs($admin)->get('/admin/events?category=function_hall')
            ->assertOk()
            ->assertSee('Sample function_hall')
            ->assertDontSee('Sample tambike');

        // No filter shows all three.
        $this->actingAs($admin)->get('/admin/events')
            ->assertOk()
            ->assertSee('Tambike Event')
            ->assertSee('Function Hall Rental')
            ->assertSee('External Event Sponsorship');
    }

    public function test_the_events_list_opens_on_what_is_coming_up(): void
    {
        $admin = $this->admin();

        Event::create(['name' => 'Next Ride-Out', 'category' => 'tambike', 'event_date' => today()->addWeek(), 'status' => 'confirmed']);
        Event::create(['name' => 'Last Month Expo', 'category' => 'external_sponsorship', 'event_date' => today()->subMonth(), 'status' => 'completed']);

        // Upcoming is the default: finished events do not bury the next one.
        $this->actingAs($admin)->get('/admin/events')
            ->assertOk()
            ->assertSee('Next Ride-Out')
            ->assertDontSee('Last Month Expo');

        $this->actingAs($admin)->get('/admin/events?when=past')
            ->assertOk()
            ->assertSee('Last Month Expo')
            ->assertDontSee('Next Ride-Out');

        $this->actingAs($admin)->get('/admin/events?when=all')
            ->assertOk()
            ->assertSee('Next Ride-Out')
            ->assertSee('Last Month Expo');
    }

    public function test_the_list_says_whether_a_shooter_is_booked(): void
    {
        $admin = $this->admin();
        $crew = User::create([
            'name' => 'Multimedia 1', 'email' => 'crew@example.test', 'password' => 'secret1234',
            'role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'is_active' => true,
        ]);

        $covered = Event::create(['name' => 'Covered Ride', 'category' => 'tambike', 'event_date' => today()->addDays(3), 'status' => 'confirmed']);
        Event::create(['name' => 'Uncovered Ride', 'category' => 'tambike', 'event_date' => today()->addDays(4), 'status' => 'confirmed']);
        Coverage::create(['event_id' => $covered->id, 'shooter_id' => $crew->id]);

        $this->actingAs($admin)->get('/admin/events')
            ->assertOk()
            ->assertSee('Multimedia 1')
            ->assertSee('No shooter');
    }

    public function test_an_unknown_event_type_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', [
            'name' => 'Mystery',
            'category' => 'birthday',
            'event_date' => '2026-10-04',
            'status' => 'new',
        ])->assertSessionHasErrors('category');

        $this->assertSame(0, Event::count());
    }

    /**
     * Every field the public forms advertise must survive the round trip into
     * storage — a form that renders an input but drops its answer is worse than
     * not asking.
     */
    #[DataProvider('inquiryPayloads')]
    public function test_every_advertised_field_is_stored(string $path, array $payload): void
    {
        config(['imprint.public_host' => 'public.invalid']);

        $this->post('http://public.invalid'.$path, $payload)->assertSessionHasNoErrors();

        $stored = PublicSubmission::sole()->data;

        foreach ($payload as $field => $value) {
            $this->assertArrayHasKey($field, $stored, "Field [$field] was not stored for [$path].");
            $this->assertSame($value, $stored[$field], "Field [$field] did not round trip for [$path].");
        }
    }

    public static function inquiryPayloads(): array
    {
        return [
            'tambike' => ['/client/tambike', [
                'event_name' => 'Sunday Ride Meet',
                'group_name' => 'Redline Riders',
                'contact_person' => 'Rico Cruz',
                'contact_number' => '0917 123 4567',
                'email' => 'rico@example.test',
                'date' => '2026-10-04',
                'start_time' => '08:00',
                'estimated_pax' => '150',
                'existing_client' => 'yes',
                'relationship_type' => 'partner',
                'relationship_period' => '2024–2025',
                'previous_campaign' => 'Tambike City Tour',
                'previous_contact' => 'Joey Santos',
                'need_booth' => 'yes',
                'booth_requirements' => '3m x 3m with power',
                'need_raffle' => 'no',
                'need_marketing_support' => 'yes',
                'marketing_support_details' => 'Social posts and event coverage',
                'notes' => 'Parking for 60 bikes.',
            ]],
            'function hall' => ['/client/function-hall', [
                'name_or_group' => 'Ortega Family',
                'contact_number' => '0917 222 3333',
                'email' => 'ortega@example.test',
                'event_type' => 'Birthday party',
                'preferred_date' => '2026-10-11',
                'start_time' => '17:00',
                'end_time' => '22:00',
                'estimated_pax' => '80',
                'special_requests' => 'Sound system and projector.',
                'notes' => 'Dance class uses the hall until 4pm.',
            ]],
            'sponsorship' => ['/client/sponsorship', [
                'applicant_type' => 'Racer',
                'racer_team_name' => 'JR Villanueva',
                'address' => '12 Rizal St, Quezon City',
                'contact_number' => '0917 444 5555',
                'email' => 'jr@example.test',
                'birthday' => '1998-03-14',
                'team' => 'Team Redline',
                'motorcycle' => 'Yamaha YZF-R15 155cc',
                'racing_category' => 'Underbone 150',
                'profile' => 'Racing since 2016.',
                'achievements' => '2025 Round 3 podium.',
                'social_media_urls' => 'https://instagram.com/example',
                'requested_sponsorship' => 'Decals, fuel support.',
            ]],
            'external event' => ['/client/external-event', [
                'event_name' => 'Makina Expo Cebu',
                'organizer' => 'Makina Events',
                'contact_person' => 'Ana Reyes',
                'contact_number' => '0917 777 8888',
                'email' => 'ana@example.test',
                'date' => '2026-11-06',
                'end_date' => '2026-11-08',
                'venue' => 'Cebu Trade Hall',
                'location' => 'Cebu City',
                'estimated_pax' => '4000',
                'can_set_up_booth' => 'yes',
                'available_booth_size' => '3m x 3m',
                'raffle_requirements' => 'Two helmets and a jersey.',
                'sponsorship_requirements' => 'Gold package, logo on backdrop.',
                'notes' => 'Load-in the day before.',
            ]],
        ];
    }
}
