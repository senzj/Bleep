<?php

namespace App\Services\Notifications;

use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Events\Notifications\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\Broadcasting\BroadcastGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for creating in-app notifications.
 *
 * Responsibilities:
 * - fan out to one or many recipients,
 * - never notify the actor about their own action,
 * - respect the recipient's category preferences,
 * - keep blocked users out of social notifications,
 * - collapse repeat events into one row via `group_key`.
 */
class NotificationService
{
    public function __construct(private BroadcastGateway $broadcasts) {}

    /**
     * @param  array{
     *     actor?: User|null,
     *     subject?: Model|null,
     *     url?: string|null,
     *     group_key?: string|null,
     *     data?: array<string, mixed>,
     * }  $attributes
     * @return int number of notifications created or refreshed
     */
    public function notify(User|iterable $recipients, NotificationType $type, array $attributes = []): int
    {
        $attributes += [
            'actor' => null,
            'subject' => null,
            'url' => null,
            'group_key' => null,
            'data' => [],
        ];

        $created = 0;

        foreach ($this->normaliseRecipients($recipients) as $recipient) {
            if ($this->isSuppressed($recipient, $type, $attributes['actor'])) {
                continue;
            }

            $notification = $this->persist($recipient, $type, $attributes);

            $this->broadcasts->dispatch(new NotificationCreated(
                (int) $recipient->id,
                NotificationPresenter::toArray($notification)
            ));

            $created++;
        }

        return $created;
    }

    /**
     * Notify the moderation queue (moderators + admins) or admins only,
     * depending on the type's declared audience.
     *
     * @param  array{
     *     actor?: User|null,
     *     subject?: Model|null,
     *     url?: string|null,
     *     group_key?: string|null,
     *     data?: array<string, mixed>,
     * }  $attributes
     * @param  User|iterable<User>|null  $except
     */
    public function notifyStaff(NotificationType $type, array $attributes = [], User|iterable|null $except = null): int
    {
        $roles = $type->audience() === NotificationAudience::Admin
            ? ['admin']
            : ['admin', 'moderator'];

        $exceptUsers = $this->normaliseRecipients($except ?? []);

        // Keep the old convenience: a single excluded user is also the actor
        // unless the caller supplied one explicitly.
        if (! array_key_exists('actor', $attributes) && $except instanceof User) {
            $attributes['actor'] = $except;
        }

        $recipients = User::query()
            ->whereIn('role', $roles)
            ->when($exceptUsers->isNotEmpty(), fn ($query) => $query->whereKeyNot($exceptUsers->pluck('id')->all()))
            ->get();

        return $this->notify($recipients, $type, $attributes);
    }

    public function unreadCount(User $user): int
    {
        return $user->notifications()->unread()->count();
    }

    public function markAllRead(User $user): int
    {
        return $user->notifications()->unread()->update(['read_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(User $recipient, NotificationType $type, array $attributes): Notification
    {
        $groupKey = $attributes['group_key'];
        $subject = $attributes['subject'];

        $values = [
            'actor_id' => $attributes['actor']?->id,
            'type' => $type->value,
            'audience' => $this->audienceFor($recipient, $type)->value,
            'category' => $type->category()->value,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'url' => $attributes['url'],
            'data' => $attributes['data'],
            'read_at' => null,
        ];

        return DB::transaction(function () use ($recipient, $groupKey, $values) {
            if ($groupKey === null) {
                return Notification::create($values + [
                    'user_id' => $recipient->id,
                    'group_key' => null,
                    'group_count' => 1,
                ]);
            }

            $existing = Notification::query()
                ->where('user_id', $recipient->id)
                ->where('group_key', $groupKey)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                return Notification::create($values + [
                    'user_id' => $recipient->id,
                    'group_key' => $groupKey,
                    'group_count' => 1,
                ]);
            }

            $existing->fill($values);
            $existing->group_count = $existing->group_count + 1;
            $existing->save();

            return $existing;
        });
    }

    private function isSuppressed(User $recipient, NotificationType $type, ?User $actor): bool
    {
        if ($actor && (int) $actor->id === (int) $recipient->id) {
            return true;
        }

        $preferenceKey = $type->preferenceKey();

        if ($preferenceKey !== null) {
            $preference = $recipient->preferences?->{$preferenceKey}
                ?? UserPreferences::defaults()[$preferenceKey];

            if (! $preference) {
                return true;
            }
        }

        if ($actor && $type->audience() === NotificationAudience::User && $type->preferenceKey() !== null) {
            return $recipient->isBlockedOrHasBlocked($actor);
        }

        return false;
    }

    private function audienceFor(User $recipient, NotificationType $type): NotificationAudience
    {
        return $type->audience() === NotificationAudience::User
            ? NotificationAudience::User
            : NotificationAudience::fromRole($recipient->role);
    }

    /**
     * @return Collection<int, User>
     */
    private function normaliseRecipients(User|iterable $recipients): Collection
    {
        $users = $recipients instanceof User ? collect([$recipients]) : collect($recipients);

        return $users
            ->filter(fn ($user) => $user instanceof User && $user->exists)
            ->unique('id')
            ->values();
    }
}
