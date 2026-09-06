<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coverage;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use App\Support\NotificationCenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MultimediaDashboardController extends Controller
{
    public function __invoke(Request $request, NotificationCenter $notifications): View
    {
        return view('admin.multimedia-dashboard', [
            'notifications' => $notifications->for($request->user()),
            'queuedTasks' => Task::with(['event', 'raisedBy'])->unclaimedFor(User::TEAM_MULTIMEDIA)->oldest()->limit(6)->get(),
            'requestedCoverage' => Coverage::with('event')->where('stage', CoverageDesk::REQUESTED)->oldest('requested_at')->limit(6)->get(),
            'myTasks' => Task::with('event')->where('user_id', $request->user()->id)->whereDate('task_date', today())->get(),
            'upcomingCoverage' => Event::with(['coverage.shooter', 'coverage.photoEditor', 'coverage.videoEditor'])
                ->whereDate('event_date', '>=', today())->whereHas('coverage', fn ($query) => $query->where('stage', CoverageDesk::ACCEPTED))
                ->orderBy('event_date')->limit(6)->get(),
        ]);
    }
}
