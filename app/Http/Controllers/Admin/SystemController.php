<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\AttackDetector;
use App\Services\System\LogFileReader;
use App\Services\System\SystemSettings;
use App\Services\System\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
