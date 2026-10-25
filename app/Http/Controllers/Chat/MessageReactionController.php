<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\MessageReacted;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\Chat\BroadcastGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    public function __construct(private BroadcastGateway $broadcasts) {}

    public function toggle(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        $conversation = $message->conversation;
        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);
        abort_if($message->trashed(), 422, 'Cannot react to deleted messages.');

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $emoji = $validated['emoji'];

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        // Clients may send the desired state so retries and offline replays are
        // idempotent. Without it we keep the original toggle behaviour.
        $desiredActive = array_key_exists('active', $validated)
            ? (bool) $validated['active']
            : ! ($existing && $existing->emoji === $emoji);

        if ($desiredActive) {
            MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->where('emoji', '!=', $emoji)
                ->delete();

            MessageReaction::query()->updateOrCreate(
                [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'emoji' => $emoji,
                ],
            );

            $action = 'added';
        } else {
            MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->delete();

            $action = 'removed';
        }

        $reactions = MessageReaction::query()
            ->where('message_id', $message->id)
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

        $this->broadcasts->dispatch(new MessageReacted((int) $conversation->id, $payload), toOthers: true);

        return response()->json(['data' => $payload]);
    }
}
