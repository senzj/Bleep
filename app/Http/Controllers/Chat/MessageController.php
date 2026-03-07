<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDelivered;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageRead;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageEdit;
use App\Models\MessageMedia;
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
            'media_items' => ['nullable', 'array'],
            'media_items.*.media_path' => ['required', 'string', 'max:2048'],
            'media_items.*.media_type' => ['nullable', 'string', 'max:255'],
            'media_items.*.media_kind' => ['nullable', 'in:none,media,audio,voice'],
            'media_items.*.media_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $incomingMediaItems = collect($validated['media_items'] ?? []);

        // Validate media count limits
        $imageCount = $incomingMediaItems->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'image/'))->count();
        $videoCount = $incomingMediaItems->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'video/'))->count();

        if ($imageCount > Message::MAX_IMAGES) {
            return response()->json([
                'message' => 'Maximum ' . Message::MAX_IMAGES . ' images per message allowed.',
            ], 422);
        }

        if ($videoCount > Message::MAX_VIDEOS) {
            return response()->json([
                'message' => 'Maximum ' . Message::MAX_VIDEOS . ' videos per message allowed.',
            ], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $body = trim((string) ($validated['body'] ?? ''));
        $mediaPath = $validated['media_path'] ?? null;

        // Backward compatibility for older clients that still send single media fields.
        if ($incomingMediaItems->isEmpty() && $mediaPath) {
            $incomingMediaItems = collect([[
                'media_path' => $mediaPath,
                'media_type' => $validated['media_type'] ?? null,
                'media_kind' => $validated['media_kind'] ?? 'media',
                'media_duration' => $validated['media_duration'] ?? null,
            ]]);
        }

        if ($body === '' && ! $mediaPath && $incomingMediaItems->isEmpty()) {
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

        $message = DB::transaction(function () use ($validated, $conversation, $user, $body, $mediaPath, $incomingMediaItems) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body ?: null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'client_uuid' => $validated['client_uuid'] ?? null,
            ]);

            if ($incomingMediaItems->isNotEmpty()) {
                MessageMedia::insert(
                    $incomingMediaItems->map(function (array $item) use ($message) {
                        $now = now();

                        return [
                            'message_id' => $message->id,
                            'media_path' => $item['media_path'],
                            'media_type' => $item['media_type'] ?? null,
                            'media_kind' => $item['media_kind'] ?? 'media',
                            'media_duration' => $item['media_duration'] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all()
                );
            }

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
                'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
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

    public function update(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless((int) $message->sender_id === (int) $user->id, 403);
        abort_if($message->trashed(), 422, 'Deleted messages cannot be edited.');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'retained_media_ids' => ['nullable', 'array'],
            'retained_media_ids.*' => ['integer', 'exists:message_media,id'],
            'media_items' => ['nullable', 'array'],
            'media_items.*.media_path' => ['required', 'string', 'max:2048'],
            'media_items.*.media_type' => ['nullable', 'string', 'max:255'],
            'media_items.*.media_kind' => ['nullable', 'in:none,media,audio,voice'],
            'media_items.*.media_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $incomingMediaItems = collect($validated['media_items'] ?? []);

        $existingMediaIds = $message->mediaItems()->pluck('id')->all();
        $hasRetainedMediaInput = array_key_exists('retained_media_ids', $validated);
        $retainedMediaIds = collect($hasRetainedMediaInput ? ($validated['retained_media_ids'] ?? []) : $existingMediaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $existingMediaIds, true))
            ->values();

        $finalMediaTypeSamples = collect($message->mediaItems()->get(['id', 'media_type'])->all())
            ->filter(fn ($item) => $retainedMediaIds->contains((int) $item->id))
            ->map(fn ($item) => ['media_type' => $item->media_type]);

        $allFinalMedia = $finalMediaTypeSamples->concat(
            $incomingMediaItems->map(fn (array $item) => ['media_type' => $item['media_type'] ?? null])
        );

        $imageCount = $allFinalMedia->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'image/'))->count();
        $videoCount = $allFinalMedia->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'video/'))->count();

        if ($imageCount > Message::MAX_IMAGES) {
            return response()->json([
                'message' => 'Maximum ' . Message::MAX_IMAGES . ' images per message allowed.',
            ], 422);
        }

        if ($videoCount > Message::MAX_VIDEOS) {
            return response()->json([
                'message' => 'Maximum ' . Message::MAX_VIDEOS . ' videos per message allowed.',
            ], 422);
        }

        if ($body === '' && $retainedMediaIds->isEmpty() && $incomingMediaItems->isEmpty()) {
            return response()->json([
                'message' => 'Message body cannot be empty when no media exists.',
            ], 422);
        }

        $bodyChanged = $body !== (string) ($message->body ?? '');
        $mediaChanged = $retainedMediaIds->sort()->values()->all() !== collect($existingMediaIds)->sort()->values()->all()
            || $incomingMediaItems->isNotEmpty();

        if (! $bodyChanged && ! $mediaChanged) {
            return response()->json([
                'data' => ChatMessageFormatter::format($message->loadMissing([
                    'sender:id,username,dname,profile_picture',
                    'deliveries.user:id,username,dname',
                    'conversation.participants:id,username,dname',
                    'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
                ]), $user->id),
            ]);
        }

        $updatedMessage = DB::transaction(function () use ($message, $body, $user, $retainedMediaIds, $incomingMediaItems) {
            MessageEdit::create([
                'message_id' => $message->id,
                'editor_id' => $user->id,
                'old_body' => $message->body,
            ]);

            $message->mediaItems()
                ->whereNotIn('id', $retainedMediaIds->all())
                ->delete();

            if ($incomingMediaItems->isNotEmpty()) {
                MessageMedia::insert(
                    $incomingMediaItems->map(function (array $item) use ($message) {
                        $now = now();

                        return [
                            'message_id' => $message->id,
                            'media_path' => $item['media_path'],
                            'media_type' => $item['media_type'] ?? null,
                            'media_kind' => $item['media_kind'] ?? 'media',
                            'media_duration' => $item['media_duration'] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all()
                );
            }

            $message->update([
                'body' => $body !== '' ? $body : null,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            return $message->fresh([
                'sender:id,username,dname,profile_picture',
                'deliveries.user:id,username,dname',
                'conversation.participants:id,username,dname',
                'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
            ]);
        });

        $payload = ChatMessageFormatter::format($updatedMessage, $user->id);
        broadcast(new MessageUpdated((int) $message->conversation_id, $payload))->toOthers();

        return response()->json(['data' => $payload]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless((int) $message->sender_id === (int) $user->id, 403);

        if (! $message->trashed()) {
            $message->delete();
        }

        $deletedMessage = Message::withTrashed()
            ->with([
                'sender:id,username,dname,profile_picture',
                'deliveries.user:id,username,dname',
                'conversation.participants:id,username,dname',
                'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
            ])
            ->findOrFail($message->id);

        $payload = ChatMessageFormatter::format($deletedMessage, $user->id);

        broadcast(new MessageDeleted((int) $message->conversation_id, $payload))->toOthers();

        return response()->json(['data' => $payload]);
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
