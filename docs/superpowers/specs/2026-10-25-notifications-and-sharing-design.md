# In-app notifications + bleep sharing — design

Date: 2026-10-25
Status: proposed (awaiting approval)
Scope owner: product

## 1. Goals

1. Give every user a working notification inbox (bell badge, dropdown, `/notifications` page) covering the interactions they already asked for.
2. Make the inbox role-aware: `user`, `moderator`, `admin` each see the events that matter to them.
3. Realtime updates where Reverb is reachable, with an automatic polling fallback for shared hosting.
4. Let users send a bleep into a DM or group chat, and improve link sharing (working counts, previews, native share).
5. No new PHP/JS dependencies, no queue worker, no Redis, no cron requirement for correctness.

Non-goals (phase 1): email/push notifications, chat-message notifications (chat already has unread badges), comment permalinks, link auto-detection inside message bodies.

## 2. What exists today (verified)

| Piece | State |
| --- | --- |
| Users table | `role` = `admin`/`moderator`/`user`; ban fields; `last_strike_at`; `suspension_count`/`report_strikes` (not in `$fillable`) |
| `Notifiable` trait | Present on `User`, completely unused (no `notify()`, no `notifications:` table) |
| Realtime | `window.Echo` initialised in `bootstrap.js` on every page; only the chat page subscribes; `App.Models.User.{id}` private channel already authorised in `routes/channels.php` |
| Bell UI | Dead link in `navlinks.blade.php` (`href="#"`, highlights `announcements`), shared by horizontal, vertical and mobile navs |
| Home right panel | Empty slot labelled "Quick Few Notifications" |
| Preferences | `user_preferences` with sounds (`recieve_notification_sound`, `send_notification_sound`), `hide_activity` (profile activity, not notifications) |
| Moderation | Reports on bleeps/comments only; bans via 4 paths; `applyStrikeToUser()` is dead code; no warnings, no user reports |
| Profile visits | Not tracked per profile (`Visits` = viewer/IP/UA only) |
| Sharing | Bleep-only, link-only, one `shares` row per user per day, `/s/{token}` redirect, modal copies URL |
| Share count UI | Broken: `share.js` updates `.share-count` / `.share-text`, but `bleepfooter.blade.php` renders plain spans |
| Chat messages | Escaped plain text, no links, no attachments beyond media; no share/embed field |
| Comment permalinks | None (`CommentsRepliesController::show()` empty, no anchors) |

## 3. Architecture decision: storage and delivery

### Option A — Laravel's built-in database notifications
`notifications:table` + `Notifiable` + per-event Notification classes with `toDatabase()` / `ShouldBroadcastNow`.

- Pros: least custom code, `$user->notify()`, built-in read/unread helpers.
- Cons: single `data` JSON blob (filters/aggregation become JSON queries), UUID keys, a generic table that fights our `audience`/`category`/grouping needs, and it replaces the very trait we would keep for future mail.

### Option B — dedicated `notifications` table + `NotificationService` (recommended)
Typed columns, one enum-driven service, explicit fan-out.

- Pros: indexable `(user_id, read_at)`, `audience`/`category` for tabs and preferences, `group_key` + `group_count` for "X and 3 others liked your bleep" (keeps the table small on shared hosting), full payload control, integer keys, easy pruning.
- Cons: hand-rolled `markRead`/`markAllRead` (~40 lines), one more table to own.

### Option C — hybrid (custom now, `Notifiable` for mail later)
Same as B, keep the `Notifiable` trait in place. Chosen implicitly by B; mail is a separate future decision.

**Recommendation: B.** The feeds that matter here are "my inbox" and "staff queue", both of which need cheap filtering and grouping. JSON-in-a-blob would push that work into PHP.

Consequence: the unused `Notifiable` trait is **removed** from `User` (verified: no `notify()`, no `notifications()` callers). Its database-channel methods would collide with the new `notifications()` relation and silently query the wrong columns. Mail/push remain a separate future decision; re-adding the trait would require a different relation name.

### Schema

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // recipient
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('type', 64);                                              // NotificationType value
    $table->string('audience', 16);                                          // user|moderator|admin
    $table->string('category', 24);                                          // social|content|follow|moderation|account|visits|system
    $table->nullableMorphs('subject');                                       // bleep|comment|report|user
    $table->string('url')->nullable();
    $table->string('group_key', 96)->nullable();
    $table->unsignedInteger('group_count')->default(1);
    $table->json('data')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'read_at', 'created_at']);
    $table->index(['user_id', 'audience', 'created_at']);
    $table->unique(['user_id', 'group_key']);                                // NULLs are not deduped
});
```

`data` holds a render snapshot (`actor_name`, `excerpt`, `count`) so the list never N+1s and keeps rendering after the subject is deleted.

### Delivery

- `NotificationService::notify(iterable $recipients, NotificationType $type, array $payload)`:
  1. drop the actor, drop blocked pairs, drop recipients who disabled the type's preference key,
  2. upsert by `(user_id, group_key)` when a group key is given (`group_count + 1`, `read_at = null`, `updated_at = now`), otherwise insert,
  3. dispatch `App\Events\Notifications\NotificationCreated` (`ShouldBroadcastNow`) once per recipient through the shared broadcast gateway.
- `BroadcastGateway` moves from `App\Services\Chat` to `App\Services\Broadcasting` (3 import updates) and gets a neutral log message. Its try/catch is why a dead Reverb never turns a like into a 500.
- Channel: existing `private-App.Models.User.{id}`, event name `notification.created`, payload = the formatted row.
- Client: Alpine `notificationsBell` subscribes when `window.Echo` exists; otherwise polls `/api/notifications/unread-count` every 60s. Server-rendered initial count means the badge is correct before JS runs.

### Pruning
`php artisan notifications:prune --days=90` (keeps max 500 per user) registered in `routes/console.php`. Not required for correctness; documented as a cron/`schedule:run` task for production.

## 4. Event → recipient matrix

Legend: **A** = actor, **R** = recipient. Default = preference default on/off. "Pref" is the `user_preferences` column that can suppress it.

### 4.1 User audience (social + account)

| # | Event | R | Type | Category | Group key | Default | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Bleep liked | bleep author | `bleep.liked` | social | `like:bleep:{id}` | on | Self-like skipped. Anonymous bleep → actor hidden ("Someone"). |
| 2 | Bleep commented | bleep author | `bleep.commented` | content | `comment:bleep:{id}` | on | Self-comment skipped. |
| 3 | Comment replied | parent comment author | `comment.replied` | content | `reply:comment:{id}` | on | Bleep author also gets #2 on first-level comments only. |
| 4 | Comment liked | comment author | `comment.liked` | social | `like:comment:{id}` | off | Noisy on active threads. |
| 5 | Bleep reposted | bleep author | `bleep.reposted` | social | `repost:bleep:{id}` | on | |
| 6 | Bleep link shared | bleep author | `bleep.shared` | social | `share:bleep:{id}` | on | One `shares` row per sharer per day; grouped. |
| 7 | Reposted bleep liked | reposter | `repost.liked` | social | `like:repost:{bleepId}:{userId}` | off | Opt-in; avoids double noise with #1. |
| 8 | Followed | followed user | `user.followed` | follow | `follow:{actorId}` | on | |
| 9 | Unfollowed | unfollowed user | `user.unfollowed` | follow | `unfollow:{actorId}` | off | Requested for completeness; off by default because it leaks churn. |
| 10 | Follow request received | target | `follow.requested` | follow | `followreq:{actorId}` | on | Private profiles. |
| 11 | Follow request accepted | requester | `follow.accepted` | follow | `followacc:{targetId}` | on | |
| 12 | Profile visited | profile owner | `profile.visited` | visits | `visit:{actorId}:{date}` | off | Opt-in. Daily dedupe per viewer. Self/anonymous skipped. |
| 13 | Report resolved/dismissed | reporter | `report.resolved` | moderation | `reportres:{reportIdGroup}` | on | "We took action" vs "No action needed"; no moderator identity. |
| 14 | Banned | banned user | `account.banned` | account | null | on | Reason + expiry, links to `/banned`. |
| 15 | Unbanned | user | `account.unbanned` | account | null | on | |
| 16 | Role changed | affected user | `account.role_changed` | account | null | on | Only admins can trigger. |
| 17 | Content removed | content author | `content.removed` | moderation | null | on | Which content, which rule, link to guidelines. |

### 4.2 Moderator audience (staff queue)

| # | Event | R | Type | Category | Group key | Default |
| --- | --- | --- | --- | --- | --- | --- |
| 18 | New report submitted | all mods + admins | `report.submitted` | moderation | `reportqueue:{type}:{id}` | on |
| 19 | Report resolved by another mod | mods + admins (not the resolver) | `report.reviewed` | moderation | `reportreview:{type}:{id}` | off |

### 4.3 Admin audience (audit)

| # | Event | R | Type | Category | Default |
| --- | --- | --- | --- | --- | --- |
| 20 | Ban issued (any path) | all admins (not the issuer) | `admin.ban_issued` | system | on |
| 21 | Unban | all admins | `admin.unbanned` | system | on |
| 22 | Role changed | all admins | `admin.role_changed` | system | on |

Fan-out is per recipient row with a role-derived audience, so the same event can produce a moderator row and an admin row without duplicate reads. Moderators only see 18–19; admins see 18–22.

### 4.4 Warnings and strikes (in phase 1)

The existing `users.report_strikes`, `users.last_strike_at` and `ReportsController::applyStrikeToUser()` describe an escalation ladder that was never wired up. Phase 1 finishes it with a real record instead of a counter.

```php
Schema::create('user_warnings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // warned user
    $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
    $table->string('reason', 32);                                            // spam|harassment|hate|nsfw|illegal|other
    $table->text('notes')->nullable();
    $table->timestamp('expires_at')->nullable();                             // rolling 7-day window
    $table->timestamp('revoked_at')->nullable();
    $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['user_id', 'revoked_at', 'expires_at']);
});
```

- A warning is the unit of record; a **strike** is an active (not revoked, not expired) warning. `users.report_strikes` becomes a maintained cache of that count, so the existing ladder code keeps working.
- Issue path: report card → **Warn User** → modal (reason, optional notes, expiry preset) → `POST /admin/reports/{report}/warn`. Resolves all pending reports for that target with `action_taken = 'warned'`.
- Auto-ban: when active strikes reach `config('moderation.auto_ban_at_strikes')` (default 3, set 0 to disable), the existing `computeBanDuration()` ladder applies the ban. The response states whether a ban was triggered.
- Revoke: `POST /admin/warnings/{warning}/revoke` (staff), recomputes strikes.
- History: `GET /admin/users/{user}/warnings` powers a warnings block in the existing admin user modal (count, reason, date, revoke button).
- Notifications: `moderation.warned` to the warned user (never suppressible), `admin.warning_issued` to admins (audit).

Policy defaults chosen here (overridable): 7-day strike expiry, 3 active strikes → auto-ban, warnings issued from a report are visible to the author but never reveal the reporter.

Explicitly **not** in phase 1: chat message notifications, likes on chat messages, digest emails, "someone mentioned you", appeals workflow for warnings.


## 5. Preference model

15 boolean columns on `user_preferences` (follows the existing column + `VALID_KEYS` + `.pref-toggle` pattern; the toggle JS needs no changes):

`notify_likes`, `notify_comments`, `notify_replies`, `notify_comment_likes`, `notify_reposts`, `notify_shares`, `notify_repost_likes`, `notify_follows`, `notify_unfollows`, `notify_profile_visits`, `notify_report_updates`, `notify_moderation`, `notify_account`, `notify_staff_reports`, `notify_staff_audit`.

Tradeoff: a JSON blob would be one column and no migration per new type, but it breaks the existing per-key validation and `.pref-toggle` auto-save. Columns win on consistency; the list is stable.

Staff keys are only rendered for `hasAdminAccess()` users. Sound continues to use `recieve_notification_sound`.

## 6. UI

1. **Bell** — `navlinks.blade.php` link becomes `route('notifications.index')`, keeps the dead-slot class conventions, shows a red count badge (same visual language as the chat badge) rendered server-side. Alpine `notificationsBell` opens a dropdown with the latest 8 rows, "Mark all read", "View all".
2. **`/notifications`** — paginated (30) list; filter chips `All` / `Unread` / `Staff` (Staff only for `hasAdminAccess()`). Each row: actor avatar, type icon, one-line text, relative time, unread dot, link to subject. Empty states per filter.
3. **Home right panel** — `x-notifications.quick` renders the latest 5, or hides itself when empty.
4. **Preferences** — new "Notifications" block above the existing sound selectors: grouped toggles (Activity / Social / Account / Staff).
5. **Toast + sound** — on a live event, show a small toast and play `recieve_notification_sound` (muted when the tab is hidden, to avoid surprise audio).

## 7. Sharing plan

### 7.1 Phase A — fix what is broken (small)
- `bleepfooter.blade.php`: add `share-count` / `share-text` classes so `share.js` can update the number.
- `show`/lazy payload: include the current share count so cards render without an extra query.
- Post page: OpenGraph/Twitter meta via `@push('meta')` — title, 160-char excerpt, `og:image` from the first image media, canonical URL. **Anonymous bleeps must not leak author identity** (use "Anonymous", no avatar); NSFW bleeps omit `og:image`.
- Native share: use `navigator.share` when available (mobile), fall back to copy + toast.

### 7.2 Phase B — send in chat
- Migration: `messages.shared_bleep_id` nullable FK → `bleeps`, `nullOnDelete`, indexed.
- `MessageController@store` accepts `shared_bleep_id` (must be visible to the sender) as an alternative to body/media; no new endpoint, so idempotency, deliveries, read receipts and broadcasts are reused as-is.
- `ChatMessageFormatter` adds a `shared_bleep` block (`id`, `url`, `excerpt`, author summary, media thumbnail, `is_unavailable`) and eager-loads it (`sharedBleep.user`, `sharedBleep.media`). Deleted/hidden bleeps render as unavailable instead of 404.
- Vue chat: `SharedBleepCard.vue` inside the bubble; reply previews and the conversation list read "Shared a bleep" when a message has no body.
- Share modal becomes two tabs: **Link** (existing) and **Send in chat** — friends from `/chat/users` + existing conversations from `/chat/conversations`; picking a user with no DM calls `POST /chat/conversations/direct` first, then `POST /messages`.
- A chat send does **not** create a `shares` row: link shares and chat shares are different metrics. Footer count stays link-based.

### 7.3 Phase C — comments (deferred)
Requires a permalink (`/bleeps/{id}#comment-{id}`), a stable anchor in the Vue comment list, and visibility rules. Not built now because comments have no addressable page.

## 8. Implementation batches

| Batch | Contents | Visible change |
| --- | --- | --- |
| 1 | `notifications` + preference-column migrations, `Notification` model, enums, presenter, `NotificationService`, `NotificationCreated`, gateway move, service tests | none |
| 2 | Social hooks: likes, comments, replies, comment likes, reposts, shares, follows, follow requests | rows written, no UI |
| 3 | Bell + dropdown, `/notifications`, home quick panel, preference toggles, polling fallback, tests | **bell goes live** |
| 4 | Moderation/admin hooks (reports, bans, unbans, roles, content removal) **plus warnings/strikes**: `user_warnings`, warn + revoke endpoints, auto-ban ladder, admin user-modal history | staff queue live |
| 5 | `profile_visits` table, daily dedupe, opt-in notification | opt-in |
| 6 | Sharing Phase A + B (count fix, OG, native share, send-in-chat) | share modal upgraded |

Warnings ship with the staff batch because a "Warn" button is a moderation action, not a notification type — but they are in scope, not deferred.


## 9. Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Shared hosting has no Reverb → live badge dead | Server-rendered initial count + 60s polling fallback; broadcasts never break the request (gateway try/catch) |
| Notification spam on popular bleeps | Group keys + `group_count`, off-by-default for noisy types (comment likes, repost likes, unfollows, visits) |
| Privacy leaks (anonymous bleeps, unfollow, visits) | Actor hidden for anonymous subjects; visits opt-in and daily-deduped; unfollow off by default |
| Table growth | 90-day prune command + 500/user cap + grouping |
| Production FTP deploy does not run migrations | Every migration is listed in the handoff with the exact `php artisan migrate --path=...` command |
| `Notifiable` shadowing confusion | Documented on `User::notifications()`; no `notify()` usage in the codebase |

## 10. Test plan

Feature tests (Pest), one file per batch area:

- `NotificationSocialTest` — each of #1–#11, self-action skip, block skip, anonymous-actor masking, group increment, subject deletion still renders.
- `NotificationPreferenceTest` — each toggle suppresses its type, defaults match the matrix, staff keys ignored for normal users.
- `NotificationInboxTest` — auth required, tenant isolation (cannot read another user's row), unread count, mark read/all, pagination, filters.
- `NotificationModerationTest` — #13, #17–#22, staff fan-out audiences, resolver excluded from own review notice, ban via all four paths.
- `WarningTest` — issue from report, strike count, 7-day expiry, 3-strike auto-ban (and disabled ladder), revoke recomputes, warned user notified, admins audited, normal user blocked from endpoints.
- `NotificationVisitTest` — daily dedupe, self/anonymous skip, opt-in required.
- `ShareToChatTest` — shared bleep message, visibility 403/404, blocked DM, deleted bleep → unavailable, formatter payload shape, no share row created.
- `ShareLinkTest` — OG tags present, anonymous bleep leaks nothing, NSFW omits image, count markup updates.

Existing 46 tests must stay green.

## 11. Deployment checklist (manual, FTP)

1. `php artisan migrate --path=database/migrations/2026_10_25_120000_create_notifications_table.php`
2. `php artisan migrate --path=database/migrations/2026_10_25_120100_add_notification_preferences_to_user_preferences_table.php`
3. `php artisan migrate --path=database/migrations/2026_10_25_120200_create_user_warnings_table.php` (batch 4)
4. `php artisan migrate --path=database/migrations/2026_10_25_120300_add_warned_to_reports_action_taken.php` (batch 4)
5. `php artisan migrate --path=database/migrations/2026_10_25_120400_create_profile_visits_table.php` (batch 5)
6. `php artisan migrate --path=database/migrations/2026_10_25_120500_add_shared_bleep_id_to_messages_table.php` (batch 6)
7. `npm run build`
8. Optional: `* * * * * php artisan schedule:run` for pruning.
