<?php

namespace App\Models;

use App\Traits\HasAnonymousName;
use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    use HasAnonymousName;

    protected $fillable = [
        'user_id',
        'bleep_id',
        'message',
        'is_anonymous',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
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
