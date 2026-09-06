<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'category', 'organization', 'contact_person', 'contact_number', 'contact_email', 'event_date', 'start_time', 'end_time', 'venue', 'group_chat_url', 'estimated_pax', 'status', 'notes', 'created_by', 'archived_at'];

    /** The three kinds of event the shop runs. */
    public const CATEGORIES = [
        'tambike' => 'Tambike Event',
        'function_hall' => 'Function Hall Rental',
        'external_sponsorship' => 'External Event Sponsorship',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Uncategorised';
    }

    protected function casts(): array
    {
        return ['event_date' => 'date', 'archived_at' => 'datetime'];
    }

    public function coverage(): HasOne
    {
        return $this->hasOne(Coverage::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(EventFile::class);
    }

    public function publicLinks(): HasMany
    {
        return $this->hasMany(PublicLink::class, 'resource_id')->where('resource_type', 'event');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
