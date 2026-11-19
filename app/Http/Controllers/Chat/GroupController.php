<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDelivered;
use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationInvite;
use App\Models\User;
use App\Services\Broadcasting\BroadcastGateway;
use App\Services\Chat\ChatMessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GroupController extends Controller
{
    public function __construct(private BroadcastGateway $broadcasts) {}

    /**
     * Rename a group or flip it to invite-only.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->requireGroupAdmin($request, $conversation);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'invite_only' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $conversation->name = trim($validated['name']) ?: $conversation->name;
        }

        if (array_key_exists('invite_only', $validated)) {
            $conversation->invite_only = (bool) $validated['invite_only'];
        }

        $conversation->save();

        return response()->json(['data' => $this->settingsPayload($conversation->fresh(), $request->user())]);
    }

    /**
     * Add a friend to the group. Invite-only groups raise a request instead of
     * adding them straight away.
     */
    public function addMember(Request $request, Conversation $conversation): JsonResponse
    {
        $this->requireGroupAdmin($request, $conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'That person is already in the group.'], 422);
        }

        if ($conversation->invite_only) {
            $invite = ConversationInvite::updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $user->id],
                ['inviter_id' => $request->user()->id, 'status' => ConversationInvite::STATUS_PENDING],
            );

            return response()->json([
                'data' => $this->settingsPayload($conversation->fresh(), $request->user()),
                'message' => 'Invite sent. They stay pending until you approve them.',
                'invite_id' => $invite->id,
            ]);
        }

        $this->attachMember($conversation, $user);

        $this->postSystemNotice($conversation, $request->user(), sprintf(
            '%s added %s to the group chat',
            $this->nameOf($request->user()),
            $this->nameOf($user),
        ));

        return response()->json([
            'data' => $this->settingsPayload($conversation->fresh(), $request->user()),
            'message' => 'Member added.',
        ]);
    }

    /**
     * Remove a member. Admins can remove anyone; anyone can remove themselves.
     */
    public function removeMember(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $isSelf = (int) $user->id === (int) $request->user()->id;

        if (! $isSelf) {
            $this->requireGroupAdmin($request, $conversation);
        }

        $conversation->participants()->detach($user->id);
        ConversationInvite::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();

        if (! $isSelf) {
            $this->postSystemNotice($conversation, $request->user(), sprintf(
                '%s removed %s from the group chat',
                $this->nameOf($request->user()),
                $this->nameOf($user),
            ));
        }

        return response()->json([
            'data' => $this->settingsPayload($conversation->fresh(), $request->user()),
            'message' => $isSelf ? 'You left the group.' : 'Member removed.',
        ]);
    }

    /**
     * Rotate the link that lands new people in this group.
     */
    public function inviteLink(Request $request, Conversation $conversation): JsonResponse
    {
        $this->requireGroupAdmin($request, $conversation);

        $conversation->invite_token = Str::random(40);
        $conversation->save();

        return response()->json(['data' => $this->settingsPayload($conversation, $request->user())]);
    }

    /**
     * Redeem an invite link. Invite-only groups queue the person for approval.
     */
    public function join(Request $request, string $token): JsonResponse
    {
        $conversation = Conversation::where('invite_token', $token)->firstOrFail();
        $user = $request->user();

        if ($conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'status' => 'joined',
                'conversation_id' => $conversation->id,
                'message' => 'You are already in this group.',
            ]);
        }

        if ($conversation->invite_only) {
            ConversationInvite::updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $user->id],
                ['inviter_id' => null, 'status' => ConversationInvite::STATUS_PENDING],
            );

            return response()->json([
                'status' => 'pending',
                'conversation_id' => $conversation->id,
                'message' => 'Request sent. An admin has to approve you before you can see the group.',
            ]);
        }

        $this->attachMember($conversation, $user);

        $this->postSystemNotice($conversation, $user, sprintf('%s joined the group chat', $this->nameOf($user)));

        return response()->json([
            'status' => 'joined',
            'conversation_id' => $conversation->id,
            'message' => 'You joined the group.',
        ]);
    }

    public function approveInvite(Request $request, Conversation $conversation, ConversationInvite $invite): JsonResponse
    {
        $this->requireGroupAdmin($request, $conversation);

        abort_unless((int) $invite->conversation_id === (int) $conversation->id, 404);

        $invite->update(['status' => ConversationInvite::STATUS_ACCEPTED]);
        $this->attachMember($conversation, $invite->user);

        if ($invite->user) {
            $this->postSystemNotice($conversation, $request->user(), sprintf(
                '%s approved %s to join the group chat',
                $this->nameOf($request->user()),
                $this->nameOf($invite->user),
            ));
        }

        return response()->json([
            'data' => $this->settingsPayload($conversation->fresh(), $request->user()),
            'message' => 'Member approved.',
        ]);
    }

    public function declineInvite(Request $request, Conversation $conversation, ConversationInvite $invite): JsonResponse
    {
        $this->requireGroupAdmin($request, $conversation);

        abort_unless((int) $invite->conversation_id === (int) $conversation->id, 404);

        $invite->update(['status' => ConversationInvite::STATUS_DECLINED]);

        return response()->json([
            'data' => $this->settingsPayload($conversation->fresh(), $request->user()),
            'message' => 'Request declined.',
        ]);
    }

    /**
     * Search this conversation's history.
     */
    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        $this->requireParticipant($request, $conversation);

        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $messages = $conversation->messages()
            ->with(ChatMessageFormatter::relations())
            ->whereNull('deleted_at')
            ->where('body', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $messages->map(fn ($message) => ChatMessageFormatter::format($message, $request->user()->id))->values(),
        ]);
    }

    /**
     * Media, files and links shared in this conversation.
     */
    public function shared(Request $request, Conversation $conversation): JsonResponse
    {
        $this->requireParticipant($request, $conversation);

        $type = (string) $request->query('type', 'media');

        $query = $conversation->messages()
            ->with(ChatMessageFormatter::relations())
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(100);

        match ($type) {
            'files' => $query->whereHas('mediaItems', function ($media) {
                $media->where('media_type', 'not like', 'image/%')
                    ->where('media_type', 'not like', 'video/%')
                    ->where('media_type', 'not like', 'audio/%');
            }),
            'links' => $query->where(function ($links) {
                $links->where('body', 'like', '%http%')
                    ->orWhereNotNull('shared_bleep_id');
            }),
            default => $query->whereHas('mediaItems', function ($media) {
                $media->where('media_type', 'like', 'image/%')
                    ->orWhere('media_type', 'like', 'video/%');
            }),
        };

        return response()->json([
            'data' => $query->get()
                ->map(fn ($message) => ChatMessageFormatter::format($message, $request->user()->id))
                ->values(),
        ]);
    }

    private function attachMember(Conversation $conversation, User $user): void
    {
        if ($conversation->participants()->where('users.id', $user->id)->exists()) {
            return;
        }

        $conversation->participants()->attach($user->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    private function requireParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->where('users.id', $request->user()->id)->exists(),
            403,
        );
    }

    private function requireGroupAdmin(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->is_group, 422, 'This is not a group chat.');

        $pivot = $conversation->participants()
            ->where('users.id', $request->user()->id)
            ->first()?->pivot;

        if (! $pivot || $pivot->left_at !== null || $pivot->role !== 'admin') {
            throw new HttpException(403, 'Only group admins can do that.');
        }
    }

    private function nameOf(?User $user): string
    {
        return $user?->dname ?: ($user?->username ?? 'Someone');
    }

    /**
     * Group changes read as system messages, so sidebar previews can say
     * "Alex added Sam to the group chat" instead of showing a raw media label.
     */
    private function postSystemNotice(Conversation $conversation, User $actor, string $body): void
    {
        $message = $conversation->messages()->create([
            'sender_id' => $actor->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $formatted = $message->fresh(ChatMessageFormatter::relations());
        $payload = ChatMessageFormatter::format($formatted, $actor->id);

        $this->broadcasts->dispatch(new MessageSent($conversation->id, $payload), toOthers: true);

        foreach ($conversation->participants()->where('users.id', '!=', $actor->id)->pluck('users.id') as $recipientId) {
            $this->broadcasts->dispatch(new MessageDelivered((int) $recipientId, $conversation->id, $payload));
        }

        foreach ($conversation->participants()->pluck('users.id') as $participantId) {
            $this->broadcasts->dispatch(new ConversationUpdated((int) $participantId, $conversation->id));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(Conversation $conversation, User $viewer): array
    {
        $participants = $conversation->participants()->get();

        $viewerPivot = $participants->firstWhere('id', $viewer->id)?->pivot;
        $isAdmin = $viewerPivot && $viewerPivot->left_at === null && $viewerPivot->role === 'admin';

        return [
            'id' => $conversation->id,
            'name' => $conversation->name,
            'is_group' => (bool) $conversation->is_group,
            'invite_only' => (bool) $conversation->invite_only,
            'role' => $viewerPivot?->role,
            'is_admin' => (bool) $isAdmin,
            'invite_token' => $isAdmin ? $conversation->invite_token : null,
            'invite_url' => $isAdmin && $conversation->invite_token
                ? route('chat.join', $conversation->invite_token)
                : null,
            'participants' => $participants->map(fn (User $participant) => [
                'id' => $participant->id,
                'username' => $participant->username,
                'dname' => $participant->dname,
                'profile_picture_url' => $participant->profile_picture_url,
                'role' => $participant->pivot->role,
                'left_at' => $participant->pivot->left_at
                    ? Carbon::parse($participant->pivot->left_at)->toIso8601String()
                    : null,
            ])->values(),
            'pending_invites' => $isAdmin
                ? $conversation->invites()
                    ->where('status', ConversationInvite::STATUS_PENDING)
                    ->with('user')
                    ->get()
                    ->map(fn (ConversationInvite $invite) => [
                        'id' => $invite->id,
                        'user_id' => $invite->user_id,
                        'username' => $invite->user?->username,
                        'dname' => $invite->user?->dname,
                        'profile_picture_url' => $invite->user?->profile_picture_url,
                        'requested_at' => optional($invite->created_at)->toIso8601String(),
                    ])->values()
                : [],
        ];
    }
}
