<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $fillable = [
        'bleep_id',
        'user_id',
        'session_key',
        'token',
        'shared_on',
        'visits',
    ];

    protected $casts = [
        'shared_on' => 'date',
        'visits' => 'integer',
    ];

    /**
     * Generate a short unique token
     */
    public static function generateToken(): string
    {
        do {
            // Generate 8-character token (first 8 chars of UUID without dashes)
            $token = substr(str_replace('-', '', Str::uuid()->toString()), 0, 8);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    // Relationships to user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relationships to bleep (include soft-deleted)
    public function bleep(): BelongsTo
    {
        return $this->belongsTo(Bleep::class)->withTrashed();
    }
}
