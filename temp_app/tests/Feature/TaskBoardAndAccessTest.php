<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TaskBoardAndAccessTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $name, string $team, string $role = 'staff'): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
            'password' => 'secret1234',
            'role' => $role,
            'team' => $team,
            'is_active' => true,
        ]);
    }

    private function joey(): User
    {
        return $this->make('Joey', User::TEAM_MARKETING, 'admin');
    }

    private function marketingStaff(): User
    {
        return $this->make('Marketing 2', User::TEAM_MARKETING);
    }

    private function crew(): User
    {
        return $this->make('Multimedia 1', User::TEAM_MULTIMEDIA);
    }

    // ---------------------------------------------------------------- access

    public function test_marketing_staff_cannot_reach_the_multimedia_screens(): void
    {
        $this->joey();
        $staff = $this->marketingStaff();

        $this->actingAs($staff)->get('/admin/coverage')->assertForbidden();
        $this->actingAs($staff)->get('/admin/team')->assertForbidden();
    }

    public function test_marketing_staff_do_not_see_multimedia_in_the_menu(): void
    {
        $this->joey();
        $staff = $this->marketingStaff();

        $response = $this->actingAs($staff)->get('/admin');

        $response->assertOk()
            ->assertDontSee('Event Coverage')
            ->assertDontSee('Multimedia')
            // What they do get instead.
            ->assertSee('Daily Tasks')
            ->assertSee('My Account');
    }

    public function test_the_crew_see_coverage_but_not_account_management(): void
    {
        $this->joey();
        $crew = $this->crew();

        $this->actingAs($crew)->get('/admin/coverage')->assertOk();
        $this->actingAs($crew)->get('/admin/team')->assertForbidden();
    }

    public function test_the_administrator_sees_both_sides(): void
    {
        $joey = $this->joey();

        $this->actingAs($joey)->get('/admin/coverage')->assertOk();
        $this->actingAs($joey)->get('/admin/team')->assertOk();
        $this->actingAs($joey)->get('/admin/tasks')->assertOk();
    }

    // ------------------------------------------------------------ task board

    public function test_a_task_moves_across_the_board(): void
    {
        $staff = $this->marketingStaff();

        $this->actingAs($staff)->post('/admin/tasks', [
            'title' => 'Draft the ride-out captions',
            'task_date' => today()->toDateString(),
            'for_team' => 'personal',
        ]);

        $task = Task::sole();
        $this->assertSame('todo', $task->status);
        $this->assertSame($staff->id, $task->user_id);

        $this->actingAs($staff)->put("/admin/tasks/{$task->id}", ['status' => 'done']);
        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        // Reopening clears the completion stamp.
        $this->actingAs($staff)->put("/admin/tasks/{$task->id}", ['status' => 'doing']);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_a_task_can_be_reworded_without_losing_its_column(): void
    {
        $staff = $this->marketingStaff();

        $task = Task::create([
            'user_id' => $staff->id,
            'title' => 'Draft the ride-out captoins',
            'task_date' => today(),
            'status' => 'doing',
        ]);

        $this->actingAs($staff)->put("/admin/tasks/{$task->id}", [
            'title' => 'Draft the ride-out captions',
            'details' => 'Tag the sponsors.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('Draft the ride-out captions', $task->title);
        $this->assertSame('Tag the sponsors.', $task->details);
        // Editing the wording must not knock the card back to To do.
        $this->assertSame('doing', $task->status);
    }

    public function test_the_day_shows_how_much_is_finished(): void
    {
        $staff = $this->marketingStaff();

        Task::create(['user_id' => $staff->id, 'title' => 'One', 'task_date' => today(), 'status' => 'done']);
        Task::create(['user_id' => $staff->id, 'title' => 'Two', 'task_date' => today(), 'status' => 'todo']);
        Task::create(['user_id' => $staff->id, 'title' => 'Three', 'task_date' => today(), 'status' => 'doing']);

        $this->actingAs($staff)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('of 3 done');
    }

    public function test_staff_cannot_touch_another_persons_task(): void
    {
        $mine = $this->marketingStaff();
        $theirs = $this->crew();

        $task = Task::create([
            'user_id' => $theirs->id,
            'title' => 'Their task',
            'task_date' => today(),
            'status' => 'todo',
        ]);

        $this->actingAs($mine)->put("/admin/tasks/{$task->id}", ['status' => 'done'])->assertForbidden();
        $this->actingAs($mine)->delete("/admin/tasks/{$task->id}")->assertForbidden();
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_marketing_can_read_a_crew_board_but_not_change_it(): void
    {
        $mine = $this->marketingStaff();
        $theirs = $this->crew();

        Task::create(['user_id' => $theirs->id, 'title' => 'Secret shoot plan', 'task_date' => today(), 'status' => 'todo']);

        $this->actingAs($mine)->get("/admin/tasks?user={$theirs->id}")
            ->assertOk()
            ->assertSee('Secret shoot plan')
            ->assertSee('Read only');
    }

    public function test_staff_get_their_own_board_when_they_ask_for_a_colleagues(): void
    {
        $mine = $this->marketingStaff();
        // Named apart: the helper builds the email from the name.
        $colleague = $this->make('Marketing 3', User::TEAM_MARKETING);

        Task::create(['user_id' => $colleague->id, 'title' => 'Private desk work', 'task_date' => today(), 'status' => 'todo']);

        // Reading across teams is deliberate; reading across desks is not.
        $this->actingAs($mine)->get("/admin/tasks?user={$colleague->id}")
            ->assertOk()
            ->assertDontSee('Private desk work');
    }

    public function test_the_administrator_can_read_anyones_board(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();

        Task::create(['user_id' => $crew->id, 'title' => 'Edit the ride-out reel', 'task_date' => today(), 'status' => 'doing']);

        $this->actingAs($joey)->get("/admin/tasks?user={$crew->id}")
            ->assertOk()
            ->assertSee('Edit the ride-out reel')
            ->assertSee("Multimedia 1's board", false);
    }

    public function test_unfinished_work_can_be_carried_over_to_today(): void
    {
        $staff = $this->marketingStaff();

        Task::create(['user_id' => $staff->id, 'title' => 'Still open', 'task_date' => today()->subDay(), 'status' => 'todo']);
        Task::create(['user_id' => $staff->id, 'title' => 'Already done', 'task_date' => today()->subDay(), 'status' => 'done']);

        $this->actingAs($staff)->post('/admin/tasks/carry-over', ['date' => today()->toDateString()]);

        $this->assertSame(today()->toDateString(), Task::where('title', 'Still open')->sole()->task_date->toDateString());
        // Finished work stays on the day it was finished.
        $this->assertSame(today()->subDay()->toDateString(), Task::where('title', 'Already done')->sole()->task_date->toDateString());
    }

    // --------------------------------------------------------------- account

    public function test_anyone_can_change_their_own_details(): void
    {
        $staff = $this->marketingStaff();

        $this->actingAs($staff)->get('/admin/account')->assertOk();

        $this->actingAs($staff)->put('/admin/account', [
            'name' => 'Ana Reyes',
            'email' => 'ana@imprintcustoms.ph',
        ])->assertRedirect('/admin/account');

        $staff->refresh();
        $this->assertSame('Ana Reyes', $staff->name);
        $this->assertSame('ana@imprintcustoms.ph', $staff->email);
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $staff = $this->marketingStaff();
        $original = $staff->password;

        $this->actingAs($staff)->put('/admin/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($original, $staff->fresh()->password);

        $this->actingAs($staff)->put('/admin/account/password', [
            'current_password' => 'secret1234',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertRedirect('/admin/account');

        $this->assertTrue(Hash::check('brand-new-secret', $staff->fresh()->password));
    }

    public function test_the_account_screen_cannot_be_used_to_change_your_own_role(): void
    {
        $staff = $this->marketingStaff();

        $this->actingAs($staff)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'role' => 'admin',
            'team' => User::TEAM_MULTIMEDIA,
        ]);

        $staff->refresh();
        $this->assertSame('staff', $staff->role);
        $this->assertSame(User::TEAM_MARKETING, $staff->team);
    }
}
