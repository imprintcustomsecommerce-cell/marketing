<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endorser extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'type', 'contact_number', 'email', 'team_or_group', 'social_media_url', 'group_chat_url', 'status', 'profile', 'notes', 'created_by'];

    public function prKits(): HasMany
    {
        return $this->hasMany(PrKit::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }
}
