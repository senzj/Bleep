<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageDelivered;
use App\Events\Chat\MessageRead;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageEdit;
use App\Models\MessageMedia;
use App\Models\User;
use App\Services\Broadcasting\BroadcastGateway;
use App\Services\Chat\ChatMessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(private BroadcastGateway $broadcasts) {}

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
            'shared_bleep_id' => ['nullable', 'integer', 'exists:bleeps,id'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $incomingMediaItems = collect($validated['media_items'] ?? []);

        // Validate media count limits
        $imageCount = $incomingMediaItems->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'image/'))->count();
        $videoCount = $incomingMediaItems->filter(fn ($item) => str_starts_with($item['media_type'] ?? '', 'video/'))->count();

        if ($imageCount > Message::MAX_IMAGES) {
            return response()->json([
                'message' => 'Maximum '.Message::MAX_IMAGES.' images per message allowed.',
            ], 422);
        }

        if ($videoCount > Message::MAX_VIDEOS) {
            return response()->json([
                'message' => 'Maximum '.Message::MAX_VIDEOS.' videos per message allowed.',
            ], 422);
        }

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);
        $this->assertDirectConversationAllowed($conversation, $user);

        if (! empty($validated['reply_to_id'])) {
            $replyTarget = Message::withTrashed()->find($validated['reply_to_id']);

            if (! $replyTarget || (int) $replyTarget->conversation_id !== (int) $conversation->id) {
                return response()->json([
                    'message' => 'You can only reply to a message in the same conversation.',
                ], 422);
            }
        }

        $sharedBleep = null;

        if (! empty($validated['shared_bleep_id'])) {
            $sharedBleep = Bleep::withTrashed()->find($validated['shared_bleep_id']);

            // A sender may only attach something they can see themselves, and
            // never a soft-deleted bleep (the formatter would mark it missing
            // for everyone else, including the recipient).
            if (! $sharedBleep || $sharedBleep->trashed() || ! $sharedBleep->isVisibleTo($user)) {
                return response()->json([
                    'message' => 'This bleep is not available to share.',
                ], 422);
            }
        }

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

        if ($body === '' && ! $mediaPath && $incomingMediaItems->isEmpty() && ! $sharedBleep) {
            return response()->json([
                'message' => 'Message body, media or a shared bleep is required.',
            ], 422);
        }

        if (! $this->chatMediaPathsAreValid($incomingMediaItems)) {
            return response()->json([
                'message' => 'One or more media attachments are invalid.',
            ], 422);
        }

        if (! empty($validated['client_uuid'])) {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_id', $user->id)
                ->where('client_uuid', $validated['client_uuid'])
                ->with(ChatMessageFormatter::relations())
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => ChatMessageFormatter::format($existing, $user->id),
                ]);
            }
        }

        $message = DB::transaction(function () use ($validated, $conversation, $user, $body, $incomingMediaItems, $sharedBleep) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body ?: null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'shared_bleep_id' => $sharedBleep?->id,
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

            return $message->fresh(ChatMessageFormatter::relations());
        });

        $payload = ChatMessageFormatter::format($message, $user->id);
        $this->broadcasts->dispatch(new MessageSent($conversation->id, $payload), toOthers: true);

        $recipientIds = $conversation->participants()
            ->where('users.id', '!=', $user->id)
            ->pluck('users.id');

        foreach ($recipientIds as $recipientId) {
            $this->broadcasts->dispatch(new MessageDelivered((int) $recipientId, $conversation->id, $payload));
            $this->broadcasts->dispatch(new ConversationUpdated((int) $recipientId, $conversation->id));
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

        if (! $this->chatMediaPathsAreValid($incomingMediaItems)) {
            return response()->json([
                'message' => 'One or more media attachments are invalid.',
            ], 422);
        }

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
                'message' => 'Maximum '.Message::MAX_IMAGES.' images per message allowed.',
            ], 422);
        }

        if ($videoCount > Message::MAX_VIDEOS) {
            return response()->json([
                'message' => 'Maximum '.Message::MAX_VIDEOS.' videos per message allowed.',
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
                'data' => ChatMessageFormatter::format($message->loadMissing(ChatMessageFormatter::relations()), $user->id),
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

            return $message->fresh(ChatMessageFormatter::relations());
        });

        $payload = ChatMessageFormatter::format($updatedMessage, $user->id);
        $this->broadcasts->dispatch(new MessageUpdated((int) $message->conversation_id, $payload), toOthers: true);

        return response()->json(['data' => $payload]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless((int) $message->sender_id === (int) $user->id, 403);

        if (! $message->trashed()) {
            // Hard delete reactions
            $message->reactions()->delete();

            // Hard delete media files from storage and database
            $mediaItems = $message->mediaItems;
            foreach ($mediaItems as $mediaItem) {
                if ($this->isSafeChatMediaPath($mediaItem->media_path)
                    && Storage::disk('public')->exists($mediaItem->media_path)) {
                    Storage::disk('public')->delete($mediaItem->media_path);
                }
            }
            $message->mediaItems()->delete();

            // Clear message body
            $message->body = null;
            $message->save();

            // Soft delete the message (keeps reply_to_id intact)
            $message->delete();
        }

        $deletedMessage = Message::withTrashed()
            ->with(ChatMessageFormatter::relations())
            ->findOrFail($message->id);

        $payload = ChatMessageFormatter::format($deletedMessage, $user->id);

        $this->broadcasts->dispatch(new MessageDeleted((int) $message->conversation_id, $payload), toOthers: true);

        return response()->json(['data' => $payload]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $validated = $request->validate([
            'last_message_id' => ['nullable', 'integer'],
        ]);

        $lastMessageId = (int) ($validated['last_message_id'] ?? 0);

        if ($lastMessageId === 0) {
            $lastMessageId = (int) $conversation->messages()->max('id');
        }

        if ($lastMessageId === 0) {
            return response()->json(['ok' => true]);
        }

        abort_unless(
            $conversation->messages()->withTrashed()->whereKey($lastMessageId)->exists(),
            422,
            'The message does not belong to this conversation.'
        );

        $now = now();

        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => $now,
        ]);

        MessageDelivery::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('message_id', '<=', $lastMessageId)
            ->whereIn('message_id', $conversation->messages()->withTrashed()->select('id'))
            ->update([
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        $reader = [
            'id' => $user->id,
            'username' => $user->username,
            'dname' => $user->dname,
            'profile_picture_url' => $user->profile_picture_url,
        ];

        $this->broadcasts->dispatch(new MessageRead($conversation->id, $lastMessageId, $reader, $now->toIso8601String()), toOthers: true);

        return response()->json(['ok' => true]);
    }

    /**
     * Direct conversations cannot be used to bypass a block.
     */
    private function assertDirectConversationAllowed(Conversation $conversation, User $user): void
    {
        if ($conversation->is_group) {
            return;
        }

        $other = $conversation->participants()
            ->where('users.id', '!=', $user->id)
            ->first();

        abort_if($other && $user->isBlockedOrHasBlocked($other), 403, 'Messaging is unavailable due to a block relationship.');
    }

    /**
     * Chat media must live inside the chat directory and actually exist.
     */
    private function chatMediaPathsAreValid(iterable $mediaItems): bool
    {
        foreach ($mediaItems as $item) {
            if (! $this->isSafeChatMediaPath($item['media_path'] ?? null)) {
                return false;
            }

            if (! Storage::disk('public')->exists($item['media_path'])) {
                return false;
            }
        }

        return true;
    }

    private function isSafeChatMediaPath(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && str_starts_with($path, 'chat/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0");
    }
}
