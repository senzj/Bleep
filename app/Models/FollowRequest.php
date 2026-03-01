<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowRequest extends Model
{
    protected $fillable = [
        'requester_id',
        'target_id',
        'status',
    ];

    /**
     * Get the user who sent the follow request
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user who received the follow request
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
