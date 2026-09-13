<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\PublicSubmission;
use App\Models\Coverage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const REPORTS = [
        'events' => 'Events',
        'endorsers' => 'Endorsers',
        'inquiries' => 'Inquiries',
        'pr-kits' => 'PR Kits',
        'obligations' => 'Obligations',
    ];

    public function index(): View
    {
        $coverages=Coverage::withoutGlobalScope('live_event')->with('revisions')->get(); $delivered=$coverages->whereNotNull('delivery_sent_at');
        return view('admin.reports.index', ['reports' => self::REPORTS, 'analytics' => [
            'delivery' => $delivered->isEmpty() ? '—' : round($delivered->avg(fn($c)=>$c->event?->event_date?->diffInDays($c->delivery_sent_at) ?? 0),1).' days',
            'overdue' => $coverages->isEmpty() ? '0%' : round($coverages->filter(fn($c)=>($c->photo_due_on?->lt(today())&&!in_array($c->photo_status,['posted','not_required']))||($c->video_due_on?->lt(today())&&!in_array($c->video_status,['posted','not_required'])))->count()/$coverages->count()*100).'%',
            'revisions' => $coverages->isEmpty() ? '0' : round($coverages->avg(fn($c)=>$c->revisions->count()),1),
            'notifications' => PublicSubmission::whereNotNull('client_notified_at')->count(),
        ]]);
    }

    public function download(string $report): StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        [$headers, $rows] = $this->data($report);
        $filename = 'imprint-'.str_replace('-', '_', $report).'-'.today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function data(string $report): array
    {
        return match ($report) {
            'events' => [['ID', 'Event', 'Category', 'Date', 'Venue', 'Organization', 'Status'], Event::orderByDesc('event_date')->get()->map(fn (Event $event) => [$event->id, $event->name, $event->category, $event->event_date?->format('Y-m-d'), $event->venue, $event->organization, $event->status])],
            'endorsers' => [['ID', 'Name', 'Type', 'Status', 'Email', 'Contact number', 'Team or group'], Endorser::orderBy('name')->get()->map(fn (Endorser $endorser) => [$endorser->id, $endorser->name, $endorser->type, $endorser->status, $endorser->email, $endorser->contact_number, $endorser->team_or_group])],
            'inquiries' => [['Reference', 'Type', 'Subject', 'Email', 'Contact number', 'Priority', 'Status', 'Assigned to', 'Follow-up', 'Submitted'], PublicSubmission::with('assignee')->latest('submitted_at')->get()->map(fn (PublicSubmission $inquiry) => [$inquiry->referenceNumber(), $inquiry->type, $inquiry->subject(), data_get($inquiry->data, 'email'), data_get($inquiry->data, 'contact_number'), $inquiry->priority, $inquiry->status, $inquiry->assignee?->name, $inquiry->follow_up_at?->format('Y-m-d H:i'), $inquiry->submitted_at?->format('Y-m-d H:i')])],
            'pr-kits' => [['ID', 'Recipient', 'Purpose', 'Endorser', 'Status', 'Courier', 'Tracking', 'Delivery', 'Pickup'], PrKit::with('endorser')->latest()->get()->map(fn (PrKit $kit) => [$kit->id, $kit->recipient, $kit->purpose, $kit->endorser?->name, $kit->status, $kit->courier, $kit->tracking_number, $kit->delivery_date?->format('Y-m-d'), $kit->pickup_date?->format('Y-m-d')])],
            'obligations' => [['ID', 'Endorser', 'Deliverable', 'Status', 'Due', 'Delivered'], Obligation::with('endorser')->latest()->get()->map(fn (Obligation $obligation) => [$obligation->id, $obligation->endorser?->name, $obligation->title, $obligation->status, $obligation->due_date?->format('Y-m-d'), $obligation->completed_on?->format('Y-m-d')])],
        };
    }
}
