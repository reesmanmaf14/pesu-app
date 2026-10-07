<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/** Built-in categories (user_id = NULL) are read-only; a user's own categories are private to them. */
class CategoryPolicy
{
    public function view(User $user, Category $category): bool
    {
        return ! $category->isCustom() || $this->owns($user, $category);
    }

    /** Adding a word to a category: any category the user can see. */
    public function addTile(User $user, Category $category): bool
    {
        return $this->view($user, $category);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->owns($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->owns($user, $category);
    }

    private function owns(User $user, Category $category): bool
    {
        return $category->isCustom() && (int) $category->user_id === (int) $user->id;
    }
}
