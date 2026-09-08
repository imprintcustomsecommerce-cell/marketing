<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** What someone may do in the hub. */
    public const ROLES = ['admin' => 'Administrator', 'staff' => 'Staff'];

    /** Which side of the shop they work on. Independent of role. */
    public const TEAM_MARKETING = 'marketing';

    public const TEAM_MULTIMEDIA = 'multimedia';

    public const TEAMS = [self::TEAM_MARKETING => 'Marketing', self::TEAM_MULTIMEDIA => 'Multimedia'];
    public const MULTIMEDIA_SPECIALTIES = ['all' => 'Shooter + photo + video', 'shooter' => 'Shooter', 'photo' => 'Photo editor', 'video' => 'Video editor'];

    /** Public URL of the profile picture, or null when there is none. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset($this->avatar_path) : null;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Who may see the marketing side: marketing themselves, and the
     * administrator. The multimedia crew work only their own screens.
     */
    public function canSeeMarketing(): bool
    {
        return $this->isAdmin() || $this->team === self::TEAM_MARKETING;
    }

    /**
     * Where this account lands after signing in. The crew cannot open the
     * marketing dashboard, so they start on their own board.
     */
    public function homeRoute(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        return $this->team === self::TEAM_MULTIMEDIA ? 'admin.multimedia' : 'admin.dashboard';
    }

    /**
     * Who may see the multimedia side: the crew themselves, and the
     * administrator, who oversees both teams. Marketing staff do not.
     */
    public function canSeeMultimedia(): bool
    {
        return $this->isAdmin() || $this->team === self::TEAM_MULTIMEDIA;
    }

    /**
     * Active multimedia staff — the people who can be assigned to shoot or edit.
     *
     * @param  Builder  $query
     */
    public function scopeMultimedia($query)
    {
        return $query->where('team', self::TEAM_MULTIMEDIA)->where('is_active', true);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'team',
        'avatar_path',
        'is_active',
        'must_change_password', 'last_login_at', 'last_login_ip', 'deadline_reminder_sent_on',
        'multimedia_specialty',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean', 'last_login_at' => 'datetime', 'deadline_reminder_sent_on' => 'date',
        ];
    }
}
