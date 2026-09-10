<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $team, string $role = 'staff', string $name = 'Staff'): User
    {
        return User::create(['name' => $name, 'email' => str($name)->slug().uniqid().'@example.test', 'password' => 'secret1234', 'team' => $team, 'role' => $role, 'is_active' => true]);
    }

    private function event(User $user): Event
    {
        return Event::create([
            'name' => 'Cebu Expo', 'category' => 'tambike', 'organization' => 'Cebu Riders',
            'contact_person' => 'Ana Cruz', 'contact_number' => '09171234567', 'contact_email' => 'ana@example.test',
            'event_date' => today()->addWeek(), 'venue' => 'Cebu Hall', 'status' => 'confirmed', 'created_by' => $user->id,
        ]);
    }

    public function test_each_role_has_the_right_home_screen(): void
    {
        $admin = $this->user(User::TEAM_MARKETING, 'admin', 'Admin');
        $marketing = $this->user(User::TEAM_MARKETING, 'staff', 'Marketing');
        $multimedia = $this->user(User::TEAM_MULTIMEDIA, 'staff', 'Multimedia');

        $this->assertSame('admin.dashboard', $admin->homeRoute());
        $this->assertSame('admin.dashboard', $marketing->homeRoute());
        $this->assertSame('admin.multimedia', $multimedia->homeRoute());
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Operations overview');
        $this->actingAs($multimedia)->get('/admin/multimedia')->assertOk()->assertSee('Multimedia home');
    }

    public function test_notifications_are_scoped_to_the_team(): void
    {
        $marketing = $this->user(User::TEAM_MARKETING, 'staff', 'Marketing');
        $multimedia = $this->user(User::TEAM_MULTIMEDIA, 'staff', 'Multimedia');
        PublicSubmission::create(['type' => 'event_inquiry', 'status' => 'new_inquiry', 'submitted_at' => now()]);
        Task::create(['for_team' => User::TEAM_MULTIMEDIA, 'title' => 'Cut teaser', 'task_date' => today(), 'status' => 'todo', 'created_by' => $marketing->id]);

        $this->actingAs($marketing)->get('/admin/notifications')->assertOk()->assertSee('New public inquiries')->assertDontSee('Tasks from Marketing');
        $this->actingAs($multimedia)->get('/admin/notifications')->assertOk()->assertSee('Tasks from Marketing')->assertDontSee('New public inquiries');
    }

    public function test_event_detail_connects_client_coverage_and_tasks(): void
    {
        $marketing = $this->user(User::TEAM_MARKETING, 'staff', 'Marketing');
        $crew = $this->user(User::TEAM_MULTIMEDIA, 'staff', 'Shooter');
        $event = $this->event($marketing);
        Coverage::create(['event_id' => $event->id, 'stage' => CoverageDesk::ACCEPTED, 'shooter_id' => $crew->id, 'photo_status' => 'editing', 'video_status' => 'not_started']);
        Task::create(['user_id' => $crew->id, 'title' => 'Edit event reel', 'task_date' => today(), 'status' => 'doing', 'event_id' => $event->id, 'created_by' => $marketing->id]);

        $this->actingAs($marketing)->get("/admin/events/{$event->id}")
            ->assertOk()->assertSee('Ana Cruz')->assertSee('Shooter')->assertSee('Edit event reel')->assertSee('Cebu Hall');
    }

    public function test_event_files_stay_private_and_download_for_staff(): void
    {
        Storage::fake('local');
        $marketing = $this->user(User::TEAM_MARKETING, 'staff', 'Marketing');
        $event = $this->event($marketing);

        $this->actingAs($marketing)->post("/admin/events/{$event->id}/files", ['file' => UploadedFile::fake()->create('run-sheet.pdf', 120, 'application/pdf')])->assertRedirect();
        $file = $event->files()->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->get("/admin/events/{$event->id}/files/{$file->id}")->assertOk()->assertDownload('run-sheet.pdf');
        $this->post('/admin/logout');
        $this->get("/admin/events/{$event->id}/files/{$file->id}")->assertRedirect('/login');
    }
}
