<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repost extends Model
{
    protected $fillable = [
        'bleep_id',
        'user_id'
    ];

    // Relationships to user
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Relationships to bleep
    public function bleep() {
        return $this->belongsTo(Bleep::class);
    }
}
