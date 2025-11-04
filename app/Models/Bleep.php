<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bleep extends Model
{
    protected $fillable = [
        'message',
    ];

    /**
     * Relation to User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
