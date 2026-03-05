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
        ]);

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
            'body' => $message->body,
            'media_path' => $message->media_path,
            'media_url' => $message->media_path ? asset('storage/' . ltrim($message->media_path, '/')) : null,
            'media_type' => $message->media_type,
            'media_kind' => $message->media_kind,
            'client_uuid' => $message->client_uuid,
            'status' => $status,
            'delivery_count' => $deliveryCount,
            'read_count' => $readCount,
            'seen_by' => $seenBy,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'updated_at' => optional($message->updated_at)?->toIso8601String(),
        ];
    }
}
