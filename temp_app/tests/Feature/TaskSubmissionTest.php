<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSubmissionTest extends TestCase
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

    private function crew(): User
    {
        return $this->make('Multimedia 1', User::TEAM_MULTIMEDIA);
    }

    private function joey(): User
    {
        return $this->make('Joey', User::TEAM_MARKETING, 'admin');
    }

    private function taskFor(User $user): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title' => 'Edit the ride-out reel',
            'task_date' => today(),
            'status' => 'done',
        ]);
    }

    // -------------------------------------------------- multimedia visibility

    public function test_the_crew_only_get_daily_tasks_multimedia_and_their_account(): void
    {
        $this->joey();
        $crew = $this->crew();

        $response = $this->actingAs($crew)->get('/admin/tasks');

        $response->assertOk()
            ->assertSee('Daily Tasks')
            ->assertSee('Event Coverage')
            ->assertSee('My Account')
            // Marketing screens are gone from the menu entirely.
            ->assertDontSee('Endorsers')
            ->assertDontSee('PR Kits')
            ->assertDontSee('Obligations')
            ->assertDontSee('Monthly Coverage')
            ->assertDontSee('Search events');
    }

    public function test_the_crew_cannot_open_the_marketing_screens(): void
    {
        $this->joey();
        $crew = $this->crew();

        foreach (['/admin', '/admin/events', '/admin/endorsers', '/admin/calendar', '/admin/pr-kits', '/admin/obligations', '/admin/kit-coverage'] as $page) {
            $this->actingAs($crew)->get($page)->assertForbidden();
        }
    }

    public function test_the_crew_land_on_their_multimedia_home_after_signing_in(): void
    {
        $crew = $this->crew();

        $this->post('/login', ['email' => $crew->email, 'password' => 'secret1234'])
            ->assertRedirect('/admin/multimedia');
    }

    public function test_the_administrator_lands_on_the_dashboard(): void
    {
        $joey = $this->joey();

        $this->post('/login', ['email' => $joey->email, 'password' => 'secret1234'])
            ->assertRedirect('/admin');
    }

    // ------------------------------------------------------------ submissions

    public function test_a_board_can_be_sent_to_the_administrator(): void
    {
        $crew = $this->crew();
        $this->taskFor($crew);

        $this->actingAs($crew)->post('/admin/tasks/submit', [
            'date' => today()->toDateString(),
            'note' => 'Reel is uploaded, waiting on captions.',
        ])->assertRedirect();

        $submission = TaskSubmission::sole();
        $this->assertSame($crew->id, $submission->user_id);
        $this->assertSame('Reel is uploaded, waiting on captions.', $submission->note);
        $this->assertFalse($submission->isReviewed());

        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Waiting to be checked');
    }

    public function test_an_administrator_does_not_see_self_review_controls_on_their_own_board(): void
    {
        $joey = $this->joey();
        $this->taskFor($joey);

        $this->actingAs($joey)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Administrator boards do not need to be submitted for checking.')
            ->assertDontSee('Note for the administrator')
            ->assertDontSee('Send to admin for checking');
    }

    public function test_an_empty_day_cannot_be_sent(): void
    {
        $crew = $this->crew();

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString()])
            ->assertStatus(422);

        $this->assertSame(0, TaskSubmission::count());
    }

    public function test_sending_twice_updates_one_submission(): void
    {
        $crew = $this->crew();
        $this->taskFor($crew);

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString(), 'note' => 'First']);
        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString(), 'note' => 'Second']);

        $this->assertSame(1, TaskSubmission::count());
        $this->assertSame('Second', TaskSubmission::sole()->note);
    }

    public function test_the_administrator_sees_the_queue_and_can_check_a_board(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();
        $this->taskFor($crew);

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString()]);

        $this->actingAs($joey)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Waiting for your check')
            ->assertSee('Multimedia 1');

        $submission = TaskSubmission::sole();

        $this->actingAs($joey)->post("/admin/tasks/submissions/{$submission->id}/review", [
            'feedback' => 'Good work, add the sponsor tag next time.',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertTrue($submission->isReviewed());
        $this->assertSame($joey->id, $submission->reviewed_by);

        // The person who sent it sees the outcome on their own board.
        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Checked')
            ->assertSee('Good work, add the sponsor tag next time.');
    }

    public function test_staff_cannot_check_a_board_themselves(): void
    {
        $this->joey();
        $crew = $this->crew();
        $this->taskFor($crew);

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString()]);
        $submission = TaskSubmission::sole();

        $this->actingAs($crew)->post("/admin/tasks/submissions/{$submission->id}/review")->assertForbidden();
        $this->assertFalse($submission->fresh()->isReviewed());
    }

    public function test_staff_do_not_see_anyone_elses_submissions(): void
    {
        $this->joey();
        $crew = $this->crew();
        $other = $this->make('Multimedia 2', User::TEAM_MULTIMEDIA);

        $this->taskFor($other);
        $this->actingAs($other)->post('/admin/tasks/submit', ['date' => today()->toDateString()]);

        $this->actingAs($crew)->get('/admin/tasks')
            ->assertOk()
            ->assertDontSee('Waiting for your check');
    }

    public function test_sending_again_after_a_check_reopens_it(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();
        $this->taskFor($crew);

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString()]);
        $submission = TaskSubmission::sole();
        $this->actingAs($joey)->post("/admin/tasks/submissions/{$submission->id}/review", ['feedback' => 'Looks fine.']);

        $this->actingAs($crew)->post('/admin/tasks/submit', ['date' => today()->toDateString()]);

        $submission->refresh();
        $this->assertFalse($submission->isReviewed());
        $this->assertNull($submission->feedback);
        $this->assertSame(1, TaskSubmission::count());
    }
}
