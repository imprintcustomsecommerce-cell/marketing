<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryContactLog extends Model
{
    protected $fillable = ['public_submission_id', 'user_id', 'type', 'note'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(PublicSubmission::class, 'public_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
