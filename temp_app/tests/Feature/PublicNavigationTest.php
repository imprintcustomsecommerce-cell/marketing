<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Every public form has to be reachable from every other one. */
    public function test_the_navigation_links_to_every_inquiry_form(): void
    {
        $page = $this->get(route('client.sponsorship'))->assertOk();

        foreach (['client.function-hall', 'client.tambike', 'client.sponsorship',
                  'client.external-event', 'client.inquiry', 'client.track'] as $route) {
            $page->assertSee(route($route, [], false), false);
        }
    }

    public function test_the_racer_team_sponsorship_form_is_named_with_a_slash(): void
    {
        $this->get(route('client.sponsorship'))
            ->assertOk()
            ->assertSee('Racer/Team Sponsorship')
            ->assertDontSee('Racer and Team Sponsorship');
    }

    public function test_the_external_event_form_still_opens(): void
    {
        $this->get(route('client.external-event'))
            ->assertOk()
            ->assertSee('External Event Sponsorship');
    }
}
