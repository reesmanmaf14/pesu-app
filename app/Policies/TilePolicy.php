<?php

namespace App\Policies;

use App\Models\Tile;
use App\Models\User;

/**
 * Built-in tiles (user_id = NULL) are read-only; a user's own words are private to them.
 * The one exception: the therapist records the Tamil voice of built-in words, which every approved user hears.
 */
class TilePolicy
{
    public function update(User $user, Tile $tile): bool
    {
        return $this->owns($user, $tile);
    }

    public function delete(User $user, Tile $tile): bool
    {
        return $this->owns($user, $tile);
    }

    /** Add, replace or remove a word's Tamil recording (audio only; the word itself never changes). */
    public function record(User $user, Tile $tile): bool
    {
        return $tile->isShared()
            ? $user->isTherapist() && $user->isApproved()
            : $this->owns($user, $tile);
    }

    /** Shared recordings are for every approved user; a user's own recordings only for them. */
    public function listen(User $user, Tile $tile): bool
    {
        return $tile->isShared()
            ? $user->isApproved()
            : $this->owns($user, $tile);
    }

    private function owns(User $user, Tile $tile): bool
    {
        return $tile->isCustom() && (int) $tile->user_id === (int) $user->id;
    }
}
