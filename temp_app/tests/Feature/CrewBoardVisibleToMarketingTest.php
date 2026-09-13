<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marketing can see what the crew have on, without being able to touch it.
 */
class CrewBoardVisibleToMarketingTest extends TestCase
{
    use RefreshDatabase;

    private function marketing(array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'role' => 'member', 'team' => User::TEAM_MARKETING, 'must_change_password' => false,
        ]);
    }

    private function crew(): User
    {
        return User::factory()->create([
            'name' => 'Mike', 'role' => 'member', 'team' => User::TEAM_MULTIMEDIA,
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function task(User $owner, array $overrides = []): Task
    {
        return Task::create($overrides + [
            'user_id' => $owner->id, 'created_by' => $owner->id,
            'title' => 'Edit the ride-out reel', 'task_date' => today(), 'status' => 'todo',
        ]);
    }

    public function test_marketing_can_read_a_crew_members_day(): void
    {
        $crew = $this->crew();
        $this->task($crew);

        $this->actingAs($this->marketing())->get("/admin/tasks?user={$crew->id}")
            ->assertOk()
            ->assertSee('Edit the ride-out reel')
            ->assertSee('Read only');
    }

    public function test_a_task_the_crew_wrote_themselves_is_shown(): void
    {
        $crew = $this->crew();
        // Written by the crew for themselves, not handed over by marketing.
        $this->task($crew, ['title' => 'Back up the weekend cards', 'for_team' => null]);

        $this->actingAs($this->marketing())->get("/admin/tasks?user={$crew->id}")
            ->assertOk()
            ->assertSee('Back up the weekend cards');
    }

    public function test_the_controls_are_not_offered(): void
    {
        $crew = $this->crew();
        $this->task($crew);

        $response = $this->actingAs($this->marketing())->get("/admin/tasks?user={$crew->id}")->assertOk();

        $response->assertDontSee('Add task');
        $response->assertDontSee('Mark done');
        $response->assertDontSee('Remove task');
    }

    public function test_marketing_cannot_add_to_a_crew_board(): void
    {
        $crew = $this->crew();

        $this->actingAs($this->marketing())->post('/admin/tasks', [
            'title' => 'Snuck in', 'task_date' => today()->toDateString(),
            'user' => $crew->id, 'for_team' => 'personal',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'Snuck in']);
    }

    public function test_marketing_cannot_carry_over_a_crew_board(): void
    {
        $crew = $this->crew();
        $this->task($crew, ['task_date' => today()->subDay()]);

        $this->actingAs($this->marketing())->post('/admin/tasks/carry-over', [
            'date' => today()->toDateString(), 'user' => $crew->id,
        ])->assertForbidden();
    }

    public function test_marketing_cannot_hand_in_a_crew_day(): void
    {
        $crew = $this->crew();
        $this->task($crew);

        $this->actingAs($this->marketing())->post('/admin/tasks/submit', [
            'date' => today()->toDateString(), 'user' => $crew->id,
        ])->assertForbidden();
    }

    public function test_marketing_cannot_read_another_marketing_board(): void
    {
        $colleague = $this->marketing(['name' => 'Someone Else']);
        $this->task($colleague, ['title' => 'Private desk work']);

        // Falls back to their own board rather than opening a colleague's.
        $this->actingAs($this->marketing())->get("/admin/tasks?user={$colleague->id}")
            ->assertOk()
            ->assertDontSee('Private desk work');
    }

    public function test_the_crew_cannot_read_a_marketing_board(): void
    {
        $marketing = $this->marketing(['name' => 'Boss Gian']);
        $this->task($marketing, ['title' => 'Marketing desk work']);

        $this->actingAs($this->crew())->get("/admin/tasks?user={$marketing->id}")
            ->assertOk()
            ->assertDontSee('Marketing desk work');
    }

    public function test_an_administrator_still_works_any_board(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
        $crew = $this->crew();
        $this->task($crew);

        $this->actingAs($admin)->get("/admin/tasks?user={$crew->id}")
            ->assertOk()
            ->assertSee('Edit the ride-out reel')
            ->assertDontSee('Read only');
    }
}
