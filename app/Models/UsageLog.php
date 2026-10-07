<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    protected $fillable = ['sentence', 'lang'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
