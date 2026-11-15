<?php

namespace App\Services\Scripts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Small JSON ledger for URL-triggered maintenance scripts.
 *
 * History lives in a file instead of the cache so clearing caches does not
 * erase the record of what ran. The scheduler heartbeat is separate because
 * the admin Schedule tab and `system:health` check it on every request.
 */
class ScriptRunLog
{
    private const HISTORY_LIMIT = 100;

    /**
     * @param  array<string, mixed>  $result
     */
    public function record(array $result, ?string $ip = null): void
    {
        $entries = $this->read($this->runsPath());

        $entries[] = [
            'task' => (string) ($result['task'] ?? 'unknown'),
            'status' => (string) ($result['status'] ?? 'error'),
            'http' => (int) ($result['http'] ?? 500),
            'message' => Str::limit((string) ($result['message'] ?? ''), 300),
            'started_at' => $result['started_at'] ?? now()->toIso8601String(),
            'duration' => $result['duration'] ?? null,
            'ip' => $ip,
            'commands' => array_map(fn (array $command): array => [
                'command' => (string) ($command['command'] ?? ''),
                'exit_code' => (int) ($command['exit_code'] ?? 0),
                'output' => Str::limit((string) ($command['output'] ?? ''), 2000),
            ], array_values((array) ($result['commands'] ?? []))),
        ];

        $this->write($this->runsPath(), array_slice($entries, -self::HISTORY_LIMIT));
    }

    /**
     * Newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        return array_slice(array_reverse($this->read($this->runsPath())), 0, max(1, $limit));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function heartbeat(array $result): void
    {
        $this->write($this->heartbeatPath(), [
            'at' => now()->toIso8601String(),
            'status' => (string) ($result['status'] ?? 'error'),
            'message' => Str::limit((string) ($result['message'] ?? ''), 300),
            'duration' => $result['duration'] ?? null,
        ]);
    }

    /**
     * @return array{at: string, status: string, message: string, duration: float|null}|null
     */
    public function heartbeatState(): ?array
    {
        $state = $this->readOne($this->heartbeatPath());

        if ($state === null || ! isset($state['at'], $state['status'])) {
            return null;
        }

        return [
            'at' => (string) $state['at'],
            'status' => (string) $state['status'],
            'message' => (string) ($state['message'] ?? ''),
            'duration' => isset($state['duration']) ? (float) $state['duration'] : null,
        ];
    }

    public function heartbeatAt(): ?Carbon
    {
        $state = $this->heartbeatState();

        if ($state === null) {
            return null;
        }

        try {
            return Carbon::parse($state['at']);
        } catch (Throwable) {
            return null;
        }
    }

    public function heartbeatAgeMinutes(): ?int
    {
        $at = $this->heartbeatAt();

        return $at === null ? null : (int) $at->diffInMinutes(now());
    }

    public function heartbeatIsStale(): bool
    {
        $age = $this->heartbeatAgeMinutes();

        if ($age === null) {
            return true;
        }

        return $age > max(1, (int) config('scripts.heartbeat_stale_minutes', 10));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readOne(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function write(string $path, array $value): void
    {
        try {
            File::ensureDirectoryExists(dirname($path));

            $temporary = $path.'.tmp';
            File::put($temporary, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            File::delete($path);
            File::move($temporary, $path);
        } catch (Throwable) {
            // A read-only storage directory must never take a task down.
        }
    }

    private function runsPath(): string
    {
        return (string) config('scripts.runs_path');
    }

    private function heartbeatPath(): string
    {
        return (string) config('scripts.heartbeat_path');
    }
}
