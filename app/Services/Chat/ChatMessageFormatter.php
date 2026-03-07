<?php

namespace App\Services\Chat;

use App\Models\Message;

class ChatMessageFormatter
{
    public static function format(Message $message, ?int $viewerId = null): array
    {
        $message->loadMissing([
            'sender:id,username,dname,profile_picture',
            'conversation.participants:id,username,dname',
            'deliveries.user:id,username,dname,profile_picture',
            'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
            'reactions.user:id,username,dname,profile_picture',
            'replyTo' => function ($query) {
                $query->withTrashed()->with([
                    'sender:id,username,dname,profile_picture',
                    'mediaItems:id,message_id,media_path,media_type,media_kind,media_duration',
                ]);
            },
        ]);

        $isDeleted = $message->trashed();

        $mediaItems = $message->mediaItems
            ->map(fn ($item) => [
                'id' => $item->id,
                'media_path' => $item->media_path,
                'media_url' => asset('storage/' . ltrim($item->media_path, '/')),
                'media_type' => $item->media_type,
                'media_kind' => $item->media_kind,
                'media_duration' => $item->media_duration,
            ])
            ->values();

        $primaryMedia = $mediaItems->first();

        $reactions = $message->reactions
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
            ->values();

        $replyTo = null;
        if ($message->replyTo) {
            $replyToMsg = $message->replyTo;
            $replyToDeleted = $replyToMsg->trashed();

            $replyMediaItems = $replyToMsg->mediaItems
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'media_path' => $item->media_path,
                    'media_url' => asset('storage/' . ltrim($item->media_path, '/')),
                    'media_type' => $item->media_type,
                    'media_kind' => $item->media_kind,
                    'media_duration' => $item->media_duration,
                ])
                ->values();

            $replyPrimaryMedia = $replyMediaItems->first();

            $replyTo = [
                'id' => $replyToMsg->id,
                'sender_id' => $replyToMsg->sender_id,
                'sender' => [
                    'id' => $replyToMsg->sender?->id,
                    'username' => $replyToMsg->sender?->username,
                    'dname' => $replyToMsg->sender?->dname,
                    'profile_picture_url' => $replyToMsg->sender?->profile_picture_url,
                ],
                'body' => $replyToDeleted ? null : $replyToMsg->body,
                'is_deleted' => $replyToDeleted,
                // Keep these single-media fields for backward compatibility in older clients.
                'media_path' => ($replyToDeleted || ! $replyPrimaryMedia) ? null : ($replyPrimaryMedia['media_path'] ?? null),
                'media_url' => ($replyToDeleted || ! $replyPrimaryMedia) ? null : ($replyPrimaryMedia['media_url'] ?? null),
                'media_type' => $replyPrimaryMedia['media_type'] ?? null,
                'media_kind' => $replyPrimaryMedia['media_kind'] ?? 'none',
                'media_duration' => $replyPrimaryMedia['media_duration'] ?? null,
                'media_items' => $replyToDeleted ? [] : $replyMediaItems,
            ];
        }

        $participantCount = $message->conversation->participants->count();
        $deliveryCount = $message->deliveries
            ->where('user_id', '!=', $message->sender_id)
            ->whereNotNull('delivered_at')
            ->count();

        $readDeliveries = $message->deliveries
            ->where('user_id', '!=', $message->sender_id)
            ->whereNotNull('read_at');

        $readCount = $readDeliveries->count();
        $requiredCount = max($participantCount - 1, 0);

        $status = 'sent';
        if ($viewerId !== null && $viewerId !== (int) $message->sender_id) {
            $status = 'received';
        } elseif ($requiredCount === 0) {
            $status = 'sent';
        } elseif ($readCount >= $requiredCount) {
            $status = 'seen';
        } elseif ($deliveryCount >= $requiredCount) {
            $status = 'delivered';
        }

        $seenBy = $readDeliveries
            ->map(fn ($delivery) => [
                'id' => $delivery->user_id,
                'username' => $delivery->user?->username,
                'dname' => $delivery->user?->dname,
                'read_at' => optional($delivery->read_at)?->toIso8601String(),
                'profile_picture_url' => $delivery->user?->profile_picture_url,
            ])
            ->values();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender' => [
                'id' => $message->sender?->id,
                'username' => $message->sender?->username,
                'dname' => $message->sender?->dname,
                'profile_picture_url' => $message->sender?->profile_picture_url,
            ],
            'body' => $isDeleted ? null : $message->body,
            // Keep these single-media fields for backward compatibility in older clients.
            'media_path' => ($isDeleted || ! $primaryMedia) ? null : ($primaryMedia['media_path'] ?? null),
            'media_url' => ($isDeleted || ! $primaryMedia) ? null : ($primaryMedia['media_url'] ?? null),
            'media_type' => $primaryMedia['media_type'] ?? null,
            'media_kind' => $primaryMedia['media_kind'] ?? 'none',
            'media_duration' => $primaryMedia['media_duration'] ?? null,
            'media_items' => $isDeleted ? [] : $mediaItems,
            'client_uuid' => $message->client_uuid,
            'is_edited' => (bool) $message->is_edited,
            'edited_at' => optional($message->edited_at)?->toIso8601String(),
            'is_deleted' => $isDeleted,
            'deleted_at' => optional($message->deleted_at)?->toIso8601String(),
            'status' => $status,
            'delivery_count' => $deliveryCount,
            'read_count' => $readCount,
            'seen_by' => $seenBy,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'updated_at' => optional($message->updated_at)?->toIso8601String(),
            'reply_to_id' => $message->reply_to_id,
            'reply_to' => $replyTo,
            'reactions' => $reactions,
        ];
    }
}
