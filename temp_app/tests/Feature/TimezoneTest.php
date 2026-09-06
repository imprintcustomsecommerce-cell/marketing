<?php

namespace Tests\Feature;

use App\Models\Endorser;
use App\Models\Obligation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shop runs on Manila time. On UTC the application was eight hours behind
 * the people using it, so between midnight and 8am local it still believed it
 * was the previous day.
 *
 * Every test here freezes a real instant inside that window — 17:00 UTC, which
 * is 01:00 the next morning in Manila — and uses dates written the way a person
 * would type them. Deriving the fixtures from today() instead would move both
 * sides of the comparison together and prove nothing.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** 01:00 on 5 September in the shop; still 4 September in UTC. */
    private const EARLY_MORNING = '2026-09-04 17:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_the_application_runs_on_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
    }

    public function test_early_morning_in_manila_is_already_the_new_day(): void
    {
        Carbon::setTestNow(Carbon::parse(self::EARLY_MORNING, 'UTC'));

        $this->assertSame('2026-09-05', today()->toDateString());
        $this->assertSame('2026-09-05 01:00', now()->format('Y-m-d H:i'));
    }

    public function test_a_board_opened_before_dawn_shows_that_days_tasks(): void
    {
        Carbon::setTestNow(Carbon::parse(self::EARLY_MORNING, 'UTC'));

        $joey = $this->joey();

        // Written for the 5th, because that is the date in the shop.
        Task::create([
            'user_id' => $joey->id,
            'title' => 'Draft the ride-out captions',
            'task_date' => '2026-09-05',
            'status' => 'todo',
        ]);

        $this->actingAs($joey)->get('/admin/tasks')
            ->assertOk()
            ->assertSee('Sep 5, 2026')
            ->assertSee('Draft the ride-out captions');
    }

    public function test_content_due_yesterday_is_already_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse(self::EARLY_MORNING, 'UTC'));

        $endorser = Endorser::create(['name' => 'Team Redline', 'type' => 'team', 'status' => 'active']);

        // Due on the 4th, and it is now the small hours of the 5th.
        $obligation = Obligation::create([
            'endorser_id' => $endorser->id,
            'title' => 'Ride-out reel',
            'type' => 'content_video',
            'due_date' => '2026-09-04',
            'status' => 'pending',
        ]);

        $this->assertTrue($obligation->isOverdue());
    }

    public function test_the_first_of_the_month_starts_the_new_month(): void
    {
        // 01:00 on 1 October in the shop; still 30 September in UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-30 17:00:00', 'UTC'));

        $this->assertSame('2026-10-01', today()->toDateString());
        // A kit delivered this morning must count for October, not September.
        $this->assertSame('2026-10-01', today()->startOfMonth()->toDateString());
    }
}
