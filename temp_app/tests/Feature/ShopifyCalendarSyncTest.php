<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shopify' => [
            'domain' => 'test-shop.myshopify.com',
            'client_id' => 'id', 'client_secret' => 'secret',
            'api_version' => '2026-07', 'event_metaobject_type' => 'event',
        ]]);
        Cache::flush();
    }

    private function event(array $overrides = []): Event
    {
        $admin = User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING]);

        return Event::create(array_merge([
            'name' => 'Sunday Ride-Out', 'category' => 'tambike', 'event_type' => 'tambike',
            'event_category' => 'motorcycle', 'event_date' => today()->addWeek(), 'status' => 'confirmed',
            'venue' => 'Imprint Customs', 'is_public' => true, 'created_by' => $admin->id,
        ], $overrides));
    }

    /** @param array<int,array<string,mixed>> $existing */
    private function fakeShopify(array $existing = []): void
    {
        Http::fake([
            '*/admin/oauth/access_token' => Http::response(['access_token' => 'tok', 'expires_in' => 86399]),
            '*/graphql.json' => Http::response(['data' => [
                'metaobjects' => ['nodes' => $existing, 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]],
                'metaobjectCreate' => ['userErrors' => []],
                'metaobjectUpdate' => ['userErrors' => []],
                'metaobjectDelete' => ['deletedId' => 'gid://shopify/Metaobject/1', 'userErrors' => []],
            ]]),
        ]);
    }

    private function sentGraphql(string $needle): bool
    {
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'graphql') && str_contains($request->body(), $needle)) {
                return true;
            }
        }

        return false;
    }

    public function test_a_published_event_is_sent_to_the_website(): void
    {
        $this->fakeShopify();
        $this->event();

        $this->artisan('imprint:shopify-calendar')->assertSuccessful();

        $this->assertTrue($this->sentGraphql('metaobjectCreate'));
        $this->assertTrue($this->sentGraphql('imprint-event-'));
    }

    public function test_a_dry_run_sends_nothing(): void
    {
        $this->fakeShopify();
        $this->event();

        $this->artisan('imprint:shopify-calendar --dry-run')->assertSuccessful();

        $this->assertFalse($this->sentGraphql('metaobjectCreate'));
    }

    public function test_an_unchanged_event_is_not_rewritten(): void
    {
        $event = $this->event();
        $entry = $event->toPublicCalendarEntry();
        $this->fakeShopify([[
            'id' => 'gid://shopify/Metaobject/1',
            'handle' => 'imprint-event-'.$event->id,
            'fields' => collect(['title', 'date', 'end_date', 'start_time', 'end_time', 'venue', 'type_label', 'summary'])
                ->map(fn ($key) => ['key' => $key, 'value' => (string) ($entry[$key] ?? '')])->all(),
        ]]);

        $this->artisan('imprint:shopify-calendar')->assertSuccessful();

        $this->assertFalse($this->sentGraphql('metaobjectUpdate'), 'nothing changed, so nothing should be written');
    }

    public function test_an_event_taken_off_the_website_is_deleted_there(): void
    {
        $event = $this->event(['is_public' => false]);
        $this->fakeShopify([[
            'id' => 'gid://shopify/Metaobject/1',
            'handle' => 'imprint-event-'.$event->id,
            'fields' => [['key' => 'title', 'value' => 'Sunday Ride-Out']],
        ]]);

        $this->artisan('imprint:shopify-calendar')->assertSuccessful();

        $this->assertTrue($this->sentGraphql('metaobjectDelete'));
    }

    public function test_entries_put_there_by_hand_are_left_alone(): void
    {
        $this->fakeShopify([[
            'id' => 'gid://shopify/Metaobject/99',
            'handle' => 'a-hand-made-entry',
            'fields' => [['key' => 'title', 'value' => 'Written by somebody in the admin']],
        ]]);

        $this->artisan('imprint:shopify-calendar')->assertSuccessful();

        $this->assertFalse($this->sentGraphql('metaobjectDelete'), 'the sync only removes what it created');
    }

    public function test_it_stops_cleanly_when_shopify_is_not_configured(): void
    {
        config(['services.shopify.client_id' => null]);

        $this->artisan('imprint:shopify-calendar')->assertFailed();
    }

    public function test_the_token_is_fetched_once_and_reused(): void
    {
        $this->fakeShopify();
        $this->event();

        $this->artisan('imprint:shopify-calendar')->assertSuccessful();
        $this->artisan('imprint:shopify-calendar')->assertSuccessful();

        $tokenCalls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'access_token'))->count();
        $this->assertSame(1, $tokenCalls, 'a cached token should not be re-fetched every run');
    }
}
