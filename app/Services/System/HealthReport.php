<?php

namespace App\Services\System;

use App\Services\GeoIpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a small set of functional checks and stores the latest report on disk.
 *
 * The report is intentionally pragmatic: it verifies the things that silently
 * break a deployment (database, cache, writable storage, queues, Reverb,
 * GeoIP freshness, log writing) instead of reporting static versions.
 */
class HealthReport
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (string) config('system.health_path');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array{
     *     checked_at: string,
     *     status: string,
     *     checks: array<int, array{key: string, label: string, status: string, detail: string}>
     * }|null
     */
    public function latest(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $report = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($report) || ! isset($report['checks'], $report['status'], $report['checked_at'])) {
            return null;
        }

        return $report;
    }

    /**
     * @return array{
     *     checked_at: string,
     *     status: string,
     *     checks: array<int, array{key: string, label: string, status: string, detail: string}>
     * }
     */
    public function run(): array
    {
        $checks = [
            $this->applicationCheck(),
            $this->databaseCheck(),
            $this->cacheCheck(),
            $this->storageCheck(),
            $this->queueCheck(),
            $this->broadcastCheck(),
            $this->geoIpCheck(),
            $this->logCheck(),
        ];

        return [
            'checked_at' => now()->toIso8601String(),
            'status' => $this->overallStatus($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @param  array{checked_at: string, status: string, checks: array<int, array{key: string, label: string, status: string, detail: string}>}  $report
     */
    public function save(array $report): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        $temporary = $this->path.'.tmp';
        File::put($temporary, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::delete($this->path);
        File::move($temporary, $this->path);
    }

    /**
     * @param  array<int, array{key: string, label: string, status: string, detail: string}>  $checks
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function applicationCheck(): array
    {
        $missing = [];

        if (config('app.key') === null || config('app.key') === '') {
            $missing[] = 'APP_KEY';
        }

        if (config('app.url') === null || config('app.url') === '') {
            $missing[] = 'APP_URL';
        }

        return $this->check(
            'application',
            'Application',
            $missing === [] ? 'ok' : 'error',
            $missing === []
                ? 'Environment '.config('app.env').' with a signing key configured.'
                : 'Missing required configuration: '.implode(', ', $missing).'.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function databaseCheck(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            return $this->check('database', 'Database', 'error', Str::limit($exception->getMessage(), 180));
        }

        return $this->check(
            'database',
            'Database',
            'ok',
            'Connected to the '.config('database.default').' connection in '.round((microtime(true) - $started) * 1000).' ms.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function cacheCheck(): array
    {
        $key = 'system-health:'.Str::random(12);

        try {
            Cache::put($key, 'ok', 30);
            $value = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $exception) {
            return $this->check('cache', 'Cache', 'error', Str::limit($exception->getMessage(), 180));
        }

        return $value === 'ok'
            ? $this->check('cache', 'Cache', 'ok', 'The '.config('cache.default').' store accepted a write and returned it.')
            : $this->check('cache', 'Cache', 'error', 'The cache store did not return the value that was just written.');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function storageCheck(): array
    {
        $file = 'system/health-'.Str::random(12).'.txt';

        try {
            Storage::disk('local')->put($file, 'ok');
            $value = Storage::disk('local')->get($file);
            Storage::disk('local')->delete($file);
        } catch (Throwable $exception) {
            return $this->check('storage', 'Storage', 'error', Str::limit($exception->getMessage(), 180));
        }

        return $value === 'ok'
            ? $this->check('storage', 'Storage', 'ok', 'The local disk is writable and readable.')
            : $this->check('storage', 'Storage', 'error', 'The local disk did not return the file that was just written.');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function queueCheck(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return $this->check('queue', 'Queue', 'ok', 'Jobs run inline (sync driver).');
        }

        return $this->check('queue', 'Queue', 'ok', 'Configured driver: '.$connection.'.');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function broadcastCheck(): array
    {
        $connection = (string) config('broadcasting.default');

        if ($connection !== 'reverb') {
            return $this->check('broadcasting', 'Broadcasting', 'ok', 'Configured connection: '.$connection.'.');
        }

        if (app()->runningUnitTests()) {
            return $this->check('broadcasting', 'Broadcasting', 'ok', 'Reverb network probe skipped in tests.');
        }

        $options = (array) config('broadcasting.connections.reverb.options', []);
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $host = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;
        $port = (int) ($options['port'] ?? 443);
        $scheme = (string) ($options['scheme'] ?? 'https');

        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 2);

        if ($socket === false) {
            return $this->check(
                'broadcasting',
                'Broadcasting',
                'warning',
                "Reverb is not reachable at {$scheme}://{$host}:{$port} (".($errorMessage ?: 'connection failed').').',
            );
        }

        fclose($socket);

        return $this->check('broadcasting', 'Broadcasting', 'ok', "Reverb is accepting connections at {$scheme}://{$host}:{$port}.");
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function geoIpCheck(): array
    {
        $state = app(GeoIpService::class)->state();

        if (! $state['exists']) {
            return $this->check(
                'geoip',
                'GeoIP database',
                'warning',
                'No .mmdb file found. Run "php artisan geoip:update" or upload a DB-IP City Lite database.',
            );
        }

        $size = $state['size'] >= 1024 * 1024
            ? number_format($state['size'] / 1024 / 1024, 1).' MB'
            : number_format($state['size'] / 1024, 1).' KB';

        $detail = basename((string) $state['path']).' · '.$size.' · updated '
            .($state['modified_at']?->diffForHumans() ?? 'unknown');

        if ($state['stale']) {
            return $this->check(
                'geoip',
                'GeoIP database',
                'warning',
                $detail.'. The database is '.$state['age_days'].' days old; run "php artisan geoip:update".',
            );
        }

        return $this->check('geoip', 'GeoIP database', 'ok', $detail.'.');
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function logCheck(): array
    {
        $directory = dirname((string) config('system.log_path'));

        if (! is_dir($directory)) {
            return $this->check('logs', 'Application log', 'error', 'The log directory does not exist: '.$directory);
        }

        if (! is_writable($directory)) {
            return $this->check('logs', 'Application log', 'error', 'The log directory is not writable: '.$directory);
        }

        return $this->check('logs', 'Application log', 'ok', 'Log directory is writable: '.$directory);
    }

    /**
     * @param  array<int, array{status: string}>  $checks
     */
    private function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('error', $statuses, true)) {
            return 'error';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Human label for a stored check timestamp.
     */
    public function checkedAt(?array $report): ?Carbon
    {
        if ($report === null) {
            return null;
        }

        try {
            return Carbon::parse($report['checked_at']);
        } catch (Throwable) {
            return null;
        }
    }
}
