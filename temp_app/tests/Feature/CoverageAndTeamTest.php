<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageAndTeamTest extends TestCase
{
    use RefreshDatabase;

    private function joey(): User
    {
        // The administrator is also one of the three marketing people.
        return User::create([
            'name' => 'Joey',
            'email' => 'joey@example.test',
            'password' => 'secret1234',
            'role' => 'admin',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);
    }

    private function shooter(string $name = 'Multimedia 1'): User
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

    private function event(string $name = 'Sunday Ride Meet'): Event
    {
        return Event::create([
            'name' => $name,
            'category' => 'tambike',
            'event_date' => today()->addWeek(),
            'venue' => 'Clark Speedway',
            'status' => 'confirmed',
        ]);
    }

    public function test_an_event_can_be_logged_with_shooter_editors_and_posting_dates(): void
    {
        $event = $this->event();
        $shooter = $this->shooter();
        $editor = $this->shooter('Multimedia 2');

        $this->actingAs($this->joey())->put("/admin/coverage/{$event->id}", [
            'shooter_id' => $shooter->id,
            'photo_editor_id' => $editor->id,
            'video_editor_id' => $editor->id,
            'photo_status' => 'posted',
            'photo_posted_on' => '2026-09-12',
            'video_status' => 'editing',
            'video_posted_on' => '2026-09-20',
            'remarks' => 'Drone shots pending.',
        ])->assertRedirect('/admin/coverage');

        $coverage = Coverage::sole();
        $this->assertSame($shooter->id, $coverage->shooter_id);
        $this->assertSame('2026-09-12', $coverage->photo_posted_on->toDateString());
        // The video is still being edited, so its posting date is not kept.
        $this->assertNull($coverage->video_posted_on);
        $this->assertSame('Drone shots pending.', $coverage->remarks);
    }

    public function test_an_event_can_be_left_without_a_shooter(): void
    {
        $event = $this->event();

        $this->actingAs($this->joey())->put("/admin/coverage/{$event->id}", [
            'shooter_id' => '',
            'photo_status' => 'not_required',
            'video_status' => 'not_required',
        ])->assertRedirect('/admin/coverage');

        $coverage = Coverage::sole();
        $this->assertNull($coverage->shooter_id);
        $this->assertTrue($coverage->needsShooter());
        $this->assertTrue($coverage->isComplete());
    }

    public function test_only_multimedia_staff_can_be_assigned(): void
    {
        $event = $this->event();
        $joey = $this->joey();

        // Joey is marketing, so cannot be picked as the shooter.
        $this->actingAs($joey)->put("/admin/coverage/{$event->id}", [
            'shooter_id' => $joey->id,
            'photo_status' => 'not_started',
            'video_status' => 'not_started',
        ])->assertSessionHasErrors('shooter_id');

        $this->assertSame(0, Coverage::count());
    }

    public function test_logging_the_same_event_twice_updates_one_row(): void
    {
        $event = $this->event();
        $joey = $this->joey();
        $shooter = $this->shooter();

        foreach (['not_started', 'editing'] as $status) {
            $this->actingAs($joey)->put("/admin/coverage/{$event->id}", [
                'shooter_id' => $shooter->id,
                'photo_status' => $status,
                'video_status' => 'not_started',
            ]);
        }

        $this->assertSame(1, Coverage::count());
        $this->assertSame('editing', Coverage::sole()->photo_status);
    }

    /**
     * "No shooter" means the crew took the job on and nobody is named yet.
     * A job still waiting to be picked up, or one marketing never sent, is a
     * different problem with a different answer and is counted elsewhere —
     * otherwise the three figures on the dashboard all include each other.
     */
    public function test_the_no_shooter_list_is_only_jobs_the_crew_have_taken_on(): void
    {
        $covered = $this->event('Covered Meet');
        $accepted = $this->event('Uncovered Meet');
        $stillWaiting = $this->event('Unanswered Meet');
        $this->event('Never Sent Meet');
        $shooter = $this->shooter();

        Coverage::create(['event_id' => $covered->id, 'stage' => CoverageDesk::ACCEPTED, 'shooter_id' => $shooter->id]);
        Coverage::create(['event_id' => $accepted->id, 'stage' => CoverageDesk::ACCEPTED]);
        Coverage::create(['event_id' => $stillWaiting->id, 'stage' => CoverageDesk::REQUESTED]);

        $this->actingAs($this->joey())->get('/admin/coverage?show=unassigned')
            ->assertOk()
            ->assertSee('Uncovered Meet')
            ->assertDontSee('Covered Meet')
            ->assertDontSee('Unanswered Meet')
            ->assertDontSee('Never Sent Meet');
    }

    /**
     * The list is laid out as six columns rather than the nine of the team's
     * spreadsheet, with the pairs that are always read together kept in one
     * cell. Nothing from the sheet may be lost in that rearranging.
     */
    public function test_the_coverage_list_still_carries_every_column_of_the_sheet(): void
    {
        $event = $this->event('Anniversary Ride');
        $shooter = $this->shooter();
        $photoEditor = $this->shooter('Multimedia 2');
        $videoEditor = $this->shooter('Multimedia 3');

        Coverage::create([
            'event_id' => $event->id,
            'shooter_id' => $shooter->id,
            'photo_editor_id' => $photoEditor->id,
            'video_editor_id' => $videoEditor->id,
            'photo_status' => 'posted',
            'photo_posted_on' => '2026-03-04',
            'video_status' => 'editing',
            'remarks' => 'Drone shots requested for the convoy.',
        ]);

        $response = $this->actingAs($this->joey())->get('/admin/coverage')->assertOk();

        foreach ([
            'Anniversary Ride',      // event name
            'Clark Speedway',        // location
            'Multimedia 1',          // shooter
            'Posted',                // photo edit status
            'Multimedia 2',          // photo editor
            'Mar 4, 2026',           // date posted
            'Editing',               // video edit status
            'Multimedia 3',          // video editor
            'Drone shots requested', // remarks
        ] as $expected) {
            $response->assertSee($expected);
        }
    }

    public function test_team_screen_shows_both_teams_and_their_workload(): void
    {
        $joey = $this->joey();
        $shooter = $this->shooter();
        $event = $this->event();

        Coverage::create([
            'event_id' => $event->id,
            'shooter_id' => $shooter->id,
            'photo_editor_id' => $shooter->id,
            'photo_status' => 'editing',
        ]);

        $this->actingAs($joey)->get('/admin/team')
            ->assertOk()
            ->assertSee('Marketing')
            ->assertSee('Multimedia')
            ->assertSee('Joey')
            ->assertSee('Multimedia 1')
            ->assertSee('1 shoots')
            ->assertSee('1 photo open');
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $joey = $this->joey();

        $this->actingAs($joey)->put("/admin/team/{$joey->id}", [
            'name' => 'Joey',
            'email' => 'joey@example.test',
            'role' => 'staff',
            'team' => User::TEAM_MARKETING,
            'is_active' => 1,
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $joey->fresh()->role);
    }

    public function test_editing_an_account_without_a_password_keeps_the_old_one(): void
    {
        $joey = $this->joey();
        $shooter = $this->shooter();
        $original = $shooter->password;

        $this->actingAs($joey)->put("/admin/team/{$shooter->id}", [
            'name' => 'Rico Cruz',
            'email' => 'rico@example.test',
            'role' => 'staff',
            'team' => User::TEAM_MULTIMEDIA,
            'is_active' => 1,
            'password' => '',
        ])->assertRedirect('/admin/team');

        $shooter->refresh();
        $this->assertSame('Rico Cruz', $shooter->name);
        $this->assertSame($original, $shooter->password);
    }

    /**
     * A syntax error in a Blade view is invisible to tests that only POST.
     * This loads every admin page so a broken template fails the build.
     */
    public function test_every_admin_page_renders(): void
    {
        $joey = $this->joey();
        $this->shooter();
        $event = $this->event();

        $pages = [
            '/admin',
            '/admin/events', '/admin/events/create', "/admin/events/{$event->id}/edit",
            '/admin/endorsers', '/admin/endorsers/create',
            '/admin/calendar', '/admin/kit-coverage',
            '/admin/pr-kits', '/admin/pr-kits/create',
            '/admin/obligations', '/admin/obligations/create',
            '/admin/coverage', "/admin/coverage/{$event->id}",
            '/admin/tasks', '/admin/account', '/admin/inquiries',
            '/admin/team', '/admin/team/create', "/admin/team/{$joey->id}/edit",
        ];

        foreach ($pages as $page) {
            $this->actingAs($joey)->get($page)->assertOk();
        }
    }
}
