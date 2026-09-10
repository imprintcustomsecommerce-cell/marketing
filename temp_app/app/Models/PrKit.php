<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PrKit extends Model
{

    protected $fillable = ['reference', 'recipient', 'purpose', 'quantity', 'endorser_id', 'event_id', 'contents', 'courier', 'tracking_number', 'address', 'delivery_date', 'pickup_date', 'status', 'content_quota', 'notes', 'created_by'];

    /**
     * Statuses that mean the kit is in the endorser's hands. The content clock
     * starts here — not when the kit is packed or still in transit.
     */
    public const RECEIVED_STATUSES = ['delivered', 'awaiting_pickup', 'returned'];

    /** A kit sent to a named endorser owes content back. */
    public const PURPOSE_ENDORSER = 'endorser';

    /** Giveaway stock handed out at an event or raffle. Owes nothing. */
    public const PURPOSE_GIVEAWAY = 'giveaway';

    public const PURPOSES = [self::PURPOSE_ENDORSER, self::PURPOSE_GIVEAWAY];

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'pickup_date' => 'date', 'obligations_generated_at' => 'datetime'];
    }

    public function endorser(): BelongsTo
    {
        return $this->belongsTo(Endorser::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class)->orderBy('sequence');
    }

    public function isGiveaway(): bool
    {
        return $this->purpose === self::PURPOSE_GIVEAWAY;
    }

    public function isReceived(): bool
    {
        return in_array($this->status, self::RECEIVED_STATUSES, true);
    }

    /**
     * The calendar month the kit belongs to, used to name and date its content.
     */
    public function period(): ?Carbon
    {
        return $this->delivery_date;
    }

    /**
     * Create the content obligations this kit owes, once it is in the endorser's
     * hands and we know which month it lands in.
     *
     * Idempotent by (kit, sequence): re-saving a kit, or bumping its quota from
     * two to three, tops up the missing rows rather than duplicating the set.
     * Existing rows are never rewritten — staff may have edited a due date or
     * attached proof, and that must survive a later save.
     */
    public function syncContentObligations(): int
    {
        if ($this->isGiveaway() || ! $this->endorser_id || ! $this->delivery_date || ! $this->isReceived()) {
            return 0;
        }

        $existing = $this->obligations()->pluck('sequence')->filter()->all();
        $due = $this->delivery_date->copy()->endOfMonth();
        $month = $this->delivery_date->format('F Y');
        $created = 0;

        for ($sequence = 1; $sequence <= $this->content_quota; $sequence++) {
            if (in_array($sequence, $existing, true)) {
                continue;
            }

            $this->obligations()->create([
                'endorser_id' => $this->endorser_id,
                'event_id' => $this->event_id,
                'sequence' => $sequence,
                'title' => "Content {$sequence} of {$this->content_quota} · {$month}",
                'type' => 'content_video',
                'description' => 'Video featuring the '.$month.' PR kit'.($this->reference ? " ({$this->reference})" : '').'.',
                'due_date' => $due,
                'status' => 'pending',
                'created_by' => $this->created_by,
            ]);

            $created++;
        }

        if ($created > 0) {
            $this->forceFill(['obligations_generated_at' => now()])->saveQuietly();
        }

        return $created;
    }

    /**
     * What has to happen to this kit next, and whether it is late.
     *
     * @return array{label: string, date: ?Carbon, late: bool}
     */
    public function nextStep(): array
    {
        if ($this->status === 'cancelled') {
            return ['label' => 'Cancelled', 'date' => null, 'late' => false];
        }

        if ($this->status === 'returned') {
            return ['label' => 'Back on the shelf', 'date' => $this->pickup_date, 'late' => false];
        }

        // Still on its way out: the delivery is the next thing to happen.
        if (! $this->isReceived()) {
            return [
                'label' => $this->delivery_date ? 'Deliver' : 'Set a delivery date',
                'date' => $this->delivery_date,
                'late' => $this->delivery_date?->isBefore(today()) ?? false,
            ];
        }

        // Delivered, and something is expected back.
        if ($this->pickup_date) {
            return [
                'label' => 'Pick up',
                'date' => $this->pickup_date,
                'late' => $this->pickup_date->isBefore(today()),
            ];
        }

        return ['label' => 'Delivered', 'date' => $this->delivery_date, 'late' => false];
    }

    /**
     * How much of the kit's content requirement has actually been delivered.
     *
     * @return array{done: int, quota: int}
     */
    public function contentProgress(): array
    {
        if ($this->isGiveaway()) {
            return ['done' => 0, 'quota' => 0];
        }

        return [
            'done' => $this->obligations->whereIn('status', ['completed', 'waived'])->count(),
            'quota' => $this->content_quota,
        ];
    }
}
