<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'media_path',
        'media_type',
        'media_kind',
        'media_duration',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
