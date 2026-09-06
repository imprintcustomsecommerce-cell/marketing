<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The house rule: one PR kit per endorser per month, and every kit owes two
 * pieces of video content due by the end of the month it was delivered in.
 */
class KitContentRuleTest extends TestCase
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

    private function endorser(): Endorser
    {
        return Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
    }

    public function test_a_received_kit_creates_two_content_obligations_due_end_of_month(): void
    {
        $endorser = $this->endorser();

        $this->actingAs($this->admin())->post('/admin/pr-kits', [
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ])->assertRedirect('/admin/pr-kits');

        $obligations = Obligation::orderBy('sequence')->get();

        $this->assertCount(2, $obligations);
        $this->assertSame('Content 1 of 2 · September 2026', $obligations[0]->title);
        $this->assertSame('Content 2 of 2 · September 2026', $obligations[1]->title);

        foreach ($obligations as $obligation) {
            $this->assertSame('content_video', $obligation->type);
            $this->assertSame('pending', $obligation->status);
            $this->assertSame($endorser->id, $obligation->endorser_id);
            // Due at the end of the month the kit landed in, not the delivery day.
            $this->assertSame('2026-09-30', $obligation->due_date->toDateString());
        }
    }

    public function test_a_kit_still_in_transit_owes_nothing_yet(): void
    {
        $endorser = $this->endorser();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/pr-kits', [
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'in_transit',
            'content_quota' => 2,
        ]);

        $this->assertSame(0, Obligation::count());

        // Marking it delivered starts the clock.
        $kit = PrKit::sole();
        $this->actingAs($admin)->put("/admin/pr-kits/{$kit->id}", [
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);

        $this->assertSame(2, Obligation::count());
    }

    public function test_saving_the_same_kit_again_does_not_duplicate_its_content(): void
    {
        $endorser = $this->endorser();
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);

        $this->assertSame(2, $kit->syncContentObligations());
        $this->assertSame(0, $kit->fresh()->syncContentObligations());
        $this->assertSame(0, $kit->fresh()->syncContentObligations());
        $this->assertSame(2, Obligation::count());
    }

    public function test_raising_the_quota_tops_up_without_touching_delivered_content(): void
    {
        $endorser = $this->endorser();
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);
        $kit->syncContentObligations();

        $first = Obligation::where('sequence', 1)->sole();
        $first->update(['status' => 'completed', 'proof_url' => 'https://example.test/reel']);

        $kit->update(['content_quota' => 3]);
        $this->assertSame(1, $kit->fresh()->syncContentObligations());

        $this->assertSame(3, Obligation::count());
        // The completed one keeps its status and its proof.
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('https://example.test/reel', $first->fresh()->proof_url);
    }

    public function test_progress_counts_delivered_content_against_the_quota(): void
    {
        $endorser = $this->endorser();
        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'endorser_id' => $endorser->id,
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);
        $kit->syncContentObligations();

        $this->assertSame(['done' => 0, 'quota' => 2], $kit->fresh()->contentProgress());

        Obligation::where('sequence', 1)->sole()->update(['status' => 'completed']);
        $this->assertSame(['done' => 1, 'quota' => 2], $kit->fresh()->contentProgress());

        // A submission awaiting review is not yet delivered content.
        Obligation::where('sequence', 2)->sole()->update(['status' => 'submitted']);
        $this->assertSame(['done' => 1, 'quota' => 2], $kit->fresh()->contentProgress());
    }

    public function test_a_kit_with_no_endorser_creates_nothing(): void
    {
        $kit = PrKit::create([
            'recipient' => 'Walk-in guest',
            'delivery_date' => '2026-09-04',
            'status' => 'delivered',
            'content_quota' => 2,
        ]);

        $this->assertSame(0, $kit->syncContentObligations());
        $this->assertSame(0, Obligation::count());
    }

    public function test_coverage_screen_flags_an_endorser_with_no_kit_this_month(): void
    {
        $covered = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        Endorser::create(['name' => 'JR Villanueva', 'type' => 'racer', 'status' => 'active']);

        $kit = PrKit::create([
            'recipient' => 'Team Redline',
            'endorser_id' => $covered->id,
            'delivery_date' => today()->startOfMonth()->addDays(3),
            'status' => 'delivered',
            'content_quota' => 2,
        ]);
        $kit->syncContentObligations();

        $this->actingAs($this->admin())->get('/admin/kit-coverage')
            ->assertOk()
            ->assertSee('Team Redline')
            ->assertSee('JR Villanueva')
            ->assertSee('No kit')
            ->assertSee('2 owed')
            // Only the endorser with no kit is counted, not every row.
            ->assertSee('1 endorser</strong> has no kit recorded', false);
    }
}
