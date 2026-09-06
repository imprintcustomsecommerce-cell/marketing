<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\PublicSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tambike_follow_up_fields_are_conditional_and_validated(): void
    {
        $this->get('/client/tambike')
            ->assertOk()
            ->assertSee('Previous relationship')
            ->assertSee('data-show-when="existing_client"', false)
            ->assertSee('data-show-when="need_booth"', false);

        $this->post('/client/tambike', $this->tambikePayload([
            'existing_client' => 'yes',
            'need_booth' => 'yes',
        ]))->assertSessionHasErrors(['relationship_type', 'relationship_period', 'previous_campaign', 'previous_contact', 'booth_requirements']);
    }

    public function test_success_confirmation_contains_a_stable_reference_number(): void
    {
        $response = $this->post('/client/tambike', $this->tambikePayload());

        $submission = PublicSubmission::sole();
        $response->assertRedirect()->assertSessionHas('reference', $submission->referenceNumber());
        $this->assertMatchesRegularExpression('/^IMP-\d{8}-\d{6}$/', $submission->referenceNumber());
    }

    public function test_admin_is_warned_about_matching_endorsers_and_previous_inquiries(): void
    {
        $admin = User::factory()->create();
        Endorser::create(['name' => 'Existing Rider', 'email' => 'same@example.test', 'contact_number' => '0917 123 4567', 'type' => 'racer', 'status' => 'active']);
        PublicSubmission::create(['type' => 'event_inquiry', 'status' => 'closed', 'data' => ['email' => 'same@example.test'], 'submitted_at' => now()->subDay()]);
        $current = PublicSubmission::create(['type' => 'sponsorship_inquiry', 'status' => 'new_inquiry', 'data' => ['racer_team_name' => 'New Rider', 'email' => 'SAME@example.test', 'contact_number' => '09171234567'], 'submitted_at' => now()]);

        $this->actingAs($admin)->get(route('admin.inquiries.show', $current))
            ->assertOk()
            ->assertSee('Possible duplicate')
            ->assertSee('Existing Rider')
            ->assertSee('Previous inquiry');
    }

    public function test_admin_quick_actions_and_partner_conversion_work(): void
    {
        $admin = User::factory()->create();
        $inquiry = PublicSubmission::create([
            'type' => 'sponsorship_inquiry',
            'status' => 'new_inquiry',
            'data' => ['applicant_type' => 'Team', 'racer_team_name' => 'Partner Club'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.inquiries.action', $inquiry), ['action' => 'request_information'])->assertRedirect();
        $this->assertSame('reviewing', $inquiry->fresh()->status);

        $this->actingAs($admin)->post(route('admin.inquiries.convert', $inquiry), ['conversion' => 'partner'])->assertRedirect();
        $this->assertSame('organization', Endorser::sole()->type);
        $this->assertSame('confirmed', $inquiry->fresh()->status);
    }

    public function test_dashboard_reports_the_configured_cloudflare_link(): void
    {
        config(['imprint.public_url' => 'https://sample-link.trycloudflare.com']);
        Endorser::create(['name' => 'Dashboard Rider', 'type' => 'racer', 'status' => 'active']);

        // The live status pill has been removed; what matters is that the
        // dashboard still hands staff the shareable client links.
        $this->actingAs(User::factory()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Share with clients')
            ->assertSee('https://sample-link.trycloudflare.com/client/tambike');
    }

    private function tambikePayload(array $overrides = []): array
    {
        return array_merge([
            'event_name' => 'Sunday Ride',
            'group_name' => 'Rider Club',
            'contact_person' => 'Ana Cruz',
            'contact_number' => '09171234567',
            'email' => 'ana@example.test',
            'date' => '2026-10-01',
            'start_time' => '08:00',
            'existing_client' => 'no',
            'need_booth' => 'no',
            'need_raffle' => 'no',
            'need_marketing_support' => 'no',
        ], $overrides);
    }
}
