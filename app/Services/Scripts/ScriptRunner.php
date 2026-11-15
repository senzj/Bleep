<?php

namespace App\Services\Scripts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared implementation for the public/scripts/*.php entry points.
 *
 * A script says which Artisan commands it runs and which config key protects
 * it. This class checks the key, prevents overlapping HTTP requests, catches
 * every Throwable, writes a history entry and renders a readable response.
 */
class ScriptRunner
{
    public function __construct(private ScriptRunLog $log, private ScriptResponse $response) {}

    /**
     * @param  array<int, array{command: string, arguments?: array<string, mixed>}>  $commands
     * @return array<string, mixed>
     */
    public function handle(
        string $task,
        array $commands,
        ?string $providedKey,
        string $configKey = 'scripts.task_key',
        bool $heartbeat = false,
        ?int $timeout = null,
    ): array {
        $startedAt = now();
        $started = microtime(true);

        $configuredKey = (string) config($configKey);

        if ($configuredKey === '') {
            return $this->finish($task, $startedAt, $started, 'misconfigured', 500, [
                'The script key is not configured. Set SCRIPT_SECRET_KEY in .env.',
            ]);
        }

        if (! is_string($providedKey) || $providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->finish($task, $startedAt, $started, 'forbidden', 403, [
                'Unauthorized: invalid or missing key.',
            ]);
        }

        if ($commands === []) {
            return $this->finish($task, $startedAt, $started, 'error', 500, [
                'No commands are configured for this script.',
            ]);
        }

        $limit = max(30, $timeout ?? (int) config('scripts.timeout', 120));

        if (function_exists('set_time_limit')) {
            @set_time_limit($limit);
        }

        @ignore_user_abort(true);

        $lock = $this->acquire($task);

        if ($lock === null) {
            return $this->finish($task, $startedAt, $started, 'busy', 409, [
                'Another run of this script is still in progress. Try again shortly.',
            ]);
        }

        $status = 'ok';
        $messages = [];
        $results = [];

        try {
            foreach ($commands as $specification) {
                $command = trim((string) ($specification['command'] ?? ''));
                $arguments = (array) ($specification['arguments'] ?? []);

                if ($command === '') {
                    continue;
                }

                $label = $this->commandLabel($command, $arguments);

                try {
                    $exitCode = Artisan::call($command, $arguments);
                    $output = trim(Artisan::output());
                } catch (Throwable $exception) {
                    $exitCode = 1;
                    $output = $exception->getMessage();

                    Log::error('URL script command failed', [
                        'task' => $task,
                        'command' => $command,
                        'exception' => $exception,
                    ]);
                }

                if ($exitCode !== 0) {
                    $status = 'error';
                    $messages[] = 'Command ['.$label.'] exited with code '.$exitCode.'.';
                }

                $results[] = [
                    'command' => $label,
                    'exit_code' => $exitCode,
                    'output' => Str::limit($output, 20000, "\n… (output truncated)"),
                ];
            }
        } finally {
            $this->release($lock);
        }

        if ($results === []) {
            return $this->finish($task, $startedAt, $started, 'error', 500, [
                'No usable commands were configured for this script.',
            ]);
        }

        $message = $status === 'ok'
            ? 'Task completed successfully.'
            : implode(' ', array_unique($messages));

        return $this->finish($task, $startedAt, $started, $status, $status === 'ok' ? 200 : 500, [
            $message,
        ], $results, $heartbeat);
    }

    /**
     * Reads the key from ?key=, ?secret= or the X-Script-Key header.
     *
     * Accepting a header means the key does not have to appear in URLs that
     * end up in access logs, which is useful for uptime pingers.
     */
    public function keyFromRequest(): ?string
    {
        foreach (['key', 'secret'] as $name) {
            if (isset($_GET[$name]) && is_string($_GET[$name]) && $_GET[$name] !== '') {
                return $_GET[$name];
            }
        }

        $header = $_SERVER['HTTP_X_SCRIPT_KEY'] ?? null;

        return is_string($header) && $header !== '' ? $header : null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function respond(array $result): never
    {
        $format = strtolower((string) ($_GET['format'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $asJson = $format === 'json' || ($format === '' && str_contains($accept, 'application/json'));

        header('X-Robots-Tag: noindex, nofollow');

        if ($asJson) {
            header('Content-Type: application/json; charset=UTF-8');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }

        http_response_code((int) ($result['http'] ?? 500));

        echo $asJson
            ? $this->response->json($result)
            : $this->response->result($result);

        exit;
    }

    /**
     * @param  array<int, string>  $messages
     * @param  array<int, array<string, mixed>>  $commands
     * @return array<string, mixed>
     */
    private function finish(
        string $task,
        Carbon $startedAt,
        float $started,
        string $status,
        int $http,
        array $messages,
        array $commands = [],
        bool $heartbeat = false,
    ): array {
        $result = [
            'task' => $task,
            'status' => $status,
            'http' => $http,
            'message' => implode(' ', array_unique(array_filter($messages))),
            'started_at' => $startedAt->toIso8601String(),
            'duration' => round(microtime(true) - $started, 2),
            'commands' => $commands,
        ];

        $this->log->record($result, $this->clientIp());

        if ($heartbeat) {
            $this->log->heartbeat($result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function commandLabel(string $command, array $arguments): string
    {
        $parts = [$command];

        foreach ($arguments as $name => $value) {
            if (is_int($name)) {
                $parts[] = (string) $value;

                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $parts[] = '--'.$name;

                continue;
            }

            $parts[] = '--'.$name.'='.(is_scalar($value) ? (string) $value : json_encode($value));
        }

        return implode(' ', $parts);
    }

    /**
     * @return resource|null
     */
    private function acquire(string $task)
    {
        try {
            $directory = (string) config('scripts.lock_path');
            File::ensureDirectoryExists($directory);

            $name = preg_replace('/[^a-z0-9_-]+/i', '-', $task) ?: 'task';
            $handle = fopen($directory.DIRECTORY_SEPARATOR.$name.'.lock', 'c');

            if ($handle === false) {
                return null;
            }

            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                return null;
            }

            return $handle;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  resource  $handle
     */
    private function release($handle): void
    {
        try {
            flock($handle, LOCK_UN);
            fclose($handle);
        } catch (Throwable) {
            // Nothing useful to do if the lock cannot be released.
        }
    }

    private function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
