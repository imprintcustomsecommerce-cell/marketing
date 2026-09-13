<?php

namespace App\Support;

use App\Models\Coverage;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class NotificationCenter
{
    /** @return Collection<int, array{title:string,detail:string,url:string,tone:string,count:int}> */
    public function for(User $user): Collection
    {
        $items = collect();

        if ($user->isAdmin()) {
            $this->add($items, TaskSubmission::whereNull('reviewed_at')->count(), 'Boards awaiting review', 'Staff have submitted their daily work for checking.', route('admin.tasks.index'), 'info');
            $backupPath = (string) config('imprint.backup_path');
            $freshBackup = File::isDirectory($backupPath) && collect(File::files($backupPath))->contains(fn ($file) => $file->getMTime() >= now()->subDays(2)->timestamp);
            $this->add($items, $freshBackup ? 0 : 1, 'Backup needs attention', 'No successful local backup was found in the last two days.', route('admin.system.index'), 'danger');
        }

        if ($user->team === User::TEAM_MARKETING) {
            $this->add($items, PublicSubmission::where('status', 'new_inquiry')->count(), 'New public inquiries', 'New client requests are waiting for a response.', route('admin.inquiries.index', ['status' => 'new_inquiry']), 'info');
            $this->add($items, PublicSubmission::whereNotNull('follow_up_at')->where('follow_up_at', '<', now())->whereNotIn('status', ['confirmed', 'declined', 'closed'])->count(), 'Inquiry follow-ups overdue', 'Scheduled client follow-ups need attention.', route('admin.inquiries.index', ['follow_up' => 'overdue']), 'danger');
            $this->add($items, Obligation::where('status', 'pending')->whereDate('due_date', '<', today())->count(), 'Overdue obligations', 'Endorser deliverables have passed their due date.', route('admin.obligations.index', ['show' => 'overdue']), 'danger');
            $this->add($items, Event::whereDate('event_date', '<', today())->whereNotIn('status', ['completed', 'cancelled'])->count(), 'Events need closing', 'Past events are still marked as active work.', route('admin.events.index', ['when' => 'past']), 'warn');
        }

        if ($user->team === User::TEAM_MULTIMEDIA) {
            $this->add($items, Task::unclaimedFor(User::TEAM_MULTIMEDIA)->count(), 'Tasks from Marketing', 'Production tasks are ready to be claimed.', route('admin.tasks.index'), 'info');
            $this->add($items, Coverage::where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id')->count(), 'Coverage requests', 'Marketing is waiting for Multimedia to respond.', route('admin.coverage.index', ['show' => 'requested']), 'warn');
            $this->add($items, Event::whereDate('event_date', '>=', today())->whereHas('coverage', fn ($query) => $query->where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id'))->count(), 'Events without a shooter', 'Accepted coverage still needs an assigned shooter.', route('admin.coverage.index', ['show' => 'unassigned']), 'danger');
            $this->add($items, Task::whereNull('archived_at')->where('for_team', User::TEAM_MULTIMEDIA)->whereDate('task_date', '<', today())->where('status', '!=', 'done')->count(), 'Production tasks overdue', 'Multimedia deadlines have passed and still need action.', route('admin.tasks.index'), 'danger');
            $this->add($items, Coverage::where(fn ($query) => $query->where(fn ($q) => $q->whereDate('photo_due_on', '<', today())->whereNotIn('photo_status', ['posted', 'not_required']))->orWhere(fn ($q) => $q->whereDate('video_due_on', '<', today())->whereNotIn('video_status', ['posted', 'not_required'])))->count(), 'Media deadlines overdue', 'Photo or video deliverables have passed their deadline.', route('admin.coverage.index', ['show' => 'outstanding']), 'danger');
        }

        return $items;
    }

    private function add(Collection $items, int $count, string $title, string $detail, string $url, string $tone): void
    {
        if ($count > 0) {
            $items->push(compact('title', 'detail', 'url', 'tone', 'count'));
        }
    }
}
