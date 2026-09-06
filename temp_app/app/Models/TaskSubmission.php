<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day's board handed to the administrator for checking.
 */
class TaskSubmission extends Model
{
    protected $fillable = ['user_id', 'task_date', 'submitted_at', 'note', 'reviewed_at', 'reviewed_by', 'feedback'];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }
}
