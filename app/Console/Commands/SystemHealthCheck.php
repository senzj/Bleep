<?php

namespace App\Console\Commands;

use App\Services\System\HealthReport;
use Illuminate\Console\Command;

/**
 * Runs the functional health checks and stores the result for the admin
 * System > Schedule tab. Exits non-zero when any check is in error, so cron
 * mail (or any uptime wrapper) notices a broken deployment.
 */
class SystemHealthCheck extends Command
{
    protected $signature = 'system:health {--json : Print the report as JSON instead of a table}';

    protected $description = 'Run database, cache, storage, Reverb and GeoIP health checks';

    public function handle(HealthReport $report): int
    {
        $result = $report->run();
        $report->save($result);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['status'] === 'error' ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['Check', 'Status', 'Detail'],
            array_map(fn (array $check): array => [
                $check['label'],
                strtoupper($check['status']),
                $check['detail'],
            ], $result['checks']),
        );

        $this->newLine();

        match ($result['status']) {
            'error' => $this->components->error('Health check finished with errors.'),
            'warning' => $this->components->warn('Health check finished with warnings.'),
            default => $this->components->info('All health checks passed.'),
        };

        $this->line('Report stored at '.$report->path());

        return $result['status'] === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
