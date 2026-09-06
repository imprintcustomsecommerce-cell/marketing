<?php

namespace App\Models;

use App\Support\PublicPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PublicLink extends Model
{
    protected $fillable = ['resource_type', 'resource_id', 'visible_data', 'is_active', 'expires_at', 'max_submissions', 'submission_count', 'password', 'allow_confirmation', 'allow_change_request'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['visible_data' => 'array', 'is_active' => 'boolean', 'expires_at' => 'datetime', 'allow_confirmation' => 'boolean', 'allow_change_request' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->token ??= (string) Str::uuid();
            if ($link->password && ! str_starts_with($link->password, '$2')) {
                $link->password = Hash::make($link->password);
            }
        });
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PublicSubmission::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && (! $this->expires_at || $this->expires_at->isFuture())
            && (! $this->max_submissions || $this->submission_count < $this->max_submissions);
    }

    public function url(): string
    {
        return app(PublicPortal::class)->url().'/client/event/'.$this->token;
    }
}
