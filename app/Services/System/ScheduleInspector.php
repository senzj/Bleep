<?php

namespace App\Services\System;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Read-only view of the application's scheduled tasks, for the admin System >
 * Schedule tab.
 *
 * The console kernel has to be bootstrapped first: that is what loads
 * routes/console.php and registers the events on the Schedule singleton.
 */
class ScheduleInspector
{
    public function __construct(private ScheduleRunRecorder $runs) {}

    /**
     * @return array<int, array{
     *     key: string,
     *     description: string,
     *     expression: string,
     *     human: string,
     *     next_run: Carbon|null,
     *     last_run: array<string, mixed>|null
     * }>
     */
    public function events(): array
    {
        return collect($this->schedule()->events())
            ->map(fn (Event $event): array => $this->describe($event))
            ->sortBy('next_run')
            ->values()
            ->all();
    }

    /**
     * Every scheduled task, with the times it would run on the given day.
     *
     * @return array<int, array{
     *     key: string,
     *     description: string,
     *     expression: string,
     *     times: array<int, string>,
     *     count: int
     * }>
     */
    public function runsOn(CarbonInterface $date): array
    {
        $start = Carbon::instance($date)->startOfDay();
        $end = Carbon::instance($date)->endOfDay();
        $grouped = [];

        foreach ($this->schedule()->events() as $event) {
            try {
                $cron = new CronExpression($event->getExpression());
            } catch (Throwable) {
                continue;
            }

            $key = $this->runs->keyFor($event);
            $cursor = $start->copy()->subMinute();

            for ($i = 0; $i < 1440; $i++) {
                try {
                    $next = Carbon::instance($cron->getNextRunDate($cursor, 0, false, $event->timezone));
                } catch (Throwable) {
                    break;
                }

                if ($next->greaterThan($end)) {
                    break;
                }

                $grouped[$key] ??= [
                    'key' => $key,
                    'description' => $this->description($event),
                    'expression' => $event->getExpression(),
                    'times' => [],
                ];

                $grouped[$key]['times'][] = $next->format('H:i');

                $cursor = $next->copy();
            }
        }

        $runs = array_values(array_map(fn (array $run): array => $run + [
            'count' => count($run['times']),
        ], $grouped));

        usort($runs, fn (array $a, array $b): int => $a['times'][0] <=> $b['times'][0]);

        return $runs;
    }

    /**
     * @return array{
     *     key: string,
     *     description: string,
     *     expression: string,
     *     human: string,
     *     next_run: Carbon|null,
     *     last_run: array<string, mixed>|null
     * }
     */
    private function describe(Event $event): array
    {
        $key = $this->runs->keyFor($event);

        return [
            'key' => $key,
            'description' => $this->description($event),
            'expression' => $event->getExpression(),
            'human' => $this->humanExpression($event->getExpression()),
            'next_run' => $this->nextRun($event),
            'last_run' => $this->runs->for($key),
        ];
    }

    /**
     * A readable task name: the explicit description when set, otherwise the
     * artisan portion of the built command without the PHP binary or redirect.
     */
    private function description(Event $event): string
    {
        if (is_string($event->description) && $event->description !== '') {
            return $event->description;
        }

        $command = (string) ($event->command ?? '');

        $command = preg_replace('/^.*?artisan"?\s*/i', '', $command) ?? $command;
        $command = preg_replace('/\s*>\s*"?NUL"?.*$/i', '', $command) ?? $command;
        $command = preg_replace('/\s*>\s*\/dev\/null.*$/i', '', $command) ?? $command;
        $command = preg_replace('/\s*2>&1\s*$/i', '', $command) ?? $command;

        return trim($command) !== '' ? trim($command) : $event->getSummaryForDisplay();
    }

    private function nextRun(Event $event): ?Carbon
    {
        try {
            return $event->nextRunDate();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A short human summary for the common schedules this app uses.
     */
    private function humanExpression(string $expression): string
    {
        if ($expression === '* * * * *') {
            return 'Every minute';
        }

        if ($expression === '0 * * * *') {
            return 'Hourly at :00';
        }

        if (preg_match('/^(\d{1,2}) (\d{1,2}) \* \* \*$/', $expression, $matches)) {
            return sprintf('Daily at %02d:%02d', (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/^(\d{1,2}) (\d{1,2}) (\d{1,2}) \* \*$/', $expression, $matches)) {
            return sprintf('Monthly on day %d at %02d:%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        return 'Cron '.$expression;
    }

    private function schedule(): Schedule
    {
        app(ConsoleKernel::class)->bootstrap();

        return app(Schedule::class);
    }
}
