<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndorserDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function roster(User $by): Endorser
    {
        $endorser = Endorser::create(['name' => 'Duplicate Entry', 'type' => 'racer', 'status' => 'active', 'created_by' => $by->id]);
        $kit = PrKit::create(['endorser_id' => $endorser->id, 'recipient' => 'Duplicate Entry', 'status' => 'preparing',
            'content_quota' => 2, 'created_by' => $by->id]);
        Obligation::create(['endorser_id' => $endorser->id, 'pr_kit_id' => $kit->id, 'title' => 'Reel post',
            'due_date' => today()->addWeek(), 'status' => 'pending', 'created_by' => $by->id]);

        return $endorser;
    }

    public function test_an_administrator_can_delete_an_endorser_and_everything_filed_under_them(): void
    {
        $admin = $this->admin();
        $endorser = $this->roster($admin);

        $this->actingAs($admin)->delete("/admin/endorsers/{$endorser->id}")
            ->assertRedirect('/admin/endorsers');

        $this->assertDatabaseMissing('endorsers', ['id' => $endorser->id]);
        $this->assertSame(0, PrKit::where('endorser_id', $endorser->id)->count(), 'a kit addressed to nobody is worse than none');
        $this->assertSame(0, Obligation::where('endorser_id', $endorser->id)->count());
    }

    public function test_marketing_staff_cannot_delete_an_endorser(): void
    {
        $endorser = $this->roster($this->admin());
        $staff = User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);

        $this->actingAs($staff)->delete("/admin/endorsers/{$endorser->id}")->assertForbidden();

        $this->assertDatabaseHas('endorsers', ['id' => $endorser->id]);
    }
}
