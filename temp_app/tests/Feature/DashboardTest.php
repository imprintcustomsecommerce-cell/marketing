<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_a_brand_new_hub_shows_the_welcome_state(): void
    {
        $this->actingAs($this->joey())->get('/admin')
            ->assertOk()
            ->assertSee('Welcome to Imprint Hub')
            ->assertSee('+ Add your first event')
            // No panels of zeros before there is anything to count.
            ->assertDontSee('Latest inquiries');
    }

    public function test_it_lists_what_needs_chasing(): void
    {
        $joey = $this->joey();

        PublicSubmission::create([
            'type' => 'tambike_inquiry', 'status' => 'new_inquiry',
            'data' => ['event_name' => 'Sunday Ride Meet'], 'submitted_at' => now(),
        ]);

        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);

        Obligation::create([
            'endorser_id' => $endorser->id, 'title' => 'Ride-out reel', 'type' => 'content_video',
            'due_date' => today()->subWeek(), 'status' => 'pending',
        ]);

        // Never handed to the crew: nobody knows it needs shooting.
        Event::create(['name' => 'Ride-Out Manila', 'category' => 'tambike', 'event_date' => today()->addWeek(), 'status' => 'confirmed']);

        // Taken on by the crew, but nobody named to shoot it. A different gap
        // with a different answer, so it is counted separately.
        $taken = Event::create(['name' => 'Night Run', 'category' => 'tambike', 'event_date' => today()->addDays(3), 'status' => 'confirmed']);
        Coverage::create(['event_id' => $taken->id, 'stage' => CoverageDesk::ACCEPTED]);

        $response = $this->actingAs($joey)->get('/admin');

        $response->assertOk()
            ->assertSee('inquiry to read')
            ->assertSee('overdue obligation')
            ->assertSee('not sent to the crew')
            ->assertSee('without a shooter')
            ->assertSee('with no kit this month')
            ->assertDontSee('Nothing needs chasing');
    }

    public function test_boards_waiting_for_review_only_reach_the_administrator(): void
    {
        $joey = $this->joey();
        $staff = User::create([
            'name' => 'Marketing 2', 'email' => 'm2@example.test', 'password' => 'secret1234',
            'role' => 'staff', 'team' => User::TEAM_MARKETING, 'is_active' => true,
        ]);

        Event::create(['name' => 'Ride-Out', 'category' => 'tambike', 'event_date' => today()->addWeek(), 'status' => 'new']);
        Task::create(['user_id' => $staff->id, 'title' => 'Captions', 'task_date' => today(), 'status' => 'done']);
        TaskSubmission::create(['user_id' => $staff->id, 'task_date' => today(), 'submitted_at' => now()]);

        $this->actingAs($joey)->get('/admin')->assertOk()->assertSee('waiting for your check');
        $this->actingAs($staff)->get('/admin')->assertOk()->assertDontSee('waiting for your check');
    }

    public function test_a_clear_desk_says_so_instead_of_showing_zeros(): void
    {
        $joey = $this->joey();

        // An endorser who already has this month's kit, and nothing overdue.
        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        PrKit::create([
            'recipient' => 'Team Redline', 'purpose' => PrKit::PURPOSE_ENDORSER, 'endorser_id' => $endorser->id,
            'delivery_date' => today()->startOfMonth()->addDay(), 'status' => 'delivered',
        ]);

        $this->actingAs($joey)->get('/admin')
            ->assertOk()
            ->assertSee('Nothing needs chasing right now');
    }

    public function test_your_own_day_is_shown_with_its_progress(): void
    {
        $joey = $this->joey();

        Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);
        Task::create(['user_id' => $joey->id, 'title' => 'Draft the ride-out captions', 'task_date' => today(), 'status' => 'done']);
        Task::create(['user_id' => $joey->id, 'title' => 'Chase the expo permit', 'task_date' => today(), 'status' => 'todo']);
        // Someone else's task must not appear on your dashboard.
        $other = User::create([
            'name' => 'Crew', 'email' => 'crew@example.test', 'password' => 'secret1234',
            'role' => 'staff', 'team' => User::TEAM_MULTIMEDIA, 'is_active' => true,
        ]);
        Task::create(['user_id' => $other->id, 'title' => 'Secret shoot plan', 'task_date' => today(), 'status' => 'todo']);

        $this->actingAs($joey)->get('/admin')
            ->assertOk()
            ->assertSee('Your day')
            ->assertSee('Draft the ride-out captions')
            ->assertSee('Chase the expo permit')
            ->assertDontSee('Secret shoot plan');
    }
}
