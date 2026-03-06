<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDelivered;
use App\Events\Chat\MessageRead;
use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Services\Chat\ChatMessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
            'body' => ['nullable', 'string', 'max:5000'],
            'media_path' => ['nullable', 'string', 'max:2048'],
            'media_type' => ['nullable', 'string', 'max:255'],
            'media_kind' => ['nullable', 'in:none,media,audio,voice'],
            'media_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $body = trim((string) ($validated['body'] ?? ''));
        $mediaPath = $validated['media_path'] ?? null;

        if ($body === '' && ! $mediaPath) {
            return response()->json([
                'message' => 'Message body or media is required.',
            ], 422);
        }

        if (! empty($validated['client_uuid'])) {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_id', $user->id)
                ->where('client_uuid', $validated['client_uuid'])
                ->with(['sender:id,username,dname,profile_picture', 'deliveries.user:id,username,dname', 'conversation.participants:id,username,dname'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => ChatMessageFormatter::format($existing, $user->id),
                ]);
            }
        }

        $message = DB::transaction(function () use ($validated, $conversation, $user, $body, $mediaPath) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body ?: null,
                'media_path' => $mediaPath,
                'media_type' => $validated['media_type'] ?? null,
                'media_kind' => $validated['media_kind'] ?? ($mediaPath ? 'media' : 'none'),
                'media_duration' => $validated['media_duration'] ?? null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'client_uuid' => $validated['client_uuid'] ?? null,
            ]);

            $conversation->update([
                'last_message_at' => $message->created_at,
            ]);

            $participantIds = $conversation->participants()->pluck('users.id');
            $now = now();

            $rows = $participantIds->map(function ($participantId) use ($message, $user, $now) {
                $isSender = (int) $participantId === (int) $user->id;

                return [
                    'message_id' => $message->id,
                    'user_id' => $participantId,
                    'delivered_at' => $now,
                    'read_at' => $isSender ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            MessageDelivery::insert($rows);

            $conversation->participants()->updateExistingPivot($user->id, [
                'last_read_at' => $now,
            ]);

            return $message->fresh([
                'sender:id,username,dname,profile_picture',
                'deliveries.user:id,username,dname',
                'conversation.participants:id,username,dname',
            ]);
        });

        $payload = ChatMessageFormatter::format($message, $user->id);
        broadcast(new MessageSent($conversation->id, $payload))->toOthers();

        $recipientIds = $conversation->participants()
            ->where('users.id', '!=', $user->id)
            ->pluck('users.id');

        foreach ($recipientIds as $recipientId) {
            broadcast(new MessageDelivered((int) $recipientId, $conversation->id, $payload));
            broadcast(new ConversationUpdated((int) $recipientId, $conversation->id));
        }

        return response()->json(['data' => $payload], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $validated = $request->validate([
            'last_message_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        $lastMessageId = (int) ($validated['last_message_id'] ?? $conversation->messages()->max('id'));

        if (! $lastMessageId) {
            return response()->json(['ok' => true]);
        }

        $now = now();

        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => $now,
        ]);

        $messageIds = $conversation->messages()
            ->where('id', '<=', $lastMessageId)
            ->pluck('id');

        $rows = $messageIds->map(fn ($id) => [
            'message_id' => $id,
            'user_id' => $user->id,
            'delivered_at' => $now,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (! empty($rows)) {
            MessageDelivery::upsert(
                $rows,
                ['message_id', 'user_id'],
                ['delivered_at', 'read_at', 'updated_at']
            );
        }

        $reader = [
            'id' => $user->id,
            'username' => $user->username,
            'dname' => $user->dname,
            'profile_picture_url' => $user->profile_picture_url,
        ];

        broadcast(new MessageRead($conversation->id, $lastMessageId, $reader, $now->toIso8601String()))->toOthers();

        return response()->json(['ok' => true]);
    }
}
