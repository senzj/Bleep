<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinReaction extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = [
        'visitor_hash',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bulletin_post_id',
        'reaction_type',
        'visitor_hash',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BulletinPost::class, 'bulletin_post_id');
    }
}
