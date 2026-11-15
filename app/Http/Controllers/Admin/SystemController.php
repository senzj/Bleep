<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\Scripts\ScriptRunLog;
use App\Services\System\AttackDetector;
use App\Services\System\HealthReport;
use App\Services\System\LogFileReader;
use App\Services\System\ScheduleInspector;
use App\Services\System\SystemSettings;
use App\Services\System\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SystemController extends Controller
{
    /**
     * Admin-only server information, health checks, and editable settings.
     */
    public function index(SystemStatus $status): View
    {
        $definitions = SystemSettings::definitions();
        $grouped = [];

        foreach ($definitions as $key => $definition) {
            $grouped[$definition['group']][$key] = $definition;
        }

        return view('admin.system', [
            'groups' => $status->groups(),
            'definitions' => $definitions,
            'values' => SystemSettings::values(),
            'grouped' => $grouped,
        ]);
    }

    /**
     * Tail of the application log. Strictly limited to developers and admins;
     * moderators keep their own activity-log view only.
     */
    public function logs(Request $request, LogFileReader $reader): View
    {
        $this->ensureDeveloperOrAdmin($request);

        $levels = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        $level = strtoupper((string) $request->query('level', ''));
        $level = in_array($level, $levels, true) ? $level : null;
        $search = trim((string) $request->query('q', ''));

        return view('admin.system-logs', [
            'entries' => $reader->entries(250, $level, $search !== '' ? $search : null),
            'levelCounts' => $reader->levelCounts(),
            'levels' => $levels,
            'level' => $level,
            'search' => $search,
            'exists' => $reader->exists(),
            'size' => $reader->size(),
            'modifiedAt' => $reader->modifiedAt(),
            'path' => $reader->path(),
        ]);
    }

    /**
     * Heuristic attack review built from activity logs, the application log
     * and dense session clusters.
     */
    public function attacks(Request $request, AttackDetector $detector): View
    {
        $this->ensureDeveloperOrAdmin($request);

        return view('admin.system-attacks', [
            'findings' => $detector->findings(),
            'summary' => $detector->summary(),
        ]);
    }

    /**
     * Every scheduled task, the runs due on a chosen date, and the latest
     * automated health report.
     */
    public function schedule(
        Request $request,
        ScheduleInspector $inspector,
        HealthReport $health,
        GeoIpService $geoIp,
        ScriptRunLog $scriptLog,
    ): View {
        $this->ensureDeveloperOrAdmin($request);

        $date = now();

        if ($request->filled('date')) {
            try {
                $date = Carbon::parse((string) $request->query('date'))->startOfDay();
            } catch (Throwable) {
                $date = now()->startOfDay();
            }
        }

        $report = $health->latest();
        $heartbeat = $scriptLog->heartbeatState();

        return view('admin.system-schedule', [
            'events' => $inspector->events(),
            'runs' => $inspector->runsOn($date),
            'date' => $date,
            'health' => $report,
            'healthCheckedAt' => $health->checkedAt($report),
            'geoip' => $geoIp->state(),
            'scheduler' => [
                'state' => $heartbeat,
                'at' => $scriptLog->heartbeatAt(),
                'age_minutes' => $scriptLog->heartbeatAgeMinutes(),
                'stale' => $scriptLog->heartbeatIsStale(),
                'stale_after_minutes' => max(1, (int) config('scripts.heartbeat_stale_minutes', 10)),
            ],
        ]);
    }

    /**
     * Run the health checks on demand from the Schedule tab.
     */
    public function runHealthCheck(Request $request, HealthReport $health): RedirectResponse
    {
        $this->ensureDeveloperOrAdmin($request);

        $result = $health->run();
        $health->save($result);

        $message = match ($result['status']) {
            'error' => 'Health check finished with errors. Review the failing checks below.',
            'warning' => 'Health check finished with warnings.',
            default => 'Health check passed. All checks are healthy.',
        };

        return redirect()
            ->route('admin.system.schedule')
            ->with($result['status'] === 'error' ? 'error' : 'success', $message);
    }

    private function ensureDeveloperOrAdmin(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['dev', 'admin'], true),
            403,
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (SystemSettings::definitions() as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'boolean' => ['sometimes', 'boolean'],
                'integer' => ['sometimes', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']],
                'select' => ['sometimes', 'string', Rule::in(array_keys($definition['options'] ?? []))],
                default => ['sometimes', 'nullable', 'string', 'max:255'],
            };
        }

        SystemSettings::update($request->validate($rules));

        return redirect()
            ->route('admin.system')
            ->with('success', 'System settings updated.');
    }

    /**
     * Copy the current personal defaults onto every existing account.
     */
    public function applyDefaults(): RedirectResponse
    {
        $updated = SystemSettings::applyDefaultsToExistingUsers();

        return redirect()
            ->route('admin.system')
            ->with('success', "Applied personal defaults to {$updated} accounts.");
    }
}
