<?php

namespace Tests\Feature;

use App\Models\PublicSubmission;
use App\Models\User;
use App\Support\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryWorkflowAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inquiry_can_be_prioritized_assigned_and_scheduled_for_follow_up(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->create(['role' => 'staff', 'team' => User::TEAM_MARKETING]);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'reviewing',
            'priority' => 'urgent',
            'assigned_to' => $staff->id,
            'follow_up_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'internal_notes' => 'Waiting on the proposal.',
        ])->assertRedirect();

        $inquiry->refresh();
        $this->assertSame('urgent', $inquiry->priority);
        $this->assertSame($staff->id, $inquiry->assigned_to);
        $this->assertSame('reviewing', $inquiry->status);
        $this->assertTrue(app(NotificationCenter::class)->for($admin)->contains('title', 'Inquiry follow-ups overdue'));
    }

    public function test_contact_activity_and_response_templates_appear_on_the_timeline(): void
    {
        $admin = User::factory()->create();
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->post(route('admin.inquiries.contacts.store', $inquiry), [
            'type' => 'call',
            'note' => 'Client confirmed the expected guest count.',
        ])->assertRedirect();

        $this->assertDatabaseHas('inquiry_contact_logs', ['public_submission_id' => $inquiry->id, 'type' => 'call']);
        $this->actingAs($admin)->get(route('admin.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Contact timeline')
            ->assertSee('Client confirmed the expected guest count.')
            ->assertSee('Response templates')
            ->assertSee('Partnership accepted');
    }

    public function test_all_workspace_reports_download_as_csv(): void
    {
        $admin = User::factory()->create();
        $this->inquiry();

        foreach (['events', 'endorsers', 'inquiries', 'pr-kits', 'obligations'] as $report) {
            $response = $this->actingAs($admin)->get(route('admin.reports.download', $report));
            $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
        }

        $this->actingAs($admin)->get('/admin/reports/not-real.csv')->assertNotFound();
    }

    private function inquiry(): PublicSubmission
    {
        return PublicSubmission::create([
            'type' => 'event_inquiry',
            'status' => 'new_inquiry',
            'data' => ['event_name' => 'City Ride', 'email' => 'ride@example.test', 'contact_number' => '09171234567'],
            'submitted_at' => now(),
        ]);
    }
}
