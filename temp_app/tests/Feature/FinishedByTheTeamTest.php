<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Work other people ticked off shows on the daily tasks screen, without anyone
 * having to switch to their board to find it.
 */
class FinishedByTheTeamTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $team, string $role = 'member'): User
    {
        return User::factory()->create([
            'name' => $name, 'email' => str($name)->slug().'@example.test',
            'team' => $team, 'role' => $role, 'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function task(User $owner, string $title, array $overrides = []): Task
    {
        return Task::create($overrides + [
            'user_id' => $owner->id, 'created_by' => $owner->id,
            'title' => $title, 'task_date' => today(), 'status' => 'done', 'completed_at' => now(),
        ]);
    }

    public function test_the_administrator_sees_what_the_crew_finished(): void
    {
        $admin = $this->user('Joey', User::TEAM_MARKETING, 'admin');
        $crew = $this->user('Mike', User::TEAM_MULTIMEDIA);
        $this->task($crew, 'Backed up the weekend cards');

        $this->actingAs($admin)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Finished by the team today')
            ->assertSee('Backed up the weekend cards')
            ->assertSee('Mike');
    }

    public function test_marketing_sees_what_the_crew_finished(): void
    {
        $crew = $this->user('Mike', User::TEAM_MULTIMEDIA);
        $this->task($crew, 'Cut the reel');

        $this->actingAs($this->user('Gian', User::TEAM_MARKETING))->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Cut the reel');
    }

    public function test_work_still_open_is_not_listed(): void
    {
        $crew = $this->user('Mike', User::TEAM_MULTIMEDIA);
        $this->task($crew, 'Half finished', ['status' => 'doing', 'completed_at' => null]);

        $this->actingAs($this->user('Gian', User::TEAM_MARKETING))->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Half finished');
    }

    public function test_marketing_does_not_see_another_marketing_desk(): void
    {
        $colleague = $this->user('Other Marketing', User::TEAM_MARKETING);
        $this->task($colleague, 'Private desk work');

        $this->actingAs($this->user('Gian', User::TEAM_MARKETING))->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Private desk work');
    }

    public function test_the_administrator_does_see_a_marketing_desk(): void
    {
        $admin = $this->user('Joey', User::TEAM_MARKETING, 'admin');
        $this->task($this->user('Gian', User::TEAM_MARKETING), 'Marketing desk work');

        $this->actingAs($admin)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Marketing desk work');
    }

    public function test_the_crew_do_not_get_the_panel(): void
    {
        $crew = $this->user('Mike', User::TEAM_MULTIMEDIA);
        $this->task($this->user('Gian', User::TEAM_MARKETING), 'Marketing desk work');

        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Finished by the team');
    }

    public function test_your_own_finished_work_is_not_repeated(): void
    {
        $admin = $this->user('Joey', User::TEAM_MARKETING, 'admin');
        $this->task($admin, 'My own finished task');

        $this->actingAs($admin)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Finished by the team');
    }

    public function test_it_follows_the_day_being_looked_at(): void
    {
        $admin = $this->user('Joey', User::TEAM_MARKETING, 'admin');
        $crew = $this->user('Mike', User::TEAM_MULTIMEDIA);
        $this->task($crew, 'Yesterday work', ['task_date' => today()->subDay()]);

        $this->actingAs($admin)->get('/admin/tasks')->assertOk()->assertDontSee('Yesterday work');

        $this->actingAs($admin)->get('/admin/tasks?date='.today()->subDay()->toDateString())
            ->assertOk()
            ->assertSee('Yesterday work');
    }
}
