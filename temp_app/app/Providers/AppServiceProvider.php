<?php

namespace App\Providers;

use App\Models\Coverage;
use App\Models\PublicSubmission;
use App\Models\Task;
use App\Models\User;
use App\Support\CoverageDesk;
use App\Support\NotificationCenter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The unread count sits in the sidebar on every admin screen, so it is
        // shared with the layout rather than passed by each controller. Only
        // counted for people who can actually open the inquiries screen.
        View::composer('layouts.admin', function ($view): void {
            $user = auth()->user();

            $view->with('notificationBadge', $user ? app(NotificationCenter::class)->for($user)->sum('count') : 0);

            $view->with(
                'newInquiryBadge',
                $user?->canSeeMarketing() ? PublicSubmission::where('status', 'new_inquiry')->count() : 0,
            );

            // The crew's own unread count: coverage marketing has asked for and
            // nobody has picked up. Sits beside Event Coverage in the sidebar so
            // a new job is visible from whatever screen they are on.
            $view->with(
                'newCoverageBadge',
                $user?->team === User::TEAM_MULTIMEDIA ? Coverage::where('stage', CoverageDesk::REQUESTED)->count() : 0,
            );

            // Tasks marketing has raised for the crew that nobody has taken.
            // Marketing raise them but do not do them, so they get no badge.
            $view->with(
                'queuedTaskBadge',
                $user?->team === User::TEAM_MULTIMEDIA
                    ? Task::unclaimedFor(User::TEAM_MULTIMEDIA)->count()
                    : 0,
            );
        });
    }
}
