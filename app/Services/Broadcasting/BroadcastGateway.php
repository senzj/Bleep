<?php

namespace App\Services\Broadcasting;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatches broadcast events without letting a broadcast transport failure
 * break the HTTP request that produced the change.
 *
 * Reverb/Pusher may be unreachable (shared hosting, local development, or a
 * temporary outage). The database write has already succeeded at that point,
 * so returning a 500 would leave the client thinking the action failed.
 */
class BroadcastGateway
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(ShouldBroadcast $event, bool $toOthers = false): void
    {
        try {
            if ($toOthers && method_exists($event, 'dontBroadcastToCurrentUser')) {
                $event->dontBroadcastToCurrentUser();
            }

            $this->events->dispatch($event);
        } catch (Throwable $exception) {
            Log::warning('Broadcast failed.', [
                'event' => $event::class,
                'connection' => config('broadcasting.default'),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
