<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObligationListTest extends TestCase
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

    private function obligation(array $overrides = []): Obligation
    {
        $endorser = Endorser::firstOrCreate(
            ['name' => 'Team Redline'],
            ['type' => 'team', 'status' => 'active'],
        );

        return Obligation::create(array_merge([
            'endorser_id' => $endorser->id,
            'title' => 'Ride-out reel',
            'type' => 'content_video',
            'due_date' => today()->addDays(3),
            'status' => 'pending',
        ], $overrides));
    }

    public function test_one_click_marks_content_delivered_and_dates_it(): void
    {
        $joey = $this->joey();
        $obligation = $this->obligation();

        $this->actingAs($joey)->patch("/admin/obligations/{$obligation->id}/status", ['status' => 'completed'])
            ->assertRedirect();

        $obligation->refresh();
        $this->assertSame('completed', $obligation->status);
        $this->assertSame(today()->toDateString(), $obligation->completed_on->toDateString());
    }

    public function test_reopening_clears_the_delivery_date(): void
    {
        $joey = $this->joey();
        $obligation = $this->obligation(['status' => 'completed', 'completed_on' => today()->subDay()]);

        $this->actingAs($joey)->patch("/admin/obligations/{$obligation->id}/status", ['status' => 'pending']);

        $obligation->refresh();
        $this->assertSame('pending', $obligation->status);
        $this->assertNull($obligation->completed_on);
    }

    public function test_an_existing_delivery_date_is_kept(): void
    {
        $joey = $this->joey();
        $obligation = $this->obligation(['completed_on' => today()->subDays(4)]);

        $this->actingAs($joey)->patch("/admin/obligations/{$obligation->id}/status", ['status' => 'completed']);

        // Marking it delivered must not rewrite a date somebody set deliberately.
        $this->assertSame(today()->subDays(4)->toDateString(), $obligation->fresh()->completed_on->toDateString());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $obligation = $this->obligation();

        $this->actingAs($this->joey())->patch("/admin/obligations/{$obligation->id}/status", ['status' => 'maybe'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $obligation->fresh()->status);
    }

    public function test_the_views_slice_the_list_the_way_the_work_runs(): void
    {
        $joey = $this->joey();

        $this->obligation(['title' => 'Late reel', 'due_date' => today()->subWeek()]);
        $this->obligation(['title' => 'This week reel', 'due_date' => today()->addDays(2)]);
        $this->obligation(['title' => 'Next month reel', 'due_date' => today()->addDays(40)]);
        $this->obligation(['title' => 'Finished reel', 'status' => 'completed', 'completed_on' => today()]);

        $this->actingAs($joey)->get('/admin/obligations?show=overdue')
            ->assertOk()->assertSee('Late reel')->assertDontSee('This week reel');

        $this->actingAs($joey)->get('/admin/obligations?show=week')
            ->assertOk()->assertSee('This week reel')->assertDontSee('Next month reel');

        $this->actingAs($joey)->get('/admin/obligations?show=done')
            ->assertOk()->assertSee('Finished reel')->assertDontSee('Late reel');

        // Open covers everything still owed, whenever it falls due.
        $this->actingAs($joey)->get('/admin/obligations')
            ->assertOk()->assertSee('Late reel')->assertSee('Next month reel')->assertDontSee('Finished reel');
    }

    public function test_the_list_can_be_narrowed_to_one_endorser(): void
    {
        $joey = $this->joey();
        $other = Endorser::create(['name' => 'JR Villanueva', 'type' => 'racer', 'status' => 'active']);

        $this->obligation(['title' => 'Redline reel']);
        $this->obligation(['title' => 'JR reel', 'endorser_id' => $other->id]);

        $this->actingAs($joey)->get("/admin/obligations?endorser={$other->id}")
            ->assertOk()->assertSee('JR reel')->assertDontSee('Redline reel');
    }

    public function test_a_clear_overdue_list_says_so(): void
    {
        $this->obligation();

        $this->actingAs($this->joey())->get('/admin/obligations?show=overdue')
            ->assertOk()
            ->assertSee('Nothing is overdue');
    }
}
