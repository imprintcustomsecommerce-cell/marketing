<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The daily-board half of the same handoff as coverage: marketing raise a task
 * for the multimedia team, it waits in a queue belonging to nobody, and
 * whoever takes it gets it on their own board for today.
 */
class TaskHandoffTest extends TestCase
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

    private function marketing(string $name = 'Marketing 2'): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);
    }

    private function crew(string $name = 'Multimedia 1'): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MULTIMEDIA,
            'is_active' => true,
        ]);
    }

    /** @return array<string,mixed> */
    private function taskFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cut a teaser for the expo',
            'task_date' => today()->toDateString(),
            'for_team' => User::TEAM_MULTIMEDIA,
        ], $overrides);
    }

    public function test_marketing_can_raise_a_task_for_the_crew(): void
    {
        $marketing = $this->marketing();

        $this->actingAs($marketing)->post('/admin/tasks', $this->taskFields())
            ->assertRedirect();

        $task = Task::firstOrFail();

        $this->assertSame(User::TEAM_MULTIMEDIA, $task->for_team);
        $this->assertSame($marketing->id, $task->created_by);

        // Nobody is picked. It belongs to the team until someone takes it.
        $this->assertNull($task->user_id);
        $this->assertNull($task->claimed_at);
    }

    public function test_a_raised_task_never_lands_on_the_raisers_own_board(): void
    {
        $marketing = $this->marketing();
        $this->actingAs($marketing)->post('/admin/tasks', $this->taskFields());

        $this->assertSame(0, Task::where('user_id', $marketing->id)->count());
    }

    public function test_the_crew_see_it_waiting_and_can_take_it(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $crew = $this->crew();

        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('From marketing')
            ->assertSee('Cut a teaser for the expo');

        $this->actingAs($crew)->post("/admin/tasks/{$task->id}/claim")->assertRedirect();

        $task->refresh();
        $this->assertSame($crew->id, $task->user_id);
        $this->assertNotNull($task->claimed_at);
    }

    public function test_marketing_tasks_go_to_multimedia_when_no_destination_is_selected(): void
    {
        $marketing = $this->marketing();

        $this->actingAs($marketing)->post('/admin/tasks', [
            'title' => 'Prepare event photos',
            'task_date' => today()->toDateString(),
        ])->assertRedirect();

        $task = Task::sole();
        $this->assertSame(User::TEAM_MULTIMEDIA, $task->for_team);
        $this->assertNull($task->user_id);
    }

    public function test_the_marketing_administrator_cannot_see_or_claim_the_multimedia_queue(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $this->actingAs($joey)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('From marketing')
            ->assertDontSee('Take it');

        $this->actingAs($joey)->post("/admin/tasks/{$task->id}/claim")->assertForbidden();
        $this->assertNull($task->fresh()->user_id);
    }

    /**
     * A request raised last week is work for the day it is picked up, not for
     * the day it was written, or it lands behind the board being looked at.
     */
    public function test_a_claimed_task_lands_on_todays_board(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields([
            'task_date' => today()->subWeek()->toDateString(),
        ]));

        $task = Task::firstOrFail();
        $crew = $this->crew();

        $this->actingAs($crew)->post("/admin/tasks/{$task->id}/claim");

        $this->assertSame(today()->toDateString(), $task->refresh()->task_date->toDateString());
    }

    public function test_a_task_already_taken_cannot_be_taken_again(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $first = $this->crew();
        $second = $this->crew('Multimedia 2');

        $this->actingAs($first)->post("/admin/tasks/{$task->id}/claim");
        $this->actingAs($second)->post("/admin/tasks/{$task->id}/claim")->assertNotFound();

        $this->assertSame($first->id, $task->refresh()->user_id);
    }

    public function test_a_taken_task_leaves_the_queue(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $crew = $this->crew();
        $this->actingAs($crew)->followingRedirects()->post("/admin/tasks/{$task->id}/claim")->assertOk();

        $this->actingAs($this->crew('Multimedia 2'))->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('From marketing');
    }

    public function test_a_task_can_be_put_back_in_the_queue(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $crew = $this->crew();
        $this->actingAs($crew)->post("/admin/tasks/{$task->id}/claim");
        $this->actingAs($crew)->post("/admin/tasks/{$task->id}/release")->assertRedirect();

        $task->refresh();
        $this->assertNull($task->user_id);
        $this->assertNull($task->claimed_at);
        $this->assertSame('todo', $task->status);
    }

    public function test_the_crew_cannot_hand_work_to_themselves_as_a_team(): void
    {
        $crew = $this->crew();

        // Not offered on the screen, and refused if the request is made anyway.
        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Multimedia queue');

        $this->actingAs($crew)->post('/admin/tasks', $this->taskFields())
            ->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_a_task_cannot_be_raised_for_marketing(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields([
            'for_team' => User::TEAM_MARKETING,
        ]))->assertSessionHasErrors('for_team');

        $this->assertSame(0, Task::count());
    }

    public function test_marketing_can_explicitly_add_a_personal_task(): void
    {
        $marketing = $this->marketing();

        $this->actingAs($marketing)->post('/admin/tasks', [
            'title' => 'Draft the ride-out captions',
            'task_date' => today()->toDateString(),
            'for_team' => 'personal',
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame($marketing->id, $task->user_id);
        $this->assertNull($task->for_team);
    }

    public function test_the_sidebar_counts_work_waiting_for_the_crew(): void
    {
        $this->actingAs($this->joey())->post('/admin/tasks', $this->taskFields());

        // The rendered element, not the class name: "nav-badge" on its own also
        // appears in the layout's stylesheet and would match on every page.
        $badge = '<span class="nav-badge">1</span>';

        // The crew are the ones who have to act on it, so only they get the count.
        $this->actingAs($this->crew())->get('/admin/tasks')
            ->assertOk()
            ->assertSee($badge, false);

        $this->actingAs($this->marketing())->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee($badge, false);
    }

    public function test_the_raiser_can_withdraw_a_task_nobody_has_taken(): void
    {
        $marketing = $this->marketing();
        $this->actingAs($marketing)->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $this->actingAs($marketing)->delete("/admin/tasks/{$task->id}")->assertRedirect();

        $this->assertSame(0, Task::count());
    }

    /**
     * Cancelling the work is still marketing's call after the crew have taken it
     * on, so the raiser can withdraw it. Rewording or re-queueing someone's live
     * work is not: that stays with them.
     */
    public function test_the_raiser_can_withdraw_it_but_not_reword_it_once_taken(): void
    {
        $marketing = $this->marketing();
        $this->actingAs($marketing)->post('/admin/tasks', $this->taskFields());
        $task = Task::firstOrFail();

        $this->actingAs($this->crew())->post("/admin/tasks/{$task->id}/claim");

        $this->actingAs($marketing)->put("/admin/tasks/{$task->id}", ['title' => 'Renamed'])->assertForbidden();
        $this->actingAs($marketing)->post("/admin/tasks/{$task->id}/release")->assertForbidden();
        $this->assertSame('Cut a teaser for the expo', $task->fresh()->title);

        $this->actingAs($marketing)->delete("/admin/tasks/{$task->id}")->assertRedirect();
        $this->assertSame(0, Task::count());
    }
}
