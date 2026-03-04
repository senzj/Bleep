<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('conversation-online.{conversationId}', function ($user, $conversationId) {
    $isParticipant = Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();

    if (! $isParticipant) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'dname' => $user->dname,
    ];
});
