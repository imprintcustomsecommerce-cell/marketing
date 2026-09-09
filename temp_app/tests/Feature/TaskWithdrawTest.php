<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWithdrawTest extends TestCase
{
    use RefreshDatabase;

    private function marketing(string $name = 'Marketing 2'): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'staff', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function crew(): User
    {
        return User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'must_change_password' => false]);
    }

    private function raise(User $by): Task
    {
        $this->actingAs($by)->post('/admin/tasks', [
            'title' => 'Cut the teaser', 'task_date' => today()->toDateString(), 'for_team' => User::TEAM_MULTIMEDIA,
        ])->assertRedirect();

        return Task::sole();
    }

    public function test_the_raiser_can_remove_a_task_after_the_crew_have_taken_it_on(): void
    {
        $marketing = $this->marketing();
        $task = $this->raise($marketing);

        $this->actingAs($this->crew())->post("/admin/tasks/{$task->id}/claim")->assertRedirect();
        $this->assertNotNull($task->fresh()->user_id, 'precondition: it has been taken on');

        $this->actingAs($marketing)->delete("/admin/tasks/{$task->id}")->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_marketing_sees_the_work_they_sent_with_a_way_to_remove_it(): void
    {
        $marketing = $this->marketing();
        $this->raise($marketing);

        $this->actingAs($marketing)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Work you sent to multimedia')
            ->assertSee('Cut the teaser')
            ->assertSee('Waiting for someone to take it');
    }

    public function test_another_marketing_person_cannot_remove_a_task_they_did_not_raise(): void
    {
        $task = $this->raise($this->marketing('Marketing 2'));

        $this->actingAs($this->marketing('Marketing 3'))->delete("/admin/tasks/{$task->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_the_crew_still_see_only_their_own_board(): void
    {
        $this->raise($this->marketing());

        $this->actingAs($this->crew())->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Work you sent to multimedia');
    }
}
