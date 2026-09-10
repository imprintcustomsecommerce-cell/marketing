<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingQueueTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name, string $role = 'staff', string $team = User::TEAM_MARKETING): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role, 'team' => $team, 'must_change_password' => false]);
    }

    private function raise(User $by, array $overrides = []): void
    {
        $this->actingAs($by)->post('/admin/tasks', array_merge([
            'title' => 'Draft the anniversary poster',
            'task_date' => today()->toDateString(),
            'for_team' => User::TEAM_MARKETING,
        ], $overrides))->assertRedirect();
    }

    public function test_work_raised_for_marketing_shows_to_the_whole_marketing_team(): void
    {
        $admin = $this->person('Joey', 'admin');
        $two = $this->person('Marketing 2');
        $three = $this->person('Marketing 3');

        $this->raise($admin);

        foreach ([$two, $three] as $viewer) {
            $this->actingAs($viewer)->get('/admin/tasks')
                ->assertOk()
                ->assertSee('Draft the anniversary poster')
                ->assertSee('For the marketing team');
        }
    }

    public function test_the_crew_do_not_see_marketing_work(): void
    {
        $this->raise($this->person('Marketing 2'));
        $crew = $this->person('Crew', 'staff', User::TEAM_MULTIMEDIA);

        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Draft the anniversary poster');
    }

    public function test_marketing_does_not_see_the_crews_queue(): void
    {
        $admin = $this->person('Joey', 'admin');
        $this->actingAs($admin)->post('/admin/tasks', [
            'title' => 'Cut the teaser', 'task_date' => today()->toDateString(), 'for_team' => User::TEAM_MULTIMEDIA,
        ]);

        $this->actingAs($this->person('Marketing 3'))->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('For the marketing team');
    }

    public function test_anyone_on_marketing_can_take_the_work_on(): void
    {
        $this->raise($this->person('Joey', 'admin'));
        $two = $this->person('Marketing 2');
        $task = Task::sole();

        $this->actingAs($two)->post("/admin/tasks/{$task->id}/claim")->assertRedirect();

        $this->assertSame($two->id, $task->fresh()->user_id);
    }

    public function test_a_marketing_task_can_be_handed_to_one_named_colleague(): void
    {
        $admin = $this->person('Joey', 'admin');
        $two = $this->person('Marketing 2');

        $this->raise($admin, ['assign_to' => $two->id]);

        $task = Task::sole();
        $this->assertSame($two->id, $task->user_id);
        $this->assertSame(User::TEAM_MARKETING, $task->for_team);
    }

    public function test_a_crew_member_cannot_be_handed_marketing_work(): void
    {
        $admin = $this->person('Joey', 'admin');
        $crew = $this->person('Crew', 'staff', User::TEAM_MULTIMEDIA);

        $this->actingAs($admin)->post('/admin/tasks', [
            'title' => 'Draft the anniversary poster', 'task_date' => today()->toDateString(),
            'for_team' => User::TEAM_MARKETING, 'assign_to' => $crew->id,
        ])->assertStatus(422);

        $this->assertSame(0, Task::count());
    }

    public function test_both_queues_are_offered_on_the_form(): void
    {
        $this->actingAs($this->person('Joey', 'admin'))->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Multimedia queue')
            ->assertSee('Marketing queue')
            ->assertSee('Personal board (only you)');
    }
}
