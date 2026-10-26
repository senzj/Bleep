<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authenticated profile view, deduplicated per viewer per day.
 *
 * Rows are only written for owners who opted in to visit notifications, so
 * users who never asked for them keep no log of who looked at their profile.
 */
class ProfileVisit extends Model
{
    protected $fillable = [
        'profile_user_id',
        'visitor_id',
        'visited_on',
    ];

    protected $casts = [
        'visited_on' => 'date',
    ];

    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }
}
