<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** Word kinds a user may pick for their own category (labels shown in the category dialog). */
    public const USER_KINDS = ['noun', 'person', 'place', 'verb', 'feel', 'adj', 'phrase'];

    protected $fillable = ['parent_id', 'slug', 'emoji', 'name_en', 'name_ta', 'default_kind', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Subcategories shown inside this category (Food & drink → Fruits, Drinks, …). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    /** Built-in categories plus the ones this user made. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    /** Compact shape consumed by resources/js/aac. $tiles are this category's visible tiles. */
    public function toAac(iterable $tiles = []): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'parent' => $this->parent_id,
            'e' => $this->emoji,
            'en' => $this->name_en,
            'ta' => $this->name_ta,
            'k' => $this->default_kind,
            'custom' => $this->isCustom(),
            'tiles' => collect($tiles)->map(fn (Tile $t) => $t->toAac())->values(),
        ];
    }
}
