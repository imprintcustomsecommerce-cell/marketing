<?php

namespace Tests\Feature;

use App\Actions\GeneratePublicEventLink;
use App\Models\PublicLink;
use App\Models\PublicSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_uses_token_and_only_renders_selected_snapshot_fields(): void
    {
        $link = PublicLink::create([
            'resource_type' => 'event', 'resource_id' => 42,
            'visible_data' => ['event_name' => 'Dealer Day', 'venue' => 'Main Hall'],
        ]);

        $this->get("/client/event/{$link->token}")
            ->assertOk()->assertSee('Dealer Day')->assertSee('Main Hall')
            ->assertDontSee('Internal Budget');
        $this->get('/client/event/42')->assertNotFound();
    }

    public function test_share_action_cannot_expose_internal_fields_even_if_selected(): void
    {
        $link = app(GeneratePublicEventLink::class)->execute(42, [
            'event_name' => 'Dealer Day',
            'internal_budget' => '500000',
            'private_staff_comments' => 'Never public',
        ], ['event_name', 'internal_budget', 'private_staff_comments']);

        $this->assertSame(['event_name' => 'Dealer Day'], $link->visible_data);
    }

    public function test_event_confirmation_is_recorded_and_counted_atomically(): void
    {
        $link = PublicLink::create(['resource_type' => 'event', 'visible_data' => ['event_name' => 'Launch']]);

        $this->post("/client/event/{$link->token}/confirm", ['response' => 'confirmed', 'comment' => 'Looks good'])
            ->assertRedirect();

        $this->assertDatabaseHas('public_submissions', ['public_link_id' => $link->id, 'response' => 'confirmed', 'comment' => 'Looks good']);
        $this->assertSame(1, $link->fresh()->submission_count);
    }

    public function test_expired_inactive_and_exhausted_links_are_gone(): void
    {
        foreach ([
            ['is_active' => false],
            ['expires_at' => now()->subMinute()],
            ['max_submissions' => 1, 'submission_count' => 1],
        ] as $attributes) {
            $link = PublicLink::create(array_merge(['resource_type' => 'event', 'visible_data' => []], $attributes));
            $this->get("/client/event/{$link->token}")->assertGone();
        }
    }

    public function test_password_and_response_permissions_are_enforced(): void
    {
        $link = PublicLink::create([
            'resource_type' => 'event', 'visible_data' => [], 'password' => 'secret-pass',
            'allow_confirmation' => false, 'allow_change_request' => true,
        ]);
        $this->assertTrue(Hash::check('secret-pass', $link->fresh()->password));
        $this->post("/client/event/{$link->token}/confirm", ['response' => 'change_requested'])->assertForbidden();
        $this->post("/client/event/{$link->token}/unlock", ['password' => 'secret-pass'])->assertRedirect();
        $this->post("/client/event/{$link->token}/confirm", ['response' => 'confirmed'])->assertForbidden();
        $this->post("/client/event/{$link->token}/confirm", ['response' => 'change_requested'])->assertRedirect();
    }

    public function test_inquiry_is_new_and_honeypot_rejects_bots(): void
    {
        $payload = [
            'event_name' => 'Community Ride', 'organization' => 'Moto Club', 'contact_person' => 'Ana',
            'contact_number' => '09171234567', 'email' => 'ana@example.test', 'date' => '2026-09-15',
            'venue' => 'Town Plaza', 'estimated_pax' => 120, 'requirements' => 'Tent', 'notes' => 'Morning',
        ];
        $this->post('/client/inquiry', $payload)->assertRedirect();
        $this->assertDatabaseHas('public_submissions', ['type' => 'event_inquiry', 'status' => 'new_inquiry']);

        $this->post('/client/inquiry', $payload + ['website' => 'spam.example'])->assertSessionHasErrors('website');
        $this->assertSame(1, PublicSubmission::count());
    }

    public function test_public_hostname_cannot_open_internal_routes(): void
    {
        config(['imprint.public_host' => 'public.example.test']);
        $this->get('http://public.example.test/')->assertNotFound();
    }
}
