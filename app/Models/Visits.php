<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visits extends Model
{
    // explicit table name (matches your migration)
    protected $table = 'visits';

    // allow mass assignment
    protected $fillable = [
        'ip_address',
        'user_agent',
        'browser',
        'device',
        'platform',
    ];

    // optional: cast timestamps are auto-handled, keep defaults
}
