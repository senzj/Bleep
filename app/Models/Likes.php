<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Likes extends Model
{
    protected $fillable = ['user_id', 'bleep_id'];

    /**
     * Relation to User model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation to Bleep model
     */
    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class);
    }
}
