<?php

namespace App\Services\Scripts;

use App\Services\GeoIpService;
use App\Services\System\HealthReport;
use App\Services\System\ScheduleRunRecorder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only facts for public/scripts/system-status.php.
 *
 * Every section answers a question the free-host operator actually has:
 * is the trigger still firing, is the database healthy, is GeoIP current,
 * can anything reach Reverb, and are queued jobs piling up?
 */
class SystemSnapshot
{
    public function __construct(
        private ScriptRunLog $scripts,
        private HealthReport $health,
        private GeoIpService $geoIp,
        private ScheduleRunRecorder $scheduled,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app' => $this->appSection(),
            'scheduler' => $this->schedulerSection(),
            'health' => $this->healthSection(),
            'geoip' => $this->geoipSection(),
            'reverb' => $this->reverbSection(),
            'queue' => $this->queueSection(),
            'scripts' => $this->scripts->recent(10),
            'scheduled_tasks' => $this->scheduledSection(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appSection(): array
    {
        return [
            'name' => (string) config('app.name'),
            'environment' => (string) config('app.env'),
            'debug' => (bool) config('app.debug'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone'),
            'now' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerSection(): array
    {
        $state = $this->scripts->heartbeatState();

        return [
            'at' => $state['at'] ?? null,
            'status' => $state['status'] ?? null,
            'message' => $state['message'] ?? null,
            'duration' => $state['duration'] ?? null,
            'age_minutes' => $this->scripts->heartbeatAgeMinutes(),
            'stale_after_minutes' => (int) config('scripts.heartbeat_stale_minutes', 10),
            'healthy' => ! $this->scripts->heartbeatIsStale(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSection(): array
    {
        $report = $this->health->latest();

        return [
            'status' => $report['status'] ?? null,
            'checked_at' => $this->health->checkedAt($report)?->toIso8601String(),
            'checks' => $report['checks'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function geoipSection(): array
    {
        $state = $this->geoIp->state();

        return [
            'exists' => $state['exists'],
            'path' => $state['path'],
            'size' => $state['size'],
            'modified_at' => $state['modified_at']?->toIso8601String(),
            'age_days' => $state['age_days'],
            'stale' => $state['stale'],
            'build_epoch' => $state['build_epoch'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reverbSection(): array
    {
        $connection = (string) config('broadcasting.default');

        if ($connection !== 'reverb') {
            return [
                'connection' => $connection,
                'configured' => false,
                'endpoint' => null,
                'reachable' => null,
                'message' => 'Broadcasting uses the "'.$connection.'" connection.',
            ];
        }

        $options = (array) config('broadcasting.connections.reverb.options', []);
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $host = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;
        $port = (int) ($options['port'] ?? 443);
        $scheme = (string) ($options['scheme'] ?? 'https');
        $endpoint = $scheme.'://'.$host.':'.$port;

        if (app()->runningUnitTests()) {
            return [
                'connection' => $connection,
                'configured' => true,
                'endpoint' => $endpoint,
                'reachable' => null,
                'message' => 'Network probe skipped in tests.',
            ];
        }

        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 2);

        if ($socket === false) {
            return [
                'connection' => $connection,
                'configured' => true,
                'endpoint' => $endpoint,
                'reachable' => false,
                'message' => 'Not reachable: '.($errorMessage ?: 'connection failed').'.',
            ];
        }

        fclose($socket);

        return [
            'connection' => $connection,
            'configured' => true,
            'endpoint' => $endpoint,
            'reachable' => true,
            'message' => 'Accepting connections.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueSection(): array
    {
        $connection = (string) config('queue.default');
        $pending = null;
        $failed = null;

        if ($connection === 'database') {
            try {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $pending = DB::table($table)->count();
            } catch (Throwable) {
                $pending = null;
            }

            try {
                $failed = DB::table('failed_jobs')->count();
            } catch (Throwable) {
                $failed = null;
            }
        }

        return [
            'connection' => $connection,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduledSection(): array
    {
        $rows = array_values($this->scheduled->all());

        usort($rows, fn (array $a, array $b): int => strcmp((string) ($b['ran_at'] ?? ''), (string) ($a['ran_at'] ?? '')));

        return array_slice($rows, 0, 10);
    }
}
