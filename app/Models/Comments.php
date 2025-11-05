<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    protected $fillable = [
        'user_id',
        'bleep_id',
        'message',
        'is_anonymous'
    ];

    /**
     * Relation to User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation to Bleep model
     */
    public function bleep()
    {
        return $this->belongsTo(Bleep::class);
    }
}
