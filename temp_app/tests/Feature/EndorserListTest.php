<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndorserListTest extends TestCase
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

    public function test_the_roster_shows_whether_this_months_kit_went_out(): void
    {
        $joey = $this->joey();

        $sent = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        Endorser::create(['name' => 'JR Villanueva', 'type' => 'racer', 'status' => 'active']);

        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $sent->id,
            'delivery_date' => today()->startOfMonth()->addDays(2),
            'status' => 'delivered',
            'content_quota' => 2,
        ]);
        $kit->syncContentObligations();
        Obligation::where('pr_kit_id', $kit->id)->where('sequence', 1)->update(['status' => 'completed']);

        $this->actingAs($joey)->get('/admin/endorsers')
            ->assertOk()
            ->assertSee('Sent '.$kit->delivery_date->format('M j'))
            // The one with no kit this month is called out rather than left blank.
            ->assertSee('Not sent');
    }

    public function test_a_kit_from_last_month_does_not_count_for_this_one(): void
    {
        $joey = $this->joey();
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);

        PrKit::create([
            'recipient' => 'Team Redline',
            'purpose' => PrKit::PURPOSE_ENDORSER,
            'endorser_id' => $endorser->id,
            'delivery_date' => today()->startOfMonth()->subDays(3),
            'status' => 'delivered',
        ]);

        $this->actingAs($joey)->get('/admin/endorsers')->assertOk()->assertSee('Not sent');
    }

    public function test_an_inactive_endorser_is_not_chased_for_a_kit(): void
    {
        $joey = $this->joey();
        Endorser::create(['name' => 'Retired Rider', 'type' => 'racer', 'status' => 'inactive']);

        $this->actingAs($joey)->get('/admin/endorsers')
            ->assertOk()
            ->assertSee('Retired Rider')
            ->assertDontSee('Not sent');
    }

    public function test_the_roster_can_be_filtered_and_searched(): void
    {
        $joey = $this->joey();

        Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active', 'team_or_group' => 'Redline Racing']);
        Endorser::create(['name' => 'Kaye Santos', 'type' => 'influencer', 'status' => 'inactive']);

        $this->actingAs($joey)->get('/admin/endorsers?status=active')
            ->assertOk()->assertSee('Team Redline')->assertDontSee('Kaye Santos');

        $this->actingAs($joey)->get('/admin/endorsers?type=influencer')
            ->assertOk()->assertSee('Kaye Santos')->assertDontSee('Team Redline');

        // Search reaches the team name, not just the endorser's own name.
        $this->actingAs($joey)->get('/admin/endorsers?q=Redline+Racing')
            ->assertOk()->assertSee('Team Redline')->assertDontSee('Kaye Santos');
    }

    public function test_working_names_are_listed_before_retired_ones(): void
    {
        $joey = $this->joey();

        Endorser::create(['name' => 'Aaron Inactive', 'type' => 'racer', 'status' => 'inactive']);
        Endorser::create(['name' => 'Zoe Active', 'type' => 'racer', 'status' => 'active']);

        $this->actingAs($joey)->get('/admin/endorsers')
            ->assertOk()
            // Alphabetically Aaron comes first, but active work comes first here.
            ->assertSeeInOrder(['Zoe Active', 'Aaron Inactive']);
    }
}
