<?php

namespace App\Services\System;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists the outcome of scheduled tasks so the admin Schedule tab can show
 * whether each command actually succeeded on its last run.
 *
 * Results live in a small JSON file instead of the cache so clearing the cache
 * does not wipe the history.
 */
class ScheduleRunRecorder
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (string) config('system.schedule_runs_path');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Stable identifier for an event: expression plus display summary. It
     * survives container rebuilds, unlike the object hash.
     */
    public function keyFor(Event $event): string
    {
        return sha1($event->getExpression().'|'.$event->getSummaryForDisplay());
    }

    public function record(Event $event, string $status, ?float $runtime = null, ?string $message = null): void
    {
        $key = $this->keyFor($event);
        $runs = $this->all();

        $runs[$key] = [
            'description' => $event->getSummaryForDisplay(),
            'expression' => $event->getExpression(),
            'status' => $status,
            'runtime' => $runtime !== null ? round($runtime, 2) : null,
            'message' => $message !== null ? Str::limit($message, 500) : null,
            'ran_at' => now()->toIso8601String(),
        ];

        $this->write(array_slice($runs, -200, null, true));
    }

    /**
     * @return array<string, array{
     *     description: string,
     *     expression: string,
     *     status: string,
     *     runtime: float|null,
     *     message: string|null,
     *     ran_at: string
     * }>
     */
    public function all(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($this->path), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{
     *     description: string,
     *     expression: string,
     *     status: string,
     *     runtime: float|null,
     *     message: string|null,
     *     ran_at: string
     * }|null
     */
    public function for(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $runs
     */
    private function write(array $runs): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        $temporary = $this->path.'.tmp';
        File::put($temporary, json_encode($runs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::delete($this->path);
        File::move($temporary, $this->path);
    }

    /**
     * Parse a stored ISO timestamp.
     */
    public function ranAt(?array $run): ?Carbon
    {
        if ($run === null || ! isset($run['ran_at'])) {
            return null;
        }

        try {
            return Carbon::parse($run['ran_at']);
        } catch (Throwable) {
            return null;
        }
    }
}
