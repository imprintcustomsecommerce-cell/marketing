<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The button on the events screen, for when waiting five minutes for the
 * scheduled run is five minutes too long.
 */
class WebsiteCalendarButtonTest extends TestCase
{
    use RefreshDatabase;

    private function connectShopify(): void
    {
        config(['services.shopify' => [
            'domain' => 'test-shop.myshopify.com',
            'client_id' => 'id', 'client_secret' => 'secret',
            'api_version' => '2026-07', 'event_metaobject_type' => 'event',
        ]]);
        Cache::flush();

        Http::fake([
            '*/admin/oauth/access_token' => Http::response(['access_token' => 'tok', 'expires_in' => 86399]),
            '*/graphql.json' => Http::response(['data' => [
                'metaobjects' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]],
                'metaobjectCreate' => ['userErrors' => []],
                'metaobjectUpdate' => ['userErrors' => []],
                'metaobjectDelete' => ['deletedId' => 'gid://shopify/Metaobject/1', 'userErrors' => []],
            ]]),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Sunday Ride-Out', 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed',
            'venue' => 'Imprint Customs', 'created_by' => $this->admin()->id,
        ], $overrides));
    }

    public function test_the_button_is_on_the_events_screen(): void
    {
        $this->actingAs($this->admin())->get('/admin/events')
            ->assertOk()
            ->assertSee('Update website calendar');
    }

    public function test_pressing_it_sends_the_live_events(): void
    {
        $this->connectShopify();
        $this->event(['name' => 'Sunday Ride-Out']);

        $this->actingAs($this->admin())->post('/admin/events/sync-website')
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Sunday Ride-Out'));
    }

    public function test_an_archived_event_is_not_sent(): void
    {
        $this->connectShopify();
        $this->event(['name' => 'Shelved', 'archived_at' => now()]);

        $this->actingAs($this->admin())->post('/admin/events/sync-website')->assertRedirect();

        Http::assertNotSent(fn ($request) => str_contains($request->body(), 'Shelved'));
    }

    public function test_a_cancelled_event_is_not_sent(): void
    {
        $this->connectShopify();
        $this->event(['name' => 'Called Off', 'status' => 'cancelled']);

        $this->actingAs($this->admin())->post('/admin/events/sync-website')->assertRedirect();

        Http::assertNotSent(fn ($request) => str_contains($request->body(), 'Called Off'));
    }

    public function test_it_says_so_when_the_website_is_not_connected(): void
    {
        // The suite runs with no shop configured, which is the same state as a
        // shop that has not been set up yet.
        $this->actingAs($this->admin())->post('/admin/events/sync-website')
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_it_needs_a_sign_in(): void
    {
        $this->post('/admin/events/sync-website')->assertRedirect('/login');
    }
}
