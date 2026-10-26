<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Notification;

/**
 * Turns a notification row into the payload consumed by the bell, the inbox
 * page and the broadcast event. All copy lives here so the three surfaces
 * never drift apart.
 */
class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Notification $notification): array
    {
        $notification->loadMissing('actor:id,username,dname,profile_picture');

        $data = $notification->data ?? [];
        $anonymous = (bool) ($data['anonymous'] ?? false);

        $actorName = $anonymous
            ? 'Someone'
            : ($data['actor_name'] ?? $notification->actor?->dname ?? 'Someone');

        $actor = (! $anonymous && $notification->actor)
            ? [
                'id' => $notification->actor->id,
                'username' => $notification->actor->username,
                'dname' => $notification->actor->dname,
                'profile_picture_url' => $notification->actor->profile_picture_url,
            ]
            : null;

        return [
            'id' => $notification->id,
            'type' => $notification->type->value,
            'audience' => $notification->audience->value,
            'category' => $notification->category->value,
            'icon' => $notification->type->icon(),
            'title' => self::title($notification, $actorName, $data),
            'body' => self::body($notification, $data),
            'url' => $notification->url,
            'group_count' => $notification->group_count,
            'read' => $notification->read_at !== null,
            'actor' => $actor,
            'created_at' => optional($notification->created_at)->toIso8601String(),
            'created_at_human' => optional($notification->created_at)->diffForHumans(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function title(Notification $notification, string $actorName, array $data): string
    {
        $count = max(1, $notification->group_count);
        $role = (string) ($data['role'] ?? 'user');
        $target = (string) ($data['target_name'] ?? 'a user');
        $subject = (string) ($data['subject_label'] ?? 'content');
        $outcome = $data['outcome'] ?? null;
        $actionTaken = ! in_array($outcome, [null, '', 'none', 'no_action'], true);

        return match ($notification->type) {
            NotificationType::BleepLiked => self::aggregate($actorName, 'liked your bleep', $count),
            NotificationType::BleepCommented => self::aggregate($actorName, 'commented on your bleep', $count),
            NotificationType::CommentReplied => self::aggregate($actorName, 'replied to your comment', $count),
            NotificationType::CommentLiked => self::aggregate($actorName, 'liked your comment', $count),
            NotificationType::BleepReposted => self::aggregate($actorName, 'reposted your bleep', $count),
            NotificationType::BleepShared => self::aggregate($actorName, 'shared your bleep', $count),
            NotificationType::RepostLiked => self::aggregate($actorName, 'liked a bleep you reposted', $count),
            NotificationType::UserFollowed => "{$actorName} followed you",
            NotificationType::UserUnfollowed => "{$actorName} unfollowed you",
            NotificationType::FollowRequested => "{$actorName} requested to follow you",
            NotificationType::FollowAccepted => "{$actorName} accepted your follow request",
            NotificationType::ProfileVisited => "{$actorName} viewed your profile",
            NotificationType::ReportSubmitted => $count > 1
                ? "{$count} new reports need review"
                : 'A new report needs review',
            NotificationType::ReportReviewed => "{$actorName} resolved a report",
            NotificationType::ReportResolved => $actionTaken
                ? 'Your report was reviewed — action taken'
                : 'Your report was reviewed — no action needed',
            NotificationType::ContentRemoved => "Your {$subject} was removed",
            NotificationType::WarningIssued => 'You received a warning',
            NotificationType::AccountBanned => 'Your account was banned',
            NotificationType::AccountUnbanned => 'Your account was unbanned',
            NotificationType::RoleChanged => 'Your role is now '.ucfirst($role),
            NotificationType::AdminBanIssued => "{$actorName} banned {$target}",
            NotificationType::AdminUnbanned => "{$actorName} unbanned {$target}",
            NotificationType::AdminRoleChanged => "{$actorName} changed {$target}'s role to ".ucfirst($role),
            NotificationType::AdminWarningIssued => "{$actorName} warned {$target}",
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function body(Notification $notification, array $data): ?string
    {
        $parts = match ($notification->type) {
            NotificationType::BleepLiked,
            NotificationType::BleepCommented,
            NotificationType::CommentReplied,
            NotificationType::CommentLiked,
            NotificationType::BleepReposted,
            NotificationType::BleepShared,
            NotificationType::RepostLiked => [$data['excerpt'] ?? null],

            NotificationType::WarningIssued,
            NotificationType::AccountBanned,
            NotificationType::ContentRemoved => [
                $data['reason'] ?? null,
                isset($data['expires_at']) ? 'Until '.$data['expires_at'] : null,
            ],

            NotificationType::ReportResolved => [$data['notes'] ?? null],

            NotificationType::ReportSubmitted => [
                $data['excerpt'] ?? null,
                isset($data['category']) ? ucfirst((string) $data['category']) : null,
            ],

            NotificationType::AdminWarningIssued => [
                $data['reason'] ?? null,
                isset($data['strikes'])
                    ? $data['strikes'].' active '.($data['strikes'] === 1 ? 'strike' : 'strikes')
                    : null,
            ],

            default => [],
        };

        $parts = array_values(array_filter(array_map(
            fn ($part) => is_string($part) ? trim($part) : null,
            $parts
        )));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function aggregate(string $actorName, string $verb, int $count): string
    {
        if ($count < 2) {
            return "{$actorName} {$verb}";
        }

        return "{$actorName} and ".($count - 1)." others {$verb}";
    }
}
