<?php

namespace App\Services\Scripts;

/**
 * Renders the small HTML/JSON pages returned by public/scripts/*.php.
 *
 * The pages are deliberately dependency-free: no Blade, no Vite, no layout,
 * so they still render when the app's frontend build is broken.
 */
class ScriptResponse
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function result(array $result): string
    {
        $status = (string) ($result['status'] ?? 'error');
        $badge = $this->badge($status);
        $message = $this->escape((string) ($result['message'] ?? ''));
        $task = $this->escape((string) ($result['task'] ?? 'task'));

        $meta = $this->kv([
            'Started' => (string) ($result['started_at'] ?? 'unknown'),
            'Duration' => isset($result['duration']) ? $result['duration'].' s' : 'unknown',
            'Request IP' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ]);

        $commands = '';

        foreach ((array) ($result['commands'] ?? []) as $command) {
            $label = $this->escape((string) ($command['command'] ?? 'command'));
            $exit = (int) ($command['exit_code'] ?? 0);
            $output = trim((string) ($command['output'] ?? ''));
            $exitBadge = $this->badge($exit === 0 ? 'ok' : 'error');
            $output = $output === '' ? '(no output)' : $this->escape($output);

            $commands .= '<div class="cmd">$ php artisan '.$label.' '.$exitBadge
                .' <span class="exit">exit '.$exit.'</span></div>'
                .'<pre>'.$output.'</pre>';
        }

        $help = in_array($status, ['forbidden', 'misconfigured'], true)
            ? '<p class="hint">Pass the script key as <code>?key=…</code> or the <code>X-Script-Key</code> header.</p>'
            : '';

        return $this->page($task, <<<HTML
            <h1>{$task}</h1>
            <p class="sub">{$badge} <span class="message">{$message}</span></p>
            {$help}
            <div class="card">{$meta}</div>
            {$commands}
            <p class="footer">Bleep URL task runner &middot; responses are not cached &middot; add <code>?format=json</code> for machines.</p>
            HTML);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function status(array $snapshot): string
    {
        $app = (array) ($snapshot['app'] ?? []);
        $scheduler = (array) ($snapshot['scheduler'] ?? []);
        $health = (array) ($snapshot['health'] ?? []);
        $geoip = (array) ($snapshot['geoip'] ?? []);
        $reverb = (array) ($snapshot['reverb'] ?? []);
        $queue = (array) ($snapshot['queue'] ?? []);
        $scripts = (array) ($snapshot['scripts'] ?? []);
        $scheduled = (array) ($snapshot['scheduled_tasks'] ?? []);

        $appCard = '<div class="card"><h2>Application</h2>'.$this->kv([
            'Environment' => ($app['environment'] ?? 'unknown').(($app['debug'] ?? false) ? ' (debug on)' : ''),
            'Laravel' => (string) ($app['laravel'] ?? 'unknown'),
            'PHP' => (string) ($app['php'] ?? PHP_VERSION),
            'URL' => (string) ($app['url'] ?? 'unknown'),
            'Timezone' => (string) ($app['timezone'] ?? 'unknown'),
            'Server time' => (string) ($app['now'] ?? 'unknown'),
        ]).'</div>';

        $heartbeatAt = $scheduler['at'] ?? null;
        $heartbeatBadge = $this->badge(($scheduler['healthy'] ?? false) ? 'ok' : 'warning');
        $schedulerBody = $heartbeatAt
            ? $this->kv([
                'Last triggered' => (string) $heartbeatAt,
                'Age' => ($scheduler['age_minutes'] ?? '?').' minutes ago',
                'Last status' => (string) ($scheduler['status'] ?? 'unknown'),
                'Detail' => (string) ($scheduler['message'] ?? ''),
            ])
            : '<p>No trigger recorded yet. Point a free URL pinger at <code>/scripts/run-scheduler.php?key=…</code> every minute.</p>';

        $schedulerCard = '<div class="card"><h2>Scheduler trigger '.$heartbeatBadge.'</h2>'.$schedulerBody
            .'<p class="hint">Free hosts often have no cron. A one-minute URL pinger is what keeps daily and monthly tasks on time.</p></div>';

        $healthStatus = (string) ($health['status'] ?? '');
        $healthBadge = $this->badge($healthStatus === 'ok' ? 'ok' : ($healthStatus === '' ? 'muted' : 'warning'));
        $checks = '';

        foreach ((array) ($health['checks'] ?? []) as $check) {
            $checkStatus = (string) ($check['status'] ?? 'unknown');
            $checks .= '<tr><td>'.$this->escape((string) ($check['label'] ?? '')).'</td>'
                .'<td>'.$this->badge($checkStatus).'</td>'
                .'<td>'.$this->escape((string) ($check['detail'] ?? '')).'</td></tr>';
        }

        $healthCard = '<div class="card"><h2>Latest health check '.$healthBadge.'</h2>'
            .'<p class="sub">'.(! empty($health['checked_at']) ? 'Checked '.$this->escape((string) $health['checked_at']) : 'No report yet. Run <code>/scripts/system-health.php</code>.').'</p>'
            .($checks === '' ? '' : '<table><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>'.$checks.'</tbody></table>')
            .'</div>';

        $geoipStatus = ($geoip['exists'] ?? false)
            ? (($geoip['stale'] ?? false) ? 'warning' : 'ok')
            : 'warning';

        $geoipCard = '<div class="card"><h2>GeoIP database '.$this->badge($geoipStatus).'</h2>'
            .$this->kv([
                'File' => ! empty($geoip['path']) ? basename((string) $geoip['path']) : 'not installed',
                'Size' => isset($geoip['size']) ? number_format(((int) $geoip['size']) / 1024 / 1024, 1).' MB' : 'unknown',
                'Updated' => $geoip['modified_at'] ?? 'unknown',
                'Age' => isset($geoip['age_days']) && $geoip['age_days'] !== null ? $geoip['age_days'].' days' : 'unknown',
                'Build epoch' => $geoip['build_epoch'] ?? 'unknown',
            ]).'</div>';

        $reverbStatus = ($reverb['reachable'] ?? false)
            ? 'ok'
            : (($reverb['configured'] ?? false) ? 'warning' : 'muted');

        $reverbCard = '<div class="card"><h2>Realtime (Reverb) '.$this->badge($reverbStatus).'</h2>'
            .$this->kv([
                'Connection' => (string) ($reverb['connection'] ?? 'unknown'),
                'Endpoint' => (string) ($reverb['endpoint'] ?? 'not configured'),
                'Reachable' => $reverb['reachable'] === null ? 'not probed' : (($reverb['reachable'] ?? false) ? 'yes' : 'no'),
                'Detail' => (string) ($reverb['message'] ?? ''),
            ])
            .'<p class="hint">A shared/free host cannot keep a WebSocket server alive. The app falls back to polling, so this is informational.</p></div>';

        $queueCard = '<div class="card"><h2>Queue</h2>'.$this->kv([
            'Connection' => (string) ($queue['connection'] ?? 'unknown'),
            'Pending jobs' => $queue['pending'] === null ? 'not counted' : (string) $queue['pending'],
            'Failed jobs' => $queue['failed'] === null ? 'not counted' : (string) $queue['failed'],
        ]).'</div>';

        $scriptRows = '';

        foreach (array_slice($scripts, 0, 10) as $entry) {
            $scriptRows .= '<tr><td>'.$this->escape((string) ($entry['started_at'] ?? '')).'</td>'
                .'<td>'.$this->escape((string) ($entry['task'] ?? '')).'</td>'
                .'<td>'.$this->badge((string) ($entry['status'] ?? 'error')).'</td>'
                .'<td>'.$this->escape((string) ($entry['message'] ?? '')).'</td></tr>';
        }

        $scriptsCard = '<div class="card"><h2>Recent URL scripts</h2>'
            .($scriptRows === '' ? '<p>Nothing has run yet.</p>' : '<table><thead><tr><th>Started</th><th>Task</th><th>Status</th><th>Detail</th></tr></thead><tbody>'.$scriptRows.'</tbody></table>')
            .'</div>';

        $taskRows = '';

        foreach (array_slice($scheduled, 0, 10) as $entry) {
            $taskRows .= '<tr><td>'.$this->escape((string) ($entry['ran_at'] ?? '')).'</td>'
                .'<td>'.$this->escape((string) ($entry['description'] ?? '')).'</td>'
                .'<td>'.$this->badge((string) ($entry['status'] ?? 'unknown')).'</td>'
                .'<td>'.$this->escape((string) ($entry['runtime'] ?? '')).'</td></tr>';
        }

        $scheduledCard = '<div class="card"><h2>Recent scheduled tasks</h2>'
            .($taskRows === '' ? '<p>No scheduled task has recorded a run yet.</p>' : '<table><thead><tr><th>Ran at</th><th>Task</th><th>Status</th><th>Runtime</th></tr></thead><tbody>'.$taskRows.'</tbody></table>')
            .'</div>';

        return $this->page('System status', $appCard.$schedulerCard.$healthCard.$geoipCard.$reverbCard.$queueCard.$scriptsCard.$scheduledCard
            .'<p class="footer">Generated '.$this->escape((string) ($snapshot['generated_at'] ?? now()->toIso8601String())).' &middot; add <code>?format=json</code> for machines.</p>');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function json(array $payload): string
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{"error":"Unable to encode the response."}';
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    private function kv(array $rows): string
    {
        $html = '<dl class="kv">';

        foreach ($rows as $label => $value) {
            $html .= '<dt>'.$this->escape((string) $label).'</dt><dd>'.$this->escape((string) $value).'</dd>';
        }

        return $html.'</dl>';
    }

    private function badge(string $status): string
    {
        $class = match ($status) {
            'ok', 'success' => 'ok',
            'warning', 'warn', 'skipped' => 'warning',
            'error', 'failed', 'forbidden', 'busy', 'misconfigured' => 'error',
            default => 'muted',
        };

        $label = $status === '' ? 'unknown' : $status;

        return '<span class="badge '.$class.'">'.$this->escape($label).'</span>';
    }

    private function page(string $title, string $body): string
    {
        $title = $this->escape($title);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex, nofollow">
                <title>{$title} · Bleep</title>
                <style>
                    :root{color-scheme:dark}
                    *{box-sizing:border-box}
                    body{margin:0;padding:2rem 1rem;background:#0f1115;color:#e5e7eb;font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
                    main{max-width:980px;margin:0 auto}
                    h1{font-size:1.35rem;margin:0 0 .35rem;color:#f9fafb}
                    h2{font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;margin:0 0 .9rem}
                    .sub{color:#9ca3af;font-size:.82rem;margin:0 0 1.25rem}
                    .message{color:#e5e7eb}
                    .badge{display:inline-block;padding:.12rem .5rem;border-radius:999px;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
                    .badge.ok{background:#052e16;color:#4ade80;border:1px solid #166534}
                    .badge.error{background:#3f1212;color:#f87171;border:1px solid #7f1d1d}
                    .badge.warning{background:#3b2a06;color:#fbbf24;border:1px solid #92400e}
                    .badge.muted{background:#1f2937;color:#9ca3af;border:1px solid #374151}
                    .card{background:#161920;border:1px solid #262b36;border-radius:10px;padding:1rem 1.15rem;margin:0 0 1rem}
                    .cmd{color:#93c5fd;font-weight:700;margin:1.25rem 0 0;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
                    .exit{color:#6b7280;font-weight:400;font-size:.72rem}
                    pre{background:#0a0c10;border-left:3px solid #334155;border-radius:0 6px 6px 0;padding:.8rem 1rem;white-space:pre-wrap;word-break:break-word;margin:.45rem 0 0;font-size:.8rem;color:#d1d5db}
                    table{width:100%;border-collapse:collapse;font-size:.8rem}
                    th,td{text-align:left;padding:.42rem .5rem;border-bottom:1px solid #262b36;vertical-align:top}
                    th{color:#9ca3af;font-weight:600;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em}
                    tr:last-child td{border-bottom:0}
                    .kv{display:grid;grid-template-columns:minmax(130px,210px) 1fr;gap:.35rem 1rem;margin:0;font-size:.82rem}
                    .kv dt{color:#9ca3af}
                    .kv dd{margin:0;word-break:break-word}
                    .hint,.footer{color:#6b7280;font-size:.74rem;margin:.9rem 0 0}
                    code{background:#1f2937;border-radius:4px;padding:.1rem .3rem;color:#e5e7eb}
                    a{color:#93c5fd}
                    @media (max-width:640px){body{padding:1.25rem .75rem}.kv{grid-template-columns:1fr}.kv dt{margin-top:.4rem}}
                </style>
            </head>
            <body>
                <main>{$body}</main>
            </body>
            </html>
            HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
