<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function marketing(): User
    {
        return User::factory()->create(['role' => 'admin', 'team' => User::TEAM_MARKETING, 'must_change_password' => false]);
    }

    private function crew(string $name = 'Multimedia 1'): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'staff', 'team' => User::TEAM_MULTIMEDIA,
            'is_active' => true, 'must_change_password' => false]);
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Edit the ride-out reel',
            'task_date' => today()->toDateString(),
            'for_team' => User::TEAM_MULTIMEDIA,
        ], $overrides);
    }

    public function test_marketing_can_hand_a_task_to_a_named_crew_member(): void
    {
        $marketing = $this->marketing();
        $crew = $this->crew('Rico');

        $this->actingAs($marketing)->post('/admin/tasks', $this->fields(['assign_to' => $crew->id]))
            ->assertRedirect();

        $task = Task::sole();
        $this->assertSame($crew->id, $task->user_id, 'it should land on their board, not the queue');
        $this->assertNotNull($task->claimed_at);
        $this->assertSame(User::TEAM_MULTIMEDIA, $task->for_team, 'kept so it can still be put back');
    }

    public function test_an_assigned_task_shows_on_that_persons_board(): void
    {
        $crew = $this->crew('Rico');
        $this->actingAs($this->marketing())->post('/admin/tasks', $this->fields(['assign_to' => $crew->id]));

        $this->actingAs($crew)->get('/admin/tasks')->assertOk()->assertSee('Edit the ride-out reel');
    }

    public function test_leaving_it_blank_still_goes_to_the_shared_queue(): void
    {
        $this->crew();
        $this->actingAs($this->marketing())->post('/admin/tasks', $this->fields(['assign_to' => '']))
            ->assertRedirect();

        $task = Task::sole();
        $this->assertNull($task->user_id);
        $this->assertSame(User::TEAM_MULTIMEDIA, $task->for_team);
    }

    public function test_work_cannot_be_pushed_onto_someone_outside_the_crew(): void
    {
        $marketing = $this->marketing();
        $otherDesk = User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MARKETING]);

        $this->actingAs($marketing)->post('/admin/tasks', $this->fields(['assign_to' => $otherDesk->id]))
            ->assertStatus(422);

        $this->assertSame(0, Task::count());
    }

    public function test_the_picker_is_offered_to_marketing_but_not_to_the_crew(): void
    {
        $this->crew('Rico');

        $this->actingAs($this->marketing())->get('/admin/tasks')->assertOk()
            ->assertSee('name="assign_to"', false)->assertSee('Rico');
        $this->actingAs($this->crew('Mika'))->get('/admin/tasks')->assertOk()
            ->assertDontSee('name="assign_to"', false);
    }
}
