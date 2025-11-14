<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reports extends Model
{
    protected $fillable = [
        'bleep_id',
        'reporter_id',
        'reason',
        'category',
        'status',
        'action_taken',
        'reviewed_at',
        'reviewed_by',
        'notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
