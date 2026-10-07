<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const AAC_DEFAULTS = [
        'lang' => 'en',          // 'en' or 'ta'
        'show_both' => true,     // show the other language under each picture
        'speak_each' => true,    // speak each picture as it is tapped
        'rate' => 0.9,           // speech speed
        'cols' => 5,             // pictures per row
        'voice_en' => null,      // browser voiceURI, null = automatic
        'voice_ta' => null,
        'log_usage' => true,     // keep a history of spoken sentences for the Activity page
    ];

    public const ROLE_PARENT = 'parent';

    public const ROLE_THERAPIST = 'therapist';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    // role and status are deliberately not fillable: no form can make someone a therapist or approve them.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aac_settings' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The database cascade removes their words; this removes the photos and voice recordings too.
        static::deleting(fn (User $user) => $user->tiles()->each(fn (Tile $tile) => $tile->deleteFiles()));
    }

    public function aacSettings(): array
    {
        return array_merge(self::AAC_DEFAULTS, $this->aac_settings ?? []);
    }

    public function isTherapist(): bool
    {
        return $this->role === self::ROLE_THERAPIST;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /** The therapist who last approved or rejected this account. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reviewed_by');
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function savedPhrases(): HasMany
    {
        return $this->hasMany(SavedPhrase::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }
}
