<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ChatMessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $keyword = trim((string) $request->query('q', ''));
        $onlineCutoff = now()->subMinutes(2)->timestamp;

        $activeSessionsSubquery = DB::table('sessions')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity');

        $recentDmSubquery = DB::table('conversations as c')
            ->join('conversation_user as me', function ($join) use ($currentUser) {
                $join->on('me.conversation_id', '=', 'c.id')
                    ->where('me.user_id', '=', $currentUser->id);
            })
            ->join('conversation_user as other', function ($join) use ($currentUser) {
                $join->on('other.conversation_id', '=', 'c.id')
                    ->where('other.user_id', '!=', $currentUser->id);
            })
            ->where('c.is_group', false)
            ->groupBy('other.user_id')
            ->selectRaw('other.user_id as other_user_id, MAX(c.last_message_at) as last_messaged_at');

        $usersQuery = User::query()
            ->where('users.id', '!=', $currentUser->id)
            ->leftJoinSub($recentDmSubquery, 'recent_dm', function ($join) {
                $join->on('users.id', '=', 'recent_dm.other_user_id');
            })
            ->leftJoin('followings as i_follow_them', function ($join) use ($currentUser) {
                $join->on('i_follow_them.followed_id', '=', 'users.id')
                    ->where('i_follow_them.follower_id', '=', $currentUser->id);
            })
            ->leftJoin('followings as they_follow_me', function ($join) use ($currentUser) {
                $join->on('they_follow_me.follower_id', '=', 'users.id')
                    ->where('they_follow_me.followed_id', '=', $currentUser->id);
            })
            ->leftJoinSub($activeSessionsSubquery, 'active_sessions', function ($join) {
                $join->on('users.id', '=', 'active_sessions.user_id');
            })
            ->select([
                'users.id',
                'users.username',
                'users.dname',
                'users.profile_picture',
                'recent_dm.last_messaged_at',
                'active_sessions.last_activity',
            ])
            ->selectRaw('CASE WHEN i_follow_them.id IS NOT NULL AND they_follow_me.id IS NOT NULL THEN 1 ELSE 0 END as is_friend');

        if ($keyword === '') {
            $usersQuery->whereNotNull('i_follow_them.id')
                ->whereNotNull('they_follow_me.id');
        }

        if ($keyword !== '') {
            $usersQuery->where(function ($query) use ($keyword) {
                $query->where('users.username', 'like', '%' . $keyword . '%')
                    ->orWhere('users.dname', 'like', '%' . $keyword . '%');
            });
        }

        $users = $usersQuery
            ->orderByRaw('CASE WHEN recent_dm.last_messaged_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('recent_dm.last_messaged_at')
            ->orderBy('users.username')
            ->get();

        $data = $users->map(function (User $user) use ($onlineCutoff) {
            $lastActivity = (int) ($user->last_activity ?? 0);

            return [
                'id' => $user->id,
                'username' => $user->username,
                'dname' => $user->dname,
                'profile_picture_url' => $user->profile_picture_url,
                'is_friend' => (bool) $user->is_friend,
                'relation_label' => (bool) $user->is_friend ? 'Friend' : 'Stranger',
                'last_messaged_at' => $user->last_messaged_at ? Carbon::parse($user->last_messaged_at)->toIso8601String() : null,
                'is_online' => $lastActivity > 0 && $lastActivity >= $onlineCutoff,
                'last_seen_at' => $lastActivity > 0 ? Carbon::createFromTimestamp($lastActivity)->toIso8601String() : null,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with([
                'participants:id,username,dname,profile_picture',
                'messages' => fn ($query) => $query->latest()->limit(1)->with(['sender:id,username,dname,profile_picture', 'deliveries.user:id,username,dname', 'conversation.participants:id,username,dname']),
            ])
            ->orderByDesc('conversation_user.is_pinned')
            ->orderByDesc('last_message_at')
            ->get();

        $participantIds = $conversations
            ->flatMap(fn (Conversation $conversation) => $conversation->participants->pluck('id'))
            ->unique()
            ->values();

        $onlineCutoff = now()->subMinutes(2)->timestamp;
        $lastActivityByUserId = DB::table('sessions')
            ->whereIn('user_id', $participantIds)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->pluck('last_activity', 'user_id');

        $data = $conversations->map(function (Conversation $conversation) use ($user) {
            $participants = $conversation->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'username' => $participant->username,
                'dname' => $participant->dname,
                'profile_picture_url' => $participant->profile_picture_url,
                'last_read_at' => optional($participant->pivot?->last_read_at)?->toIso8601String(),
                'is_online' => false,
                'last_seen_at' => null,
            ])->values();

            $otherNames = $conversation->participants
                ->where('id', '!=', $user->id)
                ->map(fn ($participant) => $participant->dname ?: $participant->username)
                ->values();

            $lastMessage = $conversation->messages->first();

            return [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'title' => $conversation->is_group
                    ? ($conversation->name ?: 'Group Chat')
                    : ($otherNames->first() ?: 'Direct Message'),
                'is_group' => $conversation->is_group,
                'participants' => $participants,
                'last_read_at' => optional($conversation->pivot->last_read_at)?->toIso8601String(),
                'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                'last_message' => $lastMessage ? ChatMessageFormatter::format($lastMessage, $user->id) : null,
            ];
        })->map(function (array $conversation) use ($lastActivityByUserId, $onlineCutoff) {
            $conversation['participants'] = collect($conversation['participants'])
                ->map(function (array $participant) use ($lastActivityByUserId, $onlineCutoff) {
                    $lastActivity = (int) ($lastActivityByUserId[$participant['id']] ?? 0);

                    $participant['is_online'] = $lastActivity > 0 && $lastActivity >= $onlineCutoff;
                    $participant['last_seen_at'] = $lastActivity > 0
                        ? Carbon::createFromTimestamp($lastActivity)->toIso8601String()
                        : null;

                    return $participant;
                })
                ->values()
                ->all();

            return $conversation;
        })->values();

        return response()->json(['data' => $data]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $limit = max(1, min(100, (int) $request->query('limit', 40)));
        $beforeId = (int) $request->query('before_id', 0);

        $query = $conversation->messages()
            ->with([
                'sender:id,username,dname,profile_picture',
                'deliveries.user:id,username,dname',
                'conversation.participants:id,username,dname',
            ])
            ->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rawMessages = $query
            ->limit($limit + 1)
            ->get();

        $hasMore = $rawMessages->count() > $limit;

        $messages = $rawMessages
            ->take($limit)
            ->reverse()
            ->values();

        $nextBeforeId = optional($messages->first())->id;

        return response()->json([
            'data' => $messages->map(fn ($message) => ChatMessageFormatter::format($message, $user->id))->values(),
            'meta' => [
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_before_id' => $nextBeforeId,
            ],
        ]);
    }

    public function createDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((int) $validated['user_id'] === (int) $request->user()->id) {
            return response()->json([
                'message' => 'Cannot create a direct conversation with yourself.',
            ], 422);
        }

        $me = $request->user();
        $otherUserId = (int) $validated['user_id'];

        $existing = Conversation::query()
            ->where('is_group', false)
            ->whereHas('participants', fn ($query) => $query->where('users.id', $me->id))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $otherUserId))
            ->withCount('participants')
            ->get()
            ->first(fn ($conversation) => $conversation->participants_count === 2);

        if (! $existing) {
            $existing = Conversation::create([
                'name' => null,
                'is_group' => false,
                'creator_id' => $me->id,
            ]);

            $existing->participants()->attach([
                $me->id => [
                    'role' => 'member',
                    'joined_at' => now(),
                ],
                $otherUserId => [
                    'role' => 'member',
                    'joined_at' => now(),
                ],
            ]);
        }

        broadcast(new ConversationUpdated($otherUserId, $existing->id));

        return response()->json([
            'data' => [
                'id' => $existing->id,
            ],
        ]);
    }
}
