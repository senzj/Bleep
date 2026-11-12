<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemStatus
{
    /**
     * Runtime diagnostics grouped for the admin system page.
     *
     * @return array<int, array{
     *     title: string,
     *     icon: string,
     *     items: array<int, array{label: string, value: string, status: string, note: string|null}>
     * }>
     */
    public function groups(): array
    {
        return [
            $this->runtimeGroup(),
            $this->frameworkGroup(),
            $this->databaseGroup(),
            $this->storageGroup(),
            $this->queueGroup(),
            $this->broadcastingGroup(),
            $this->uploadsGroup(),
            $this->mailGroup(),
        ];
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function runtimeGroup(): array
    {
        $required = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl'];
        $optional = ['gd', 'imagick', 'intl', 'zip', 'bcmath', 'redis', 'pcntl'];
        $missingRequired = array_values(array_filter($required, fn (string $extension) => ! extension_loaded($extension)));
        $missingOptional = array_values(array_filter($optional, fn (string $extension) => ! extension_loaded($extension)));

        return $this->group('PHP runtime', 'cpu', [
            $this->item(
                'PHP version',
                PHP_VERSION,
                version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'error',
                version_compare(PHP_VERSION, '8.3.0', '>=') ? null : 'Bleep requires PHP 8.3 or newer.',
            ),
            $this->item('Server API', PHP_SAPI),
            $this->item('Operating system', PHP_OS_FAMILY.' '.php_uname('r').' ('.php_uname('m').')'),
            $this->item('Memory limit', (string) ini_get('memory_limit')),
            $this->item('Max execution time', (string) ini_get('max_execution_time')),
            $this->item('OPcache', extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'Enabled' : 'Disabled', extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'ok' : 'warning'),
            $this->item('upload_max_filesize', (string) ini_get('upload_max_filesize')),
            $this->item('post_max_size', (string) ini_get('post_max_size')),
            $this->item(
                'Required extensions',
                $missingRequired === [] ? 'All present' : 'Missing: '.implode(', ', $missingRequired),
                $missingRequired === [] ? 'ok' : 'error',
            ),
            $this->item(
                'Optional extensions',
                $missingOptional === [] ? 'All present' : 'Missing: '.implode(', ', $missingOptional),
                $missingOptional === [] ? 'ok' : 'info',
            ),
        ]);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function frameworkGroup(): array
    {
        return $this->group('Laravel', 'settings', [
            $this->item('Laravel version', app()->version()),
            $this->item('Environment', (string) app()->environment()),
            $this->item('Debug mode', config('app.debug') ? 'Enabled' : 'Disabled', config('app.debug') ? 'warning' : 'ok'),
            $this->item('Application URL', (string) config('app.url')),
            $this->item('Timezone', (string) config('app.timezone')),
            $this->item('Locale', (string) config('app.locale')),
            $this->item('Cache store', (string) config('cache.default')),
            $this->item('Session driver', (string) config('session.driver')),
            $this->item(
                'Maintenance mode',
                config('app.maintenance_mode.enabled') ? 'Enabled' : 'Disabled',
                config('app.maintenance_mode.enabled') ? 'warning' : 'ok',
                config('app.maintenance_mode.enabled') ? 'Visitors see the maintenance page; staff can still sign in.' : null,
            ),
            $this->item(
                'Registration',
                config('app.registration_enabled', true) ? 'Open' : 'Closed',
                config('app.registration_enabled', true) ? 'ok' : 'warning',
                config('app.registration_enabled', true) ? null : 'New account sign-ups are disabled.',
            ),
        ]);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function databaseGroup(): array
    {
        $driver = (string) config('database.default');

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $version = match ($driver) {
                'mysql', 'mariadb' => $connection->selectOne('select version() as version')?->version,
                'pgsql' => $connection->selectOne('select version() as version')?->version,
                'sqlite' => $connection->selectOne('select sqlite_version() as version')?->version,
                default => null,
            };

            return $this->group('Database', 'database', [
                $this->item('Connection', $driver),
                $this->item('Server version', (string) ($version ?: 'Unknown')),
                $this->item('Status', 'Connected', 'ok'),
            ]);
        } catch (Throwable $exception) {
            return $this->group('Database', 'database', [
                $this->item('Connection', $driver),
                $this->item('Status', 'Unavailable', 'error', 'Database unavailable: '.$exception->getMessage()),
            ]);
        }
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function storageGroup(): array
    {
        $items = [];

        foreach (['local', 'public'] as $diskName) {
            $probe = '.system-check-'.Str::random(10);

            try {
                $disk = Storage::disk($diskName);
                $disk->put($probe, 'ok');
                $writable = $disk->exists($probe);
                $disk->delete($probe);

                $items[] = $this->item(
                    Str::ucfirst($diskName).' disk',
                    $writable ? 'Writable' : 'Not writable',
                    $writable ? 'ok' : 'error',
                );
            } catch (Throwable $exception) {
                $items[] = $this->item(
                    Str::ucfirst($diskName).' disk',
                    'Unavailable',
                    'error',
                    $exception->getMessage(),
                );
            }
        }

        $free = @disk_free_space(storage_path());
        $items[] = $this->item(
            'Free storage',
            $free === false ? 'Unknown' : $this->formatBytes((int) $free),
            $free !== false && $free < 512 * 1024 * 1024 ? 'warning' : 'ok',
            $free !== false && $free < 512 * 1024 * 1024 ? 'Less than 512 MB free.' : null,
        );

        return $this->group('Storage', 'hard-drive', $items);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function queueGroup(): array
    {
        $connection = (string) config('queue.default');
        $pending = null;
        $failed = null;

        try {
            $pending = DB::table('jobs')->count();
        } catch (Throwable) {
            // The queue table only exists for the database driver.
        }

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            // Failed jobs table may not be migrated yet.
        }

        return $this->group('Queue', 'list-checks', [
            $this->item('Connection', $connection),
            $this->item('Pending jobs', $pending === null ? 'Not tracked' : (string) $pending, 'info'),
            $this->item(
                'Failed jobs',
                $failed === null ? 'Not tracked' : (string) $failed,
                ($failed ?? 0) > 0 ? 'warning' : 'ok',
                ($failed ?? 0) > 0 ? 'Failed jobs are waiting for review.' : null,
            ),
        ]);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function broadcastingGroup(): array
    {
        $connection = (string) config('broadcasting.default');
        $items = [$this->item('Default connection', $connection)];

        if ($connection !== 'reverb') {
            $items[] = $this->item(
                'Reverb',
                'Not the default broadcaster',
                'info',
                'Broadcasting currently uses the "'.$connection.'" connection.',
            );

            return $this->group('Broadcasting', 'radio', $items);
        }

        $reverb = (array) config('broadcasting.connections.reverb', []);
        $appId = $reverb['app_id'] ?? null;
        $key = $reverb['key'] ?? null;
        $secret = $reverb['secret'] ?? null;
        $options = (array) ($reverb['options'] ?? []);
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $port = (int) ($options['port'] ?? 443);
        $scheme = (string) ($options['scheme'] ?? 'https');

        $items[] = $this->item('Endpoint', "{$scheme}://{$host}:{$port}");
        $items[] = $this->item('Application ID', $appId ? (string) $appId : 'Not configured', $appId ? 'ok' : 'error');

        if (! $appId || ! $key || ! $secret) {
            $items[] = $this->item(
                'Reverb status',
                'Unavailable',
                'error',
                'Reverb Unavailable: Server does not support this because REVERB_APP_ID, REVERB_APP_KEY, or REVERB_APP_SECRET is missing.',
            );

            return $this->group('Broadcasting', 'radio', $items);
        }

        if (app()->runningUnitTests()) {
            $items[] = $this->item(
                'Reverb status',
                'Configured',
                'info',
                'Connectivity checks are skipped while running the test suite.',
            );

            return $this->group('Broadcasting', 'radio', $items);
        }

        $probeHost = in_array($host, ['0.0.0.0', '::', '[::]'], true) ? '127.0.0.1' : $host;

        $health = Cache::remember('system:reverb:health:'.$probeHost.':'.$port, now()->addSeconds(30), function () use ($probeHost, $port): array {
            $errorNumber = 0;
            $errorMessage = '';
            $socket = @fsockopen($probeHost, $port, $errorNumber, $errorMessage, 0.3);

            if (is_resource($socket)) {
                fclose($socket);

                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $errorMessage ?: 'connection refused ('.$errorNumber.')'];
        });

        $missingPcntl = ! extension_loaded('pcntl');

        if ($health['ok']) {
            $items[] = $this->item(
                'Reverb status',
                'Reachable',
                $missingPcntl ? 'warning' : 'ok',
                $missingPcntl
                    ? 'The server is reachable, but this PHP runtime lacks ext-pcntl. Run "php artisan reverb:start" on a Unix host or WSL.'
                    : null,
            );
        } else {
            $items[] = $this->item(
                'Reverb status',
                'Unavailable',
                'error',
                'Reverb Unavailable: Server does not support this due to '.$health['error'].' at '.$probeHost.':'.$port.'.',
            );
        }

        return $this->group('Broadcasting', 'radio', $items);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function uploadsGroup(): array
    {
        $maxBleep = (int) config('media.max_bleep_file_kb', 2 * 1024 * 1024);
        $maxChat = (int) config('media.max_chat_file_kb', 2 * 1024 * 1024);
        $chunkSize = (int) config('media.chunk_size_kb', 8 * 1024);
        $threshold = (int) config('media.chunked_upload_threshold_kb', 8 * 1024);

        $uploadMax = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        $limitBytes = $maxBleep * 1024;

        $note = null;
        $status = 'ok';

        if ($uploadMax > 0 && $uploadMax < $limitBytes) {
            $status = 'warning';
            $note = 'PHP upload_max_filesize is smaller than the bleep limit, so large files will be rejected by PHP first.';
        } elseif ($postMax > 0 && $postMax < $limitBytes) {
            $status = 'warning';
            $note = 'PHP post_max_size is smaller than the bleep limit, so large uploads will be rejected before Laravel sees them.';
        }

        return $this->group('Uploads', 'upload-cloud', [
            $this->item('Bleep limit', $this->formatBytes($limitBytes), $status, $note),
            $this->item('Chat limit', $this->formatBytes($maxChat * 1024)),
            $this->item('Resumable threshold', $this->formatBytes($threshold * 1024)),
            $this->item('Chunk size', $this->formatBytes($chunkSize * 1024)),
            $this->item('PHP upload_max_filesize', (string) ini_get('upload_max_filesize')),
            $this->item('PHP post_max_size', (string) ini_get('post_max_size')),
        ]);
    }

    /**
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function mailGroup(): array
    {
        return $this->group('Mail', 'mail', [
            $this->item('Mailer', (string) config('mail.default')),
            $this->item('From address', (string) config('mail.from.address')),
            $this->item('From name', (string) config('mail.from.name')),
        ]);
    }

    /**
     * @param  array<int, array{label: string, value: string, status: string, note: string|null}>  $items
     * @return array{title: string, icon: string, items: array<int, array{label: string, value: string, status: string, note: string|null}>}
     */
    private function group(string $title, string $icon, array $items): array
    {
        return [
            'title' => $title,
            'icon' => $icon,
            'items' => $items,
        ];
    }

    /**
     * @return array{label: string, value: string, status: string, note: string|null}
     */
    private function item(string $label, string $value, string $status = 'ok', ?string $note = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'status' => $status,
            'note' => $note,
        ];
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = min(count($units) - 1, (int) floor(log($bytes, 1024)));

        return round($bytes / (1024 ** $index), 2).' '.$units[$index];
    }
}
