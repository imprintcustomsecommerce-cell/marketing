<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endorser extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'type', 'contact_number', 'email', 'team_or_group', 'birthday', 'social_media_url', 'group_chat_url', 'status', 'profile', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['birthday' => 'date'];
    }

    /**
     * The next time this birthday comes round, in the shop's timezone.
     *
     * The stored date is the year they were born, so the day and month are what
     * matter. Today counts as the next one — a greeting is not late until the
     * day is over. A 29 February birthday falls back to the 28th in common
     * years rather than silently landing in March.
     */
    public function nextBirthday(): ?CarbonImmutable
    {
        if (! $this->birthday) {
            return null;
        }

        $day = min($this->birthday->day, CarbonImmutable::create(today()->year, $this->birthday->month)->daysInMonth);
        $next = CarbonImmutable::create(today()->year, $this->birthday->month, $day)->startOfDay();

        if ($next->lt(today())) {
            $year = today()->year + 1;
            $day = min($this->birthday->day, CarbonImmutable::create($year, $this->birthday->month)->daysInMonth);
            $next = CarbonImmutable::create($year, $this->birthday->month, $day)->startOfDay();
        }

        return $next;
    }

    /** Whole days until the greeting is due; 0 means today. */
    public function daysUntilBirthday(): ?int
    {
        return $this->nextBirthday()
            ? (int) today()->startOfDay()->diffInDays($this->nextBirthday(), false)
            : null;
    }

    /** The age they are turning, or null when the birth year is not known. */
    public function turningAge(): ?int
    {
        return $this->birthday ? $this->nextBirthday()->year - $this->birthday->year : null;
    }

    /**
     * Everyone with a birthday inside the next `$days` days, soonest first.
     *
     * Filtered in PHP rather than SQL: matching a day and month across a year
     * boundary needs database-specific date functions, and the roster is small.
     *
     * @return \Illuminate\Support\Collection<int,self>
     */
    public static function greetingsDue(int $days = 30)
    {
        return static::query()
            ->whereNotNull('birthday')
            ->where('status', '!=', 'inactive')
            ->get()
            ->filter(fn (self $endorser) => $endorser->daysUntilBirthday() <= $days)
            ->sortBy(fn (self $endorser) => $endorser->daysUntilBirthday())
            ->values();
    }

    public function prKits(): HasMany
    {
        return $this->hasMany(PrKit::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }
}
