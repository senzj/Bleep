<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function bleep()
    {
        return $this->belongsTo(Bleep::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
