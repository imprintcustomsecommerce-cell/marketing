<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Event;
use App\Models\PublicSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryConversionTest extends TestCase
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

    public function test_a_tambike_inquiry_becomes_a_tambike_event(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'tambike_inquiry',
            'status' => 'reviewing',
            'data' => [
                'event_name' => 'Sunday Ride Meet',
                'group_name' => 'Redline Riders',
                'contact_person' => 'Rico Cruz',
                'date' => '2026-10-04',
                'start_time' => '08:00',
                'estimated_pax' => '150',
                'notes' => 'Parking for 60 bikes.',
            ],
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert")
            ->assertRedirect();

        $event = Event::sole();
        $this->assertSame('Sunday Ride Meet', $event->name);
        $this->assertSame('tambike', $event->category);
        $this->assertSame('Redline Riders', $event->organization);
        $this->assertSame('2026-10-04', $event->event_date->toDateString());
        $this->assertSame('08:00', $event->start_time);
        $this->assertSame(150, $event->estimated_pax);
        $this->assertSame('new', $event->status);
        $this->assertSame($joey->id, $event->created_by);

        // The sender's own words travel with it, along with where it came from.
        $this->assertStringContainsString('Parking for 60 bikes.', $event->notes);
        $this->assertStringContainsString("inquiry #{$inquiry->id}", $event->notes);

        // The inquiry now points at what it became.
        $this->assertSame($event->id, $inquiry->fresh()->converted_event_id);
    }

    public function test_each_form_maps_to_its_own_event_type(): void
    {
        $joey = $this->joey();

        $expected = [
            'function-hall_inquiry' => 'function_hall',
            'external-event_inquiry' => 'external_sponsorship',
        ];

        foreach ($expected as $type => $category) {
            $inquiry = PublicSubmission::create([
                'type' => $type,
                'status' => 'new_inquiry',
                'data' => ['event_name' => "From {$type}", 'name_or_group' => "From {$type}", 'date' => '2026-10-04'],
                'submitted_at' => now(),
            ]);

            $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert");

            $this->assertSame($category, Event::find($inquiry->fresh()->converted_event_id)->category);
        }
    }

    public function test_a_sponsorship_application_becomes_an_endorser_not_an_event(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'sponsorship_inquiry',
            'status' => 'reviewing',
            'data' => [
                'applicant_type' => 'Individual racer',
                'racer_team_name' => 'JR Villanueva',
                'contact_number' => '0917 444 5555',
                'email' => 'jr@example.test',
                'team' => 'Team Redline',
                'profile' => 'Racing since 2016.',
                'achievements' => '2025 Round 3 podium.',
                'social_media_urls' => "not-a-link\nhttps://instagram.com/jr",
                'requested_sponsorship' => 'Decals and fuel support.',
            ],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert")->assertRedirect();

        $this->assertSame(0, Event::count());

        $endorser = Endorser::sole();
        $this->assertSame('JR Villanueva', $endorser->name);
        $this->assertSame('racer', $endorser->type);
        $this->assertSame('Team Redline', $endorser->team_or_group);
        // The junk line is skipped and the real link kept.
        $this->assertSame('https://instagram.com/jr', $endorser->social_media_url);
        $this->assertStringContainsString('Racing since 2016.', $endorser->profile);
        $this->assertStringContainsString('2025 Round 3 podium.', $endorser->profile);
        $this->assertStringContainsString('Decals and fuel support.', $endorser->notes);

        $this->assertSame($endorser->id, $inquiry->fresh()->converted_endorser_id);
    }

    public function test_a_team_application_is_recorded_as_a_team(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'sponsorship_inquiry',
            'status' => 'new_inquiry',
            'data' => ['applicant_type' => 'Racing team', 'racer_team_name' => 'Team Redline'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert");

        $this->assertSame('team', Endorser::sole()->type);
    }

    public function test_converting_twice_does_not_create_a_second_record(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'tambike_inquiry',
            'status' => 'new_inquiry',
            'data' => ['event_name' => 'Sunday Ride Meet', 'date' => '2026-10-04'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert");
        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert");

        $this->assertSame(1, Event::count());
    }

    public function test_an_inquiry_without_a_date_still_converts(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'event_inquiry',
            'status' => 'new_inquiry',
            'data' => ['event_name' => 'Something later', 'estimated_pax' => 'lots'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert")->assertRedirect();

        $event = Event::sole();
        // Falls back to today rather than refusing, and a non-numeric headcount
        // is dropped rather than stored as nonsense.
        $this->assertSame(today()->toDateString(), $event->event_date->toDateString());
        $this->assertNull($event->estimated_pax);
    }

    public function test_the_converted_record_is_linked_from_the_inquiry_screen(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'tambike_inquiry',
            'status' => 'new_inquiry',
            'data' => ['event_name' => 'Sunday Ride Meet', 'date' => '2026-10-04'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->get("/admin/inquiries/{$inquiry->id}")
            ->assertOk()
            ->assertSee('Accept as event');

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert");

        $this->actingAs($joey)->get("/admin/inquiries/{$inquiry->id}")
            ->assertOk()
            ->assertSee('Converted')
            ->assertSee('Now an event')
            ->assertDontSee('Accept as event');
    }
}
