<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CoverageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EndorserController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventFileController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\KitCoverageController;
use App\Http\Controllers\Admin\MultimediaDashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ObligationController;
use App\Http\Controllers\Admin\PrKitController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TaskBoardController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicInquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware('internal.host')->group(function (): void {
    Route::get('/', fn () => auth()->check() ? redirect()->route(auth()->user()->homeRoute()) : redirect()->route('login'));
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
    });
    Route::middleware(['auth', 'password.changed'])->prefix('admin')->name('admin.')->group(function (): void {
        // Marketing screens are closed to the multimedia crew.
        Route::middleware('marketing.access')->group(function (): void {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/search', SearchController::class)->name('search');
            Route::resource('events', EventController::class)->except(['destroy']);
            Route::get('/events/{event}/summary', [EventController::class, 'summary'])->name('events.summary');
            Route::patch('/events/{event}/archive', [EventController::class, 'archive'])->name('events.archive');
            Route::patch('/events/{event}/restore', [EventController::class, 'restore'])->name('events.restore');
            Route::post('/events/{event}/request-coverage', [EventController::class, 'requestCoverage'])->name('events.request-coverage');
            // Deleting an event takes its coverage, production tasks, and
            // uploaded files with it and cannot be undone, so it is the
            // administrator's call. Archiving stays open to all of marketing.
            Route::delete('/events/{event}', [EventController::class, 'destroy'])
                ->middleware('admin.only')->name('events.destroy');
            Route::resource('endorsers', EndorserController::class)->except(['show', 'destroy']);
            // Deleting takes their PR kits and content obligations with it, so
            // it is the administrator's call. Marking someone inactive is the
            // everyday way to take them off the working roster.
            Route::delete('/endorsers/{endorser}', [EndorserController::class, 'destroy'])
                ->middleware('admin.only')->name('endorsers.destroy');
            Route::resource('pr-kits', PrKitController::class)->except(['show', 'destroy'])->parameters(['pr-kits' => 'prKit']);
            Route::resource('obligations', ObligationController::class)->except(['show', 'destroy']);
            Route::patch('/obligations/{obligation}/status', [ObligationController::class, 'status'])->name('obligations.status');
            Route::get('/calendar', CalendarController::class)->name('calendar');
            Route::get('/kit-coverage', KitCoverageController::class)->name('kit-coverage');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}.csv', [ReportController::class, 'download'])->name('reports.download');

            Route::get('/inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
            Route::get('/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
            Route::put('/inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
            Route::patch('/inquiries/{inquiry}/action', [InquiryController::class, 'quickAction'])->name('inquiries.action');
            Route::post('/inquiries/{inquiry}/contacts', [InquiryController::class, 'addContact'])->name('inquiries.contacts.store');
            Route::post('/inquiries/{inquiry}/convert', [InquiryController::class, 'convert'])->name('inquiries.convert');
            Route::get('/inquiries/{inquiry}/files/{index}', [InquiryController::class, 'download'])->whereNumber('index')->name('inquiries.download');
        });

        // Ticking the pre-event checklist is deliberately outside the marketing
        // group: the crew loading the van are as likely to be the ones marking
        // an item done, and they cannot open the event screen itself.
        Route::patch('/events/{event}/preparation', [EventController::class, 'preparation'])
            ->name('events.preparation');

        // Everyone gets their own board and their own account settings.
        Route::get('/tasks', [TaskBoardController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskBoardController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [TaskBoardController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskBoardController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/tasks/{task}/claim', [TaskBoardController::class, 'claim'])->name('tasks.claim');
        Route::post('/tasks/{task}/release', [TaskBoardController::class, 'release'])->name('tasks.release');
        Route::post('/tasks/carry-over', [TaskBoardController::class, 'carryOver'])->name('tasks.carry-over');
        Route::post('/tasks/submit', [TaskBoardController::class, 'submit'])->name('tasks.submit');
        Route::post('/tasks/submissions/{submission}/review', [TaskBoardController::class, 'review'])->name('tasks.review');

        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
        Route::delete('/account/avatar', [AccountController::class, 'removeAvatar'])->name('account.avatar.destroy');
        Route::get('/notifications', NotificationController::class)->name('notifications');
        Route::view('/guide', 'admin.guide')->name('guide');
        Route::post('/events/{event}/files', [EventFileController::class, 'store'])->name('events.files.store');
        Route::get('/events/{event}/files/{file}', [EventFileController::class, 'download'])->name('events.files.download');

        // Multimedia screens are closed to marketing staff.
        Route::middleware('multimedia.access')->group(function (): void {
            Route::get('/multimedia', MultimediaDashboardController::class)->name('multimedia');
            Route::get('/coverage', [CoverageController::class, 'index'])->name('coverage.index');
            Route::get('/coverage/{event}', [CoverageController::class, 'edit'])->name('coverage.edit');
            Route::put('/coverage/{event}', [CoverageController::class, 'update'])->name('coverage.update');
            Route::post('/coverage/{event}/respond', [CoverageController::class, 'respond'])->name('coverage.respond');
            Route::post('/coverage/{event}/confirm-delivery', [CoverageController::class, 'confirmDelivery'])->name('coverage.confirm-delivery');
            Route::post('/coverage/{event}/revisions', [CoverageController::class, 'addRevision'])->name('coverage.revisions.store');
            Route::patch('/coverage/{event}/revisions/{revision}', [CoverageController::class, 'completeRevision'])->name('coverage.revisions.complete');
        });

        // Managing accounts belongs to the administrator alone.
        Route::middleware('admin.only')->group(function (): void {
            Route::get('/activity', ActivityController::class)->name('activity');
            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::get('/team/{user}/edit', [TeamController::class, 'edit'])->name('team.edit');
            Route::put('/team/{user}', [TeamController::class, 'update'])->name('team.update');
            Route::get('/system', [SystemController::class, 'index'])->name('system.index');
            Route::post('/system/backup', [SystemController::class, 'backup'])->name('system.backup');
            Route::post('/system/verify', [SystemController::class, 'verify'])->name('system.verify');
            Route::get('/system/backups/{file}', [SystemController::class, 'download'])->where('file', 'imprint-hub_[A-Za-z0-9_-]+\\.zip')->name('system.backups.download');
            Route::delete('/system/sessions', [SystemController::class, 'sessions'])->name('system.sessions.destroy');
        });
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});

Route::prefix('client')->middleware(['public.host', 'throttle:30,1'])->group(function (): void {
    Route::get('/', fn () => view('public.home'))->name('client.home');
    Route::get('/track', [PublicInquiryController::class, 'track'])->name('client.track');
    Route::post('/track', [PublicInquiryController::class, 'lookup'])->middleware('throttle:10,1')->name('client.track.lookup');

    Route::get('/event/{token}', [PublicEventController::class, 'show'])->name('client.event.show');
    Route::post('/event/{token}/unlock', [PublicEventController::class, 'unlock'])->name('client.event.unlock');
    Route::post('/event/{token}/confirm', [PublicEventController::class, 'respond'])->middleware('throttle:10,1')->name('client.event.respond');

    Route::get('/function-hall', [PublicInquiryController::class, 'show'])->defaults('type', 'function-hall')->name('client.function-hall');
    Route::post('/function-hall', [PublicInquiryController::class, 'store'])->defaults('type', 'function-hall');
    Route::get('/tambike', [PublicInquiryController::class, 'show'])->defaults('type', 'tambike')->name('client.tambike');
    Route::post('/tambike', [PublicInquiryController::class, 'store'])->defaults('type', 'tambike');
    Route::get('/sponsorship', [PublicInquiryController::class, 'show'])->defaults('type', 'sponsorship')->name('client.sponsorship');
    Route::post('/sponsorship', [PublicInquiryController::class, 'store'])->defaults('type', 'sponsorship');
    Route::get('/external-event', [PublicInquiryController::class, 'show'])->defaults('type', 'external-event')->name('client.external-event');
    Route::post('/external-event', [PublicInquiryController::class, 'store'])->defaults('type', 'external-event');
    Route::get('/inquiry', [PublicInquiryController::class, 'show'])->defaults('type', 'event')->name('client.inquiry');
    Route::post('/inquiry', [PublicInquiryController::class, 'store'])->defaults('type', 'event');
});
