<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Obligation extends Model
{

    protected $fillable = ['endorser_id', 'event_id', 'pr_kit_id', 'sequence', 'title', 'type', 'description', 'due_date', 'completed_on', 'proof_url', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_on' => 'date'];
    }

    public function endorser(): BelongsTo
    {
        return $this->belongsTo(Endorser::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function prKit(): BelongsTo
    {
        return $this->belongsTo(PrKit::class);
    }

    /**
     * Past its due date and still not delivered. Kept out of the stored status
     * so a row cannot go stale simply because nobody opened the page.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isBefore(today());
    }
}
