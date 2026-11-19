<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_group',
        'invite_only',
        'invite_token',
        'creator_id',
        'chat_theme',
        'chat_bg',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'invite_only' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot([
                'role',
                'last_read_at',
                'left_at',
                'joined_at',
                'is_muted',
                'muted_until',
                'is_pinned',
                'is_archived',
                'snoozed_until',
            ])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The most recent message in the conversation, including soft-deleted messages.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->withTrashed()->latestOfMany();
    }

    /**
     * Join requests raised by an invite link or a direct add on an
     * invite-only group. Pending rows are invisible to the requested member.
     */
    public function invites(): HasMany
    {
        return $this->hasMany(ConversationInvite::class);
    }
}
