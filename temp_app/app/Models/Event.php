<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{

    protected $fillable = ['name', 'event_type', 'event_category', 'category', 'shooters_needed', 'photo_editors_needed', 'video_editors_needed', 'organization', 'contact_person', 'contact_number', 'contact_email', 'event_date', 'start_time', 'end_time', 'venue', 'booth_size', 'venue_type', 'group_chat_url', 'estimated_pax', 'deal_type', 'cash_amount', 'exdeal_amount', 'ingress_date', 'egress_date', 'ingress_time', 'egress_time', 'duration_days', 'status', 'public_summary', 'notes', 'preparation', 'preparation_done', 'custom_preparation', 'created_by', 'archived_at'];

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

    /**
     * Events fit to show on the website calendar.
     *
     * Every event goes on the website now; there is no tick to forget. Two
     * things still hold one back, and both are decisions already made elsewhere:
     * archiving it, and cancelling it — a cancelled date left on a public
     * calendar sends customers to a closed venue.
     *
     * Archiving is therefore the way to keep a booking off the website.
     */
    // The is_public column is still on the table and deliberately not listed
    // above: every event is published now, so filling or casting it would give
    // a dead flag the appearance of meaning something.
    public function scopePubliclyListed($query)
    {
        return $query->whereNull('archived_at')
            ->where('status', '!=', 'cancelled');
    }

    /**
     * The event as the website sees it.
     *
     * A whitelist, not a filter: everything customers get is named here, so a
     * column added later cannot quietly find its way onto the storefront.
     *
     * @return array<string,mixed>
     */
    public function toPublicCalendarEntry(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name,
            'date' => $this->event_date->toDateString(),
            'end_date' => $this->duration_days > 1
                ? $this->event_date->copy()->addDays($this->duration_days - 1)->toDateString()
                : $this->event_date->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'venue' => $this->venue,
            'organization' => $this->organization,
            'type' => $this->event_type,
            'type_label' => $this->eventTypeLabel(),
            'summary' => $this->public_summary,
        ];
    }

    public const VENUE_TYPES = ['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'both' => 'Indoor and outdoor'];

    /** Ex-deal is settled in product or exposure; cash carries an amount. */
    public const DEAL_TYPES = ['exdeal' => 'Ex-deal', 'cash' => 'Full cash'];

    /**
     * What a booth might need before the van leaves.
     *
     * The checklist runs in two stages. Booking the event picks which of these
     * apply — a hall booking needs no tarpaulin — and those land in
     * `preparation`. Ticking them off later as they are sorted fills
     * `preparation_done`. Kept here rather than in the database so the wording
     * can change without a migration.
     */
    public const PREPARATION = [
        'booth_confirmed' => 'Booth space confirmed with the organiser',
        'tent' => 'Tent, tables, and chairs',
        'tarpaulin' => 'Tarpaulin and signage',
        'stock' => 'Product stock and display units',
        'flyers' => 'Flyers, cards, and price lists',
        'giveaways' => 'Giveaways and raffle items',
        'power' => 'Power source and extension cords',
        'sound' => 'Sound system',
        'transport' => 'Vehicle and load-out plan',
        'crew' => 'Crew roster and call times',
        'permits' => 'Permits and organiser paperwork',
        'payment' => 'Payment or ex-deal terms agreed in writing',
    ];

    public function venueTypeLabel(): ?string
    {
        return self::VENUE_TYPES[$this->venue_type] ?? null;
    }

    public function dealTypeLabel(): ?string
    {
        return self::DEAL_TYPES[$this->deal_type] ?? null;
    }

    /**
     * Items added by hand for this event only, as a list of {label, done}.
     *
     * @return array<int,array{label:string,done:bool}>
     */
    public function customPreparation(): array
    {
        return array_values(array_filter(
            $this->custom_preparation ?? [],
            fn ($item) => is_array($item) && filled($item['label'] ?? null),
        ));
    }

    /**
     * The standing items this event needs, as key => label, in list order.
     *
     * @return array<string,string>
     */
    public function preparationNeeded(): array
    {
        return array_intersect_key(self::PREPARATION, array_flip($this->preparation ?? []));
    }

    public function isPreparationItemDone(string $key): bool
    {
        return in_array($key, $this->preparation_done ?? [], true);
    }

    /** Items sorted, out of the ones this event asked for. */
    public function preparationDone(): int
    {
        $standing = count(array_intersect(array_keys($this->preparationNeeded()), $this->preparation_done ?? []));
        $custom = count(array_filter($this->customPreparation(), fn (array $item) => ! empty($item['done'])));

        return $standing + $custom;
    }

    public function preparationTotal(): int
    {
        return count($this->preparationNeeded()) + count($this->customPreparation());
    }

    /** Nothing was asked for, so there is no checklist to show. */
    public function hasPreparation(): bool
    {
        return $this->preparationTotal() > 0;
    }

    public function isPreparationComplete(): bool
    {
        return $this->hasPreparation() && $this->preparationDone() === $this->preparationTotal();
    }

    /** How the event is run — the field marketing actually picks on the form. */
    public const EVENT_TYPES = [
        'in_house' => 'IN-HOUSE',
        'outside_event' => 'OUTSIDE EVENT',
        'tambike' => 'TAMBIKE',
    ];

    /** What the event is about. */
    public const EVENT_CATEGORIES = [
        'motorcycle' => 'Motorcycle',
        'automotive' => 'Automotive',
        'car' => 'Car',
        'others' => 'Others',
    ];

    public function eventTypeLabel(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? 'Unset';
    }

    public function eventCategoryLabel(): string
    {
        return self::EVENT_CATEGORIES[$this->event_category] ?? 'Uncategorised';
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date', 'ingress_date' => 'date', 'egress_date' => 'date',
            'archived_at' => 'datetime', 'preparation' => 'array', 'preparation_done' => 'array', 'custom_preparation' => 'array',
            'cash_amount' => 'decimal:2', 'exdeal_amount' => 'decimal:2',
        ];
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



