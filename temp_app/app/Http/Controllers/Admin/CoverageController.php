<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coverage;
use App\Models\Event;
use App\Models\User;
use App\Models\CoverageRevision;
use App\Support\CoverageDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoverageController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('show', 'all');

        // Every event has a coverage row conceptually, whether or not one has
        // been filled in, so the list is driven by events and left-joined to
        // the log. An event nobody has touched still needs to be visible.
        $events = Event::with(['coverage.shooter', 'coverage.photoEditor', 'coverage.videoEditor'])
            ->when($filter === 'requested', fn ($query) => $query->whereHas('coverage', fn ($q) => $q->where('stage', CoverageDesk::REQUESTED)))
            // Taken on, but nobody named to shoot it. Deliberately not the same
            // as "has no shooter row": a job still sitting in the new-request
            // queue, or one never sent at all, is a different problem with a
            // different answer, and each has its own place to be dealt with.
            ->when($filter === 'unassigned', fn ($query) => $query->whereHas('coverage', fn ($q) => $q
                ->where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id')))
            ->when($filter === 'outstanding', fn ($query) => $query->where(fn ($q) => $q
                ->whereDoesntHave('coverage')
                ->orWhereHas('coverage', fn ($c) => $c
                    ->whereNotIn('photo_status', ['posted', 'not_required'])
                    ->orWhereNotIn('video_status', ['posted', 'not_required']))))
            ->latest('event_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.coverage.index', [
            'events' => $events,
            'filter' => $filter,
            'unassignedCount' => Event::whereDate('event_date', '>=', today())
                ->whereHas('coverage', fn ($q) => $q
                    ->where('stage', CoverageDesk::ACCEPTED)->whereNull('shooter_id'))
                ->count(),
            'requestedCount' => Coverage::where('stage', CoverageDesk::REQUESTED)->count(),
        ]);
    }

    public function edit(Event $event): View
    {
        $event->load(['files.uploader', 'coverage.revisions.creator', 'coverage.deliverySender']);
        $loads = Coverage::where('stage', CoverageDesk::ACCEPTED)->get()->flatMap(fn (Coverage $item) => [$item->shooter_id, $item->photo_editor_id, $item->video_editor_id])->filter()->countBy();
        return view('admin.coverage.form', [
            'event' => $event,
            'coverage' => $event->coverage ?: new Coverage(['event_id' => $event->id]),
            'crew' => User::multimedia()->orderBy('name')->pluck('name', 'id'),
            'crewLoads' => $loads,
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validated($request);

        $event->coverage()->updateOrCreate(
            ['event_id' => $event->id],
            $data + ['created_by' => $request->user()->id],
        );

        $coverage = $event->coverage()->first();
        if ($coverage && count(array_unique($coverage->checklist ?? [])) === count(Coverage::CHECKLIST) && $coverage->isComplete()) {
            $coverage->update(['stage' => CoverageDesk::COMPLETED]);
        } elseif ($coverage?->stage === CoverageDesk::COMPLETED) {
            $coverage->update(['stage' => CoverageDesk::ACCEPTED]);
        }

        $assigned=collect([$coverage->shooter_id,$coverage->photo_editor_id,$coverage->video_editor_id])->filter()->unique();
        $conflicts=Coverage::whereKeyNot($coverage->id)->whereIn('stage',[CoverageDesk::ACCEPTED,CoverageDesk::REQUESTED])->whereHas('event',fn($q)=>$q->whereDate('event_date',$event->event_date))->where(fn($q)=>$q->whereIn('shooter_id',$assigned)->orWhereIn('photo_editor_id',$assigned)->orWhereIn('video_editor_id',$assigned))->count();
        $redirect=redirect()->route('admin.coverage.index')->with('success', "Coverage updated for {$event->name}.");
        if($conflicts) $redirect->with('warning','Workload warning: an assigned crew member is also scheduled on another event that day.');
        return $redirect;
    }

    public function confirmDelivery(Request $request, Event $event): RedirectResponse
    {
        $coverage = $event->coverage;
        abort_unless($coverage && $coverage->delivery_url, 422, 'Add the final media delivery link first.');

        $coverage->update(['delivery_sent_at' => now(), 'delivery_sent_by' => $request->user()->id]);

        return back()->with('success', 'Final media delivery recorded.');
    }
    public function addRevision(Request $request, Event $event): RedirectResponse
    {
        $coverage = $event->coverage()->firstOrFail();
        $data = $request->validate(['notes'=>['required','string','max:5000'],'due_on'=>['nullable','date']]);
        $coverage->revisions()->create($data + ['round'=>(int)$coverage->revisions()->max('round')+1,'created_by'=>$request->user()->id]);
        if ($coverage->stage === CoverageDesk::COMPLETED) $coverage->update(['stage'=>CoverageDesk::ACCEPTED]);
        return back()->with('success','Revision round added.');
    }
    public function completeRevision(Request $request, Event $event, CoverageRevision $revision): RedirectResponse
    {
        abort_unless($revision->coverage_id === $event->coverage?->id, 404);
        $revision->update(['completed_at'=>$revision->completed_at ? null : now()]);
        return back()->with('success','Revision updated.');
    }

    /**
     * The crew take the job on, or say it does not need covering. Either way it
     * leaves the new-work queue, so nothing sits there unanswered.
     */
    public function respond(Request $request, Event $event, CoverageDesk $desk): RedirectResponse
    {
        $answer = $request->validate([
            'answer' => ['required', Rule::in(['accept', 'decline'])],
            'specialty' => ['nullable', Rule::in(['shooter', 'photo', 'video'])],
        ])['answer'];
        $specialty = $request->input('specialty') ?: ($request->user()->multimedia_specialty === 'photo' ? 'photo' : ($request->user()->multimedia_specialty === 'video' ? 'video' : 'shooter'));
        abort_unless($request->user()->isAdmin() || $specialty !== null, 422, 'Choose the multimedia role you want to take.');

        // An event with no coverage row predates the handoff, so opening one
        // here is what accepting it means.
        $coverage = $event->coverage ?: $desk->request($event, $request->user());

        $answer === 'accept'
            ? $desk->accept($coverage, $request->user(), ! $request->user()->isAdmin(), $specialty)
            : $desk->decline($coverage, $request->user());

        return back()->with('success', $answer === 'accept'
            ? "Coverage accepted for {$event->name}."
            : "{$event->name} marked as not needing coverage.");
    }

    private function validated(Request $request): array
    {
        $crewIds = User::multimedia()->pluck('id')->all();

        $validated = $request->validate([
            'shooter_id' => ['nullable', Rule::in($crewIds)],
            'photo_editor_id' => ['nullable', Rule::in($crewIds)],
            'video_editor_id' => ['nullable', Rule::in($crewIds)],
            'photo_status' => ['required', Rule::in(array_keys(Coverage::STATUSES))],
            'video_status' => ['required', Rule::in(array_keys(Coverage::STATUSES))],
            'photo_posted_on' => ['nullable', 'date'],
            'video_posted_on' => ['nullable', 'date'],
            'photo_due_on' => ['nullable', 'date'],
            'video_due_on' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['string', Rule::in(array_keys(Coverage::CHECKLIST))],
            'delivery_url' => ['nullable', 'url', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'shooter_id.in' => 'The shooter must be an active member of the multimedia team.',
            'photo_editor_id.in' => 'The photo editor must be an active member of the multimedia team.',
            'video_editor_id.in' => 'The video editor must be an active member of the multimedia team.',
        ]);

        $validated['checklist'] = array_values($validated['checklist'] ?? []);

        // A posting date only means something once the work is actually posted.
        foreach (['photo', 'video'] as $medium) {
            if ($validated["{$medium}_status"] !== 'posted') {
                $validated["{$medium}_posted_on"] = null;
            }
        }

        return $validated;
    }
}
