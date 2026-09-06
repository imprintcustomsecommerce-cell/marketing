<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventFileController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip']]);
        $file = $validated['file'];
        $path = $file->store("event-files/{$event->id}", 'local');

        $event->files()->create([
            'uploaded_by' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'File attached to the event.');
    }

    public function download(Event $event, EventFile $file): StreamedResponse
    {
        abort_unless($file->event_id === $event->id && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name);
    }
}
