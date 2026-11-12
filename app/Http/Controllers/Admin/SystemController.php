<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
