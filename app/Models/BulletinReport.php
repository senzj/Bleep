<?php

namespace App\Models;

use App\Enums\BulletinReportReason;
use Database\Factories\BulletinReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinReport extends Model
{
    /** @use HasFactory<BulletinReportFactory> */
    use HasFactory;

    /**
     * The internal id and visitor hash must never reach a public response.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'visitor_hash',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bulletin_post_id',
        'reason',
        'visitor_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => BulletinReportReason::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BulletinPost::class, 'bulletin_post_id');
    }
}
