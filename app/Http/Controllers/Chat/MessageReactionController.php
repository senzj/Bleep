<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\MessageReacted;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    public function toggle(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        $conversation = $message->conversation;
        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);
        abort_if($message->trashed(), 422, 'Cannot react to deleted messages.');

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        $emoji = $validated['emoji'];

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
            $action = 'removed';
        } else {
            if ($existing) {
                $existing->update(['emoji' => $emoji]);
            } else {
                MessageReaction::create([
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'emoji' => $emoji,
                ]);
            }
            $action = 'added';
        }

        $reactions = MessageReaction::where('message_id', $message->id)
            ->with('user:id,username,dname,profile_picture')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'emoji' => $r->emoji,
                'user' => [
                    'id' => $r->user?->id,
                    'username' => $r->user?->username,
                    'dname' => $r->user?->dname,
                    'profile_picture_url' => $r->user?->profile_picture_url,
                ],
            ])
            ->values()
            ->all();

        $payload = [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'reactions' => $reactions,
            'action' => $action,
            'actor_id' => $user->id,
            'emoji' => $emoji,
        ];

        broadcast(new MessageReacted((int) $conversation->id, $payload))->toOthers();

        return response()->json(['data' => $payload]);
    }
}
