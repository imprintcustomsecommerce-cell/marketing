{{--
    Archive and delete, sitting with Edit on the row they act on.

    Kept as a partial so the table and the narrow-screen cards cannot drift
    apart: one of them would end up without a way to remove an event.
--}}
@unless($event->archived_at)
    <form method="post" action="{{ route('admin.events.archive', $event) }}"
          data-confirm="Archive &quot;{{ $event->name }}&quot;?"
          data-confirm-detail="It stays on file and can be restored at any time."
          data-confirm-action="Archive">
        @csrf @method('patch')
        <button class="link-btn" type="submit">Archive</button>
    </form>
@else
    <form method="post" action="{{ route('admin.events.restore', $event) }}">
        @csrf @method('patch')
        <button class="link-btn" type="submit">Restore</button>
    </form>
@endunless

@if(auth()->user()->isAdmin())
    <form method="post" action="{{ route('admin.events.destroy', $event) }}"
          data-confirm="Delete &quot;{{ $event->name }}&quot; for good?"
                      data-confirm-detail="Its coverage, production tasks, and uploaded files go with it. This cannot be undone. Archive it instead if you only want it out of the way."
                      data-confirm-action="Delete">
        @csrf @method('delete')
        <button class="link-btn danger" type="submit">Delete</button>
    </form>
@endif
