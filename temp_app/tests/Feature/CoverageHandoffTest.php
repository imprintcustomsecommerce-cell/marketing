<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\PublicSubmission;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking an event hands the job to multimedia. Nobody is assigned — the shop
 * names a shooter only sometimes — so the job lands in the crew's queue and
 * someone there takes it on.
 */
class CoverageHandoffTest extends TestCase
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

    private function crew(string $name = 'Multimedia 1'): User
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

    /** @return array<string,mixed> */
    private function eventFields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sunday Ride-Out',
            'category' => 'tambike',
            'event_date' => today()->addWeek()->toDateString(),
            'status' => 'confirmed',
        ], $overrides);
    }

    public function test_booking_an_event_opens_a_coverage_job_for_the_crew(): void
    {
        $joey = $this->joey();

        $this->actingAs($joey)->post('/admin/events', $this->eventFields())
            ->assertRedirect(route('admin.events.index'));

        $coverage = Coverage::firstOrFail();

        $this->assertSame(CoverageDesk::REQUESTED, $coverage->stage);
        $this->assertSame($joey->id, $coverage->requested_by);
        $this->assertNotNull($coverage->requested_at);

        // Marketing does not pick the person. That is the crew's call.
        $this->assertNull($coverage->shooter_id);
        $this->assertNull($coverage->accepted_at);
    }

    public function test_an_inquiry_converted_into_an_event_reaches_the_crew_too(): void
    {
        $joey = $this->joey();

        $inquiry = PublicSubmission::create([
            'type' => 'tambike_inquiry',
            'status' => 'new_inquiry',
            'data' => ['name_or_group' => 'Cavite Underbone Society', 'preferred_date' => today()->addMonth()->toDateString()],
            'submitted_at' => now(),
        ]);

        $this->actingAs($joey)->post("/admin/inquiries/{$inquiry->id}/convert", ['as' => 'event']);

        $this->assertSame(CoverageDesk::REQUESTED, Coverage::firstOrFail()->stage);
    }

    public function test_the_new_job_shows_in_the_crews_queue(): void
    {
        $this->actingAs($this->joey())->post('/admin/events', $this->eventFields(['name' => 'Tanay Loop Run']));

        $this->actingAs($this->crew())->get('/admin/coverage?show=requested')
            ->assertOk()
            ->assertSee('Tanay Loop Run')
            ->assertSee('New request');
    }

    public function test_the_crew_can_take_a_job_on(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();

        $this->actingAs($joey)->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        $this->actingAs($crew)->post("/admin/coverage/{$event->id}/respond", ['answer' => 'accept'])
            ->assertRedirect();

        $coverage = Coverage::firstOrFail();
        $this->assertSame(CoverageDesk::ACCEPTED, $coverage->stage);
        $this->assertSame($crew->id, $coverage->accepted_by);
        $this->assertNotNull($coverage->accepted_at);
    }

    public function test_an_accepted_job_leaves_the_queue(): void
    {
        $this->actingAs($this->joey())->post('/admin/events', $this->eventFields(['name' => 'Tanay Loop Run']));
        $event = Event::firstOrFail();

        // Following the redirect consumes the "Coverage accepted for …" flash,
        // so what is asserted below is the list itself rather than the
        // confirmation of the click that got there.
        $crew = $this->crew();
        $this->actingAs($crew)
            ->followingRedirects()
            ->post("/admin/coverage/{$event->id}/respond", ['answer' => 'accept'])
            ->assertOk();

        $this->actingAs($crew)->get('/admin/coverage?show=requested')
            ->assertOk()
            ->assertDontSee('Tanay Loop Run');
    }

    public function test_a_declined_job_can_be_asked_for_again(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();

        $this->actingAs($joey)->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        $this->actingAs($crew)->post("/admin/coverage/{$event->id}/respond", ['answer' => 'decline']);
        $this->assertSame(CoverageDesk::DECLINED, Coverage::firstOrFail()->stage);

        $this->actingAs($joey)->post("/admin/events/{$event->id}/request-coverage")->assertRedirect();

        $this->assertSame(CoverageDesk::REQUESTED, Coverage::firstOrFail()->stage);
    }

    /**
     * Pressing the button on live work must not bounce it back into the queue
     * or lose the record of who took it on.
     */
    public function test_asking_again_does_not_disturb_a_job_already_taken_on(): void
    {
        $joey = $this->joey();
        $crew = $this->crew();

        $this->actingAs($joey)->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        $this->actingAs($crew)->post("/admin/coverage/{$event->id}/respond", ['answer' => 'accept']);
        $acceptedAt = Coverage::firstOrFail()->accepted_at;

        $this->actingAs($joey)->post("/admin/events/{$event->id}/request-coverage");

        $coverage = Coverage::firstOrFail();
        $this->assertSame(CoverageDesk::ACCEPTED, $coverage->stage);
        $this->assertSame($crew->id, $coverage->accepted_by);
        $this->assertEquals($acceptedAt, $coverage->accepted_at);
    }

    public function test_only_one_coverage_row_is_ever_opened_for_an_event(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        $this->actingAs($joey)->post("/admin/events/{$event->id}/request-coverage");
        $this->actingAs($joey)->post("/admin/events/{$event->id}/request-coverage");

        $this->assertSame(1, Coverage::where('event_id', $event->id)->count());
    }

    public function test_marketing_sees_that_a_job_is_still_with_the_crew(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/events', $this->eventFields(['name' => 'Tanay Loop Run']));

        $this->actingAs($joey)->get('/admin/events')
            ->assertOk()
            ->assertSee('With multimedia');
    }

    /**
     * Marketing cannot open the crew's screens, so the dashboard is the only
     * place they can find out whether a request was ever picked up.
     */
    public function test_the_dashboard_shows_what_is_still_with_the_crew(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/events', $this->eventFields(['name' => 'Tanay Loop Run']));

        $this->actingAs($joey)->get('/admin')
            ->assertOk()
            ->assertSee('With multimedia')
            // The strip renders the count in its own element and drops it from
            // the label, so the text is asserted without the leading number.
            ->assertSee('coverage request with multimedia')
            ->assertSee('Tanay Loop Run');
    }

    public function test_the_dashboard_stops_mentioning_a_request_once_it_is_taken(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/events', $this->eventFields(['name' => 'Tanay Loop Run']));
        $event = Event::firstOrFail();

        $this->actingAs($this->crew())
            ->followingRedirects()
            ->post("/admin/coverage/{$event->id}/respond", ['answer' => 'accept'])
            ->assertOk();

        $this->actingAs($joey)->get('/admin')
            ->assertOk()
            ->assertDontSee('coverage request with multimedia')
            ->assertDontSee('With multimedia');
    }

    /**
     * An event nobody was ever asked to cover is worse than an unanswered
     * request: it is not on anyone's screen at all.
     */
    public function test_the_dashboard_flags_an_event_never_sent_to_the_crew(): void
    {
        Event::create($this->eventFields(['name' => 'Legacy Ride']));

        $this->actingAs($this->joey())->get('/admin')
            ->assertOk()
            ->assertSee('upcoming event not sent to the crew');
    }

    /**
     * Marketing staff are not allowed on the coverage screen, so the card and
     * the chip must not send them somewhere that answers 403.
     */
    public function test_marketing_staff_are_not_pointed_at_a_screen_they_cannot_open(): void
    {
        $this->actingAs($this->joey())->post('/admin/events', $this->eventFields());

        $marketing = User::create([
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);

        $this->actingAs($marketing)->get('/admin')
            ->assertOk()
            ->assertSee('With multimedia')
            ->assertDontSee(route('admin.coverage.index', ['show' => 'requested']));

        // And the screen really is shut to them, so the link mattered.
        $this->actingAs($marketing)->get('/admin/coverage')->assertForbidden();
    }

    public function test_the_crew_cannot_be_asked_by_someone_outside_marketing(): void
    {
        $this->actingAs($this->joey())->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        // The request button lives on a marketing screen and stays shut to the crew.
        $this->actingAs($this->crew())->post("/admin/events/{$event->id}/request-coverage")
            ->assertForbidden();
    }

    public function test_marketing_cannot_answer_on_the_crews_behalf(): void
    {
        $joey = $this->joey();
        $this->actingAs($joey)->post('/admin/events', $this->eventFields());
        $event = Event::firstOrFail();

        $marketing = User::create([
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);

        $this->actingAs($marketing)->post("/admin/coverage/{$event->id}/respond", ['answer' => 'accept'])
            ->assertForbidden();

        $this->assertSame(CoverageDesk::REQUESTED, Coverage::firstOrFail()->stage);
    }

    /**
     * Events logged before the handoff existed were plainly already seen by the
     * crew, so shipping this must not drop the shop's whole history into the
     * new-work queue.
     */
    public function test_coverage_logged_before_the_handoff_is_not_treated_as_new_work(): void
    {
        $event = Event::create($this->eventFields(['name' => 'Old Anniversary']));

        // A row as the old code wrote one: no stage set by the application.
        Coverage::create(['event_id' => $event->id, 'photo_status' => 'posted', 'video_status' => 'posted']);

        \DB::table('coverages')->update(['stage' => CoverageDesk::ACCEPTED]);

        $this->actingAs($this->crew())->get('/admin/coverage?show=requested')
            ->assertOk()
            ->assertDontSee('Old Anniversary');
    }
}
