<?php

namespace App\Http\Controllers;

use App\Enums\NotificationAudience;
use App\Models\Notification;
use App\Services\Notifications\NotificationPresenter;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Inbox page.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isStaff = $user->hasAdminAccess();
        $filter = (string) $request->query('filter', 'all');

        if ($filter === 'staff' && ! $isStaff) {
            $filter = 'all';
        }

        $query = $user->notifications()
            ->with('actor:id,username,dname,profile_picture');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        if ($filter === 'staff') {
            $query->whereIn('audience', [
                NotificationAudience::Moderator->value,
                NotificationAudience::Admin->value,
            ]);
        }

        $notifications = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $notifications->through(
            fn (Notification $notification) => NotificationPresenter::toArray($notification)
        );

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'isStaff' => $isStaff,
            'unreadCount' => $this->notifications->unreadCount($user),
        ]);
    }

    /**
     * Latest notifications for the bell dropdown.
     */
    public function data(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 8), 1), 30);

        $items = $request->user()->notifications()
            ->with('actor:id,username,dname,profile_picture')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Notification $notification) => NotificationPresenter::toArray($notification))
            ->values();

        return response()->json([
            'notifications' => $items,
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless(
            (int) $notification->user_id === (int) $request->user()->id,
            404
        );

        $notification->markRead();

        return response()->json([
            'ok' => true,
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notifications->markAllRead($request->user());

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'unread_count' => 0,
        ]);
    }
}
