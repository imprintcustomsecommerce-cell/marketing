<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A personal task belongs to one board. The administrator can look at anyone's
 * board because they review the day's work; nobody else can.
 */
class PersonalBoardPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name, string $role = 'staff', string $team = User::TEAM_MARKETING): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role, 'team' => $team, 'must_change_password' => false]);
    }

    private function addPersonal(User $by, string $title): void
    {
        $this->actingAs($by)->post('/admin/tasks', [
            'title' => $title, 'task_date' => today()->toDateString(), 'for_team' => 'personal',
        ])->assertRedirect();
    }

    public function test_a_personal_task_is_not_shown_to_other_people(): void
    {
        $marketingTwo = $this->person('Marketing 2');
        $marketingThree = $this->person('Marketing 3');
        $crew = $this->person('Crew', 'staff', User::TEAM_MULTIMEDIA);

        $this->addPersonal($marketingTwo, 'Ring the printer about the tarpaulin');

        $this->actingAs($marketingTwo)->get('/admin/tasks')->assertOk()->assertSee('Ring the printer');
        $this->actingAs($marketingThree)->get('/admin/tasks')->assertOk()->assertDontSee('Ring the printer');
        $this->actingAs($crew)->get('/admin/tasks')->assertOk()->assertDontSee('Ring the printer');
    }

    public function test_the_administrators_own_board_is_private_too(): void
    {
        $admin = $this->person('Joey', 'admin');
        $marketing = $this->person('Marketing 2');

        $this->addPersonal($admin, 'Chase the supplier invoice');

        $this->actingAs($marketing)->get('/admin/tasks')->assertOk()->assertDontSee('Chase the supplier');
    }

    public function test_the_administrator_can_look_at_somebody_elses_board(): void
    {
        $admin = $this->person('Joey', 'admin');
        $marketing = $this->person('Marketing 2');

        $this->addPersonal($marketing, 'Ring the printer about the tarpaulin');

        $this->actingAs($admin)->get('/admin/tasks?user='.$marketing->id)
            ->assertOk()
            ->assertSee('Ring the printer');
    }

    public function test_staff_cannot_reach_another_board_by_asking_for_it(): void
    {
        $admin = $this->person('Joey', 'admin');
        $marketing = $this->person('Marketing 2');

        $this->addPersonal($admin, 'Chase the supplier invoice');

        // The switcher is the administrator's, so the parameter is ignored and
        // the requester is shown their own board rather than being refused.
        $this->actingAs($marketing)->get('/admin/tasks?user='.$admin->id)
            ->assertOk()
            ->assertDontSee('Chase the supplier');
    }
}
