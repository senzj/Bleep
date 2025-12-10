<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BleepViews extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bleep_id',
        'user_id',
        'session_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
