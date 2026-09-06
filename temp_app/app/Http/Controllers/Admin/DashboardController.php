<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $monthInquiries = PublicSubmission::whereBetween('submitted_at', [today()->startOfMonth(), today()->endOfMonth()])->get();
        $handledThisMonth = $monthInquiries->filter(fn (PublicSubmission $inquiry) => $inquiry->handled_at !== null);
        $averageResponseMinutes = $handledThisMonth->avg(fn (PublicSubmission $inquiry) => $inquiry->submitted_at->diffInMinutes($inquiry->handled_at));
        $convertedThisMonth = $monthInquiries->filter(fn (PublicSubmission $inquiry) => $inquiry->converted_event_id || $inquiry->converted_endorser_id)->count();

        return view('admin.dashboard', [
            'eventCount' => Event::count(),
            'endorserCount' => Endorser::count(),
            'newInquiryCount' => PublicSubmission::where('status', 'new_inquiry')->count(),
            'monthlyMetrics' => [
                'received' => $monthInquiries->count(),
                'response' => $averageResponseMinutes === null ? '—' : ($averageResponseMinutes < 60 ? round($averageResponseMinutes).'m' : round($averageResponseMinutes / 60, 1).'h'),
                'conversion' => $monthInquiries->isEmpty() ? '0%' : round(($convertedThisMonth / $monthInquiries->count()) * 100).'%',
                'partnerships' => $monthInquiries->where('type', 'sponsorship_inquiry')->whereNotNull('converted_endorser_id')->count(),
            ],
            'upcomingEvents' => Event::whereDate('event_date', '>=', today())->orderBy('event_date')->limit(6)->get(),
            'recentInquiries' => PublicSubmission::latest('submitted_at')->limit(5)->get(),
            'overdueObligations' => Obligation::with('endorser')
                ->where('status', 'pending')->whereDate('due_date', '<', today())
                ->orderBy('due_date')->limit(5)->get(),
            'upcomingKits' => PrKit::with('endorser')
                ->whereNotIn('status', ['returned', 'cancelled'])
                ->where(fn ($query) => $query->whereDate('delivery_date', '>=', today())->orWhereDate('pickup_date', '>=', today()))
                ->orderByRaw('COALESCE(delivery_date, pickup_date)')->limit(5)->get(),

            // Your own day, so the dashboard opens on work rather than totals.
            'myTasks' => Task::where('user_id', $user->id)->whereDate('task_date', today())->get(),

            // Work handed to the crew that nobody there has picked up. Marketing
            // cannot see the crew's own screens, so this is where they find out
            // a request is still sitting unanswered.
            'awaitingCrew' => Coverage::with('event')
                ->where('stage', CoverageDesk::REQUESTED)
                ->whereHas('event')
                ->get()
                ->sortBy(fn (Coverage $coverage) => $coverage->event->event_date)
                ->take(5)
                ->values(),
            'queuedTasks' => Task::with('raisedBy')
                ->unclaimedFor(User::TEAM_MULTIMEDIA)
                ->orderBy('created_at')
                ->limit(5)
                ->get(),
            'productionEvents' => Event::with(['coverage.shooter', 'coverage.photoEditor', 'coverage.videoEditor', 'coverage.accepter'])
                ->whereNull('archived_at')
                ->whereHas('coverage', fn ($query) => $query->where('stage', CoverageDesk::ACCEPTED))
                ->whereHas('coverage', fn ($query) => $query
                    ->whereNotIn('photo_status', ['posted', 'not_required'])
                    ->orWhereNotIn('video_status', ['posted', 'not_required']))
                ->orderBy('event_date')
                ->limit(5)
                ->get(),

            'attention' => $this->attention($user),
        ]);
    }

    /**
     * The short list of things that will go wrong if nobody looks at them.
     * Anything already clear is left out entirely rather than shown as a zero.
     *
     * @return Collection<int, array{label: string, count: int, url: string, tone: string}>
     */
    private function attention($user): Collection
    {
        $items = collect();

        $newInquiries = PublicSubmission::where('status', 'new_inquiry')->count();
        if ($newInquiries > 0) {
            $items->push([
                'label' => $newInquiries.' '.str('inquiry')->plural($newInquiries).' to read',
                'count' => $newInquiries,
                'url' => route('admin.inquiries.index', ['status' => 'new_inquiry']),
                'tone' => 'info',
            ]);
        }

        $overdue = Obligation::where('status', 'pending')->whereDate('due_date', '<', today())->count();
        if ($overdue > 0) {
            $items->push([
                'label' => $overdue.' overdue '.str('obligation')->plural($overdue),
                'count' => $overdue,
                'url' => route('admin.obligations.index', ['show' => 'overdue']),
                'tone' => 'danger',
            ]);
        }

        // Coverage marketing has asked for that the crew have not answered.
        // Only Joey can open their screen, so everyone else is sent to the
        // events list rather than into a 403.
        $awaitingCrew = Coverage::where('stage', CoverageDesk::REQUESTED)->count();
        if ($awaitingCrew > 0) {
            $items->push([
                'label' => $awaitingCrew.' coverage '.str('request')->plural($awaitingCrew).' with multimedia',
                'count' => $awaitingCrew,
                'url' => $user->canSeeMultimedia()
                    ? route('admin.coverage.index', ['show' => 'requested'])
                    : route('admin.events.index', ['when' => 'all']),
                'tone' => 'info',
            ]);
        }

        // Upcoming events nobody was ever asked to cover — worse than an
        // unanswered request, because it is not on anyone's screen at all.
        $neverSent = Event::whereDate('event_date', '>=', today())
            ->whereDoesntHave('coverage')
            ->count();
        if ($neverSent > 0) {
            $items->push([
                'label' => $neverSent.' upcoming '.str('event')->plural($neverSent).' not sent to the crew',
                'count' => $neverSent,
                'url' => route('admin.events.index', ['when' => 'upcoming']),
                'tone' => 'warn',
            ]);
        }

        $queuedTasks = Task::unclaimedFor(User::TEAM_MULTIMEDIA)->count();
        if ($queuedTasks > 0) {
            $items->push([
                'label' => $queuedTasks.' '.str('task')->plural($queuedTasks).' waiting in the multimedia queue',
                'count' => $queuedTasks,
                'url' => route('admin.tasks.index'),
                'tone' => 'info',
            ]);
        }

        // Taken on by the crew but nobody named to shoot it. The two other
        // states — still unanswered, and never sent — have their own entries
        // above, so the three counts partition the work instead of overlapping.
        $noShooter = Event::whereDate('event_date', '>=', today())
            ->whereHas('coverage', fn ($query) => $query
                ->where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id'))
            ->count();
        if ($noShooter > 0 && $user->canSeeMultimedia()) {
            $items->push([
                'label' => $noShooter.' upcoming '.str('event')->plural($noShooter).' without a shooter',
                'count' => $noShooter,
                'url' => route('admin.coverage.index', ['show' => 'unassigned']),
                'tone' => 'warn',
            ]);
        }

        // The monthly kit rule: who has not been sent one yet this month.
        $withoutKit = Endorser::whereIn('status', ['active', 'pending'])
            ->whereDoesntHave('prKits', fn ($query) => $query
                ->where('purpose', PrKit::PURPOSE_ENDORSER)
                ->whereBetween('delivery_date', [today()->startOfMonth(), today()->endOfMonth()]))
            ->count();
        if ($withoutKit > 0) {
            $items->push([
                'label' => $withoutKit.' '.str('endorser')->plural($withoutKit).' with no kit this month',
                'count' => $withoutKit,
                'url' => route('admin.kit-coverage'),
                'tone' => 'warn',
            ]);
        }

        if ($user->isAdmin()) {
            $boards = TaskSubmission::whereNull('reviewed_at')->count();
            if ($boards > 0) {
                $items->push([
                    'label' => $boards.' '.str('board')->plural($boards).' waiting for your check',
                    'count' => $boards,
                    'url' => route('admin.tasks.index'),
                    'tone' => 'info',
                ]);
            }
        }

        return $items;
    }
}
