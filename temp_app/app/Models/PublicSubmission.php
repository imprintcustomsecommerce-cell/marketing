<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicSubmission extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'uploads' => 'array',
            'submitted_at' => 'datetime',
            'handled_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'client_notified_at' => 'datetime',
        ];
    }

    public function publicLink(): BelongsTo
    {
        return $this->belongsTo(PublicLink::class);
    }

    public function convertedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'converted_event_id');
    }

    public function convertedEndorser(): BelongsTo
    {
        return $this->belongsTo(Endorser::class, 'converted_endorser_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(InquiryContactLog::class)->latest();
    }

    /**
     * The best name the form gave us. Each form asks for it differently, so the
     * list is not left showing "Inquiry" for everything.
     */
    public function subject(): string
    {
        foreach (['event_name', 'name_or_group', 'racer_team_name', 'group_name', 'organization', 'organizer'] as $field) {
            if ($value = data_get($this->data, $field)) {
                return $value;
            }
        }

        return 'Inquiry';
    }

    /**
     * Attachments as {path, name, size}, whichever shape they were stored in.
     * Early submissions recorded a bare path string.
     *
     * @return list<array{path: string, name: string, size: int|null}>
     */
    public function attachments(): array
    {
        return collect($this->uploads ?? [])
            ->map(fn ($upload) => is_array($upload)
                ? [
                    'path' => $upload['path'] ?? '',
                    'name' => $upload['name'] ?? basename($upload['path'] ?? ''),
                    'size' => $upload['size'] ?? null,
                ]
                : ['path' => $upload, 'name' => basename($upload), 'size' => null])
            ->filter(fn (array $upload) => $upload['path'] !== '')
            ->values()
            ->all();
    }

    public function contactName(): ?string
    {
        return data_get($this->data, 'contact_person') ?: data_get($this->data, 'name_or_group') ?: data_get($this->data, 'racer_team_name');
    }

    public function referenceNumber(): string
    {
        $date = ($this->submitted_at ?: $this->created_at ?: now())->format('Ymd');

        return 'IMP-'.$date.'-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
