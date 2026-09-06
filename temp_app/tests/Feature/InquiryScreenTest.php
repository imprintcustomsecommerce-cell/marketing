<?php

namespace Tests\Feature;

use App\Models\PublicSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InquiryScreenTest extends TestCase
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

    private function crew(): User
    {
        return User::create([
            'name' => 'Multimedia 1',
            'email' => 'crew@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MULTIMEDIA,
            'is_active' => true,
        ]);
    }

    private function inquiry(array $overrides = []): PublicSubmission
    {
        return PublicSubmission::create(array_merge([
            'type' => 'tambike_inquiry',
            'status' => 'new_inquiry',
            'data' => [
                'event_name' => 'Sunday Ride Meet',
                'group_name' => 'Redline Riders',
                'contact_person' => 'Rico Cruz',
                'contact_number' => '0917 123 4567',
                'email' => 'rico@example.test',
                'estimated_pax' => '150',
                'existing_client' => 'yes',
                'need_booth' => 'no',
                'notes' => 'Parking for 60 bikes.',
            ],
            'ip_address' => '203.0.113.9',
            'submitted_at' => now()->subHours(2),
        ], $overrides));
    }

    public function test_every_answered_field_is_readable_on_the_detail_screen(): void
    {
        $inquiry = $this->inquiry();

        $response = $this->actingAs($this->joey())->get("/admin/inquiries/{$inquiry->id}");

        $response->assertOk()
            ->assertSee('Sunday Ride Meet')
            ->assertSee('Redline Riders')
            ->assertSee('Rico Cruz')
            ->assertSee('0917 123 4567')
            ->assertSee('rico@example.test')
            ->assertSee('Parking for 60 bikes.')
            // Yes/no answers read as words, not raw values.
            ->assertSee('Existing Client')
            ->assertSee('Yes')
            ->assertSee('No');
    }

    public function test_the_list_shows_who_sent_it_and_from_which_form(): void
    {
        $this->inquiry();
        $this->inquiry([
            'type' => 'sponsorship_inquiry',
            'data' => ['racer_team_name' => 'JR Villanueva', 'email' => 'jr@example.test'],
        ]);

        $this->actingAs($this->joey())->get('/admin/inquiries')
            ->assertOk()
            ->assertSee('Sunday Ride Meet')
            ->assertSee('Tambike')
            ->assertSee('JR Villanueva')
            ->assertSee('Sponsorship');
    }

    public function test_inquiries_can_be_filtered_and_searched(): void
    {
        $joey = $this->joey();
        $this->inquiry();
        $this->inquiry([
            'type' => 'sponsorship_inquiry',
            'status' => 'confirmed',
            'data' => ['racer_team_name' => 'JR Villanueva'],
        ]);

        $this->actingAs($joey)->get('/admin/inquiries?type=sponsorship_inquiry')
            ->assertOk()
            ->assertSee('JR Villanueva')
            ->assertDontSee('Sunday Ride Meet');

        $this->actingAs($joey)->get('/admin/inquiries?status=new_inquiry')
            ->assertOk()
            ->assertSee('Sunday Ride Meet')
            ->assertDontSee('JR Villanueva');

        $this->actingAs($joey)->get('/admin/inquiries?q=Redline')
            ->assertOk()
            ->assertSee('Sunday Ride Meet')
            ->assertDontSee('JR Villanueva');
    }

    public function test_status_and_notes_record_who_handled_it(): void
    {
        $joey = $this->joey();
        $inquiry = $this->inquiry();

        $this->actingAs($joey)->put("/admin/inquiries/{$inquiry->id}", [
            'status' => 'quoted',
            'internal_notes' => 'Quoted 18,000, waiting on confirmation.',
        ])->assertRedirect("/admin/inquiries/{$inquiry->id}");

        $inquiry->refresh();
        $this->assertSame('quoted', $inquiry->status);
        $this->assertSame('Quoted 18,000, waiting on confirmation.', $inquiry->internal_notes);
        $this->assertSame($joey->id, $inquiry->handled_by);
        $this->assertNotNull($inquiry->handled_at);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $inquiry = $this->inquiry();

        $this->actingAs($this->joey())->put("/admin/inquiries/{$inquiry->id}", ['status' => 'maybe'])
            ->assertSessionHasErrors('status');

        $this->assertSame('new_inquiry', $inquiry->fresh()->status);
    }

    public function test_attachments_download_for_staff_and_stay_off_the_public_web(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('proposal.pdf', 20, 'application/pdf')
            ->store('public-submissions/sponsorship', 'local');

        $inquiry = $this->inquiry(['uploads' => [
            ['path' => $path, 'name' => 'proposal.pdf', 'size' => 20480],
        ]]);

        $this->actingAs($this->joey())->get("/admin/inquiries/{$inquiry->id}/files/0")
            ->assertOk()
            ->assertDownload('proposal.pdf');
    }

    public function test_attachments_are_closed_to_anyone_not_signed_in(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('proposal.pdf', 20, 'application/pdf')
            ->store('public-submissions/sponsorship', 'local');

        $inquiry = $this->inquiry(['uploads' => [
            ['path' => $path, 'name' => 'proposal.pdf', 'size' => 20480],
        ]]);

        // No actingAs: a plain visitor is sent to the login screen.
        $this->get("/admin/inquiries/{$inquiry->id}/files/0")->assertRedirect('/login');
    }

    public function test_an_older_upload_stored_as_a_bare_path_still_downloads(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('old-deck.pdf', 10, 'application/pdf')
            ->store('public-submissions/sponsorship', 'local');

        // Submissions recorded before file names were kept hold a plain string.
        $inquiry = $this->inquiry(['uploads' => [$path]]);

        $this->actingAs($this->joey())->get("/admin/inquiries/{$inquiry->id}/files/0")
            ->assertOk()
            ->assertDownload(basename($path));
    }

    public function test_a_missing_attachment_index_is_not_found(): void
    {
        $inquiry = $this->inquiry();

        $this->actingAs($this->joey())->get("/admin/inquiries/{$inquiry->id}/files/3")->assertNotFound();
    }

    public function test_the_multimedia_crew_cannot_read_inquiries(): void
    {
        $inquiry = $this->inquiry();
        $crew = $this->crew();

        $this->actingAs($crew)->get('/admin/inquiries')->assertForbidden();
        $this->actingAs($crew)->get("/admin/inquiries/{$inquiry->id}")->assertForbidden();
    }

    public function test_the_sidebar_counts_unread_inquiries(): void
    {
        $joey = $this->joey();
        $this->inquiry();
        $this->inquiry(['status' => 'closed']);

        // One new, one closed: the badge shows the one still waiting.
        $this->actingAs($joey)->get('/admin')
            ->assertOk()
            ->assertSee('nav-badge', false)
            ->assertSeeInOrder(['Inquiries', '1']);
    }
}
