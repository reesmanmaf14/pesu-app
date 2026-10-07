<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPhrase extends Model
{
    protected $fillable = ['text'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
