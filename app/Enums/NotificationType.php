<?php

namespace App\Enums;

enum NotificationType: string
{
    // Social activity
    case BleepLiked = 'bleep.liked';
    case BleepCommented = 'bleep.commented';
    case CommentReplied = 'comment.replied';
    case CommentLiked = 'comment.liked';
    case BleepReposted = 'bleep.reposted';
    case BleepShared = 'bleep.shared';
    case RepostLiked = 'repost.liked';

    // Following
    case UserFollowed = 'user.followed';
    case UserUnfollowed = 'user.unfollowed';
    case FollowRequested = 'follow.requested';
    case FollowAccepted = 'follow.accepted';

    // Profile visits (opt-in)
    case ProfileVisited = 'profile.visited';

    // Moderation
    case ReportSubmitted = 'report.submitted';
    case ReportReviewed = 'report.reviewed';
    case ReportResolved = 'report.resolved';
    case ContentRemoved = 'content.removed';
    case WarningIssued = 'moderation.warned';

    // Account
    case AccountBanned = 'account.banned';
    case AccountUnbanned = 'account.unbanned';
    case RoleChanged = 'account.role_changed';

    // Admin audit trail
    case AdminBanIssued = 'admin.ban_issued';
    case AdminUnbanned = 'admin.unbanned';
    case AdminRoleChanged = 'admin.role_changed';
    case AdminWarningIssued = 'admin.warning_issued';

    /**
     * Primary audience for the type. Staff types are resolved per recipient
     * so an admin sees moderation rows in the admin audience.
     */
    public function audience(): NotificationAudience
    {
        return match ($this) {
            self::ReportSubmitted, self::ReportReviewed => NotificationAudience::Moderator,
            self::AdminBanIssued, self::AdminUnbanned, self::AdminRoleChanged, self::AdminWarningIssued => NotificationAudience::Admin,
            default => NotificationAudience::User,
        };
    }

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::BleepLiked,
            self::CommentLiked,
            self::BleepReposted,
            self::BleepShared,
            self::RepostLiked => NotificationCategory::Social,

            self::BleepCommented,
            self::CommentReplied => NotificationCategory::Content,

            self::UserFollowed,
            self::UserUnfollowed,
            self::FollowRequested,
            self::FollowAccepted => NotificationCategory::Follow,

            self::ReportSubmitted,
            self::ReportReviewed,
            self::ReportResolved,
            self::ContentRemoved,
            self::WarningIssued => NotificationCategory::Moderation,

            self::ProfileVisited => NotificationCategory::Visits,

            self::AccountBanned,
            self::AccountUnbanned,
            self::RoleChanged => NotificationCategory::Account,

            self::AdminBanIssued,
            self::AdminUnbanned,
            self::AdminRoleChanged,
            self::AdminWarningIssued => NotificationCategory::System,
        };
    }

    /**
     * Preference column that can mute this type. `null` means the notice is
     * always delivered (bans, warnings, removed content, role changes).
     */
    public function preferenceKey(): ?string
    {
        return match ($this) {
            self::BleepLiked => 'notify_likes',
            self::BleepCommented => 'notify_comments',
            self::CommentReplied => 'notify_replies',
            self::CommentLiked => 'notify_comment_likes',
            self::BleepReposted => 'notify_reposts',
            self::BleepShared => 'notify_shares',
            self::RepostLiked => 'notify_repost_likes',
            self::UserFollowed,
            self::FollowRequested,
            self::FollowAccepted => 'notify_follows',
            self::UserUnfollowed => 'notify_unfollows',
            self::ProfileVisited => 'notify_profile_visits',
            self::ReportResolved => 'notify_report_updates',
            self::ReportSubmitted => 'notify_staff_reports',
            self::ReportReviewed,
            self::AdminBanIssued,
            self::AdminUnbanned,
            self::AdminRoleChanged,
            self::AdminWarningIssued => 'notify_staff_audit',
            self::AccountBanned,
            self::AccountUnbanned,
            self::RoleChanged,
            self::ContentRemoved,
            self::WarningIssued => null,
        };
    }

    /**
     * Lucide icon name rendered next to the notification.
     */
    public function icon(): string
    {
        return match ($this) {
            self::BleepLiked, self::CommentLiked => 'heart',
            self::BleepCommented, self::CommentReplied => 'message-circle',
            self::BleepReposted, self::RepostLiked => 'repeat',
            self::BleepShared => 'forward',
            self::UserFollowed, self::FollowRequested, self::FollowAccepted => 'user-plus',
            self::UserUnfollowed => 'user-minus',
            self::ProfileVisited => 'eye',
            self::ReportSubmitted, self::ReportReviewed, self::ReportResolved => 'flag',
            self::ContentRemoved => 'trash-2',
            self::WarningIssued, self::AdminWarningIssued => 'triangle-alert',
            self::AccountBanned, self::AdminBanIssued => 'ban',
            self::AccountUnbanned, self::AdminUnbanned => 'circle-check',
            self::RoleChanged, self::AdminRoleChanged => 'shield-check',
        };
    }
}
