<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use App\Models\Reports;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    // User submits a report
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bleep_id' => ['required', 'exists:bleeps,id'],
            'category' => ['required', 'in:spam,harassment,hate,nsfw,illegal,other'],
            'reason'   => ['required', 'string', 'max:500'],
        ]);

        $bleep = Bleep::findOrFail($validated['bleep_id']);

        // Self-report check
        if ($bleep->user_id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot report your own bleep.',
                'self_report' => true,
            ], 422);
        }

        // Duplicate report
        $existing = Reports::where('bleep_id', $validated['bleep_id'])
            ->where('reporter_id', Auth::id())
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reported this bleep.',
                'duplicate' => true,
            ], 409);
        }

        Reports::create([
            'bleep_id'    => $validated['bleep_id'],
            'reporter_id' => Auth::id(),
            'category'    => $validated['category'],
            'reason'      => $validated['reason'],
            'status'      => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully.'], 201);
    }

    // Admin/Mod dashboard
    public function index(Request $request)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $status = $request->query('status', 'pending');

        $reports = Reports::with(['bleep.user', 'reporter', 'reviewer'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.reports', compact('reports', 'status'));
    }

    // Delete bleep and optionally ban OP
    public function deleteBleep(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'ban_op' => ['nullable', 'boolean'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $bleep = $report->bleep;
        $op = $bleep->user;

        // Delete bleep
        $bleep->delete();

        // Ban OP if requested
        if ($validated['ban_op'] ?? false) {
            $bannedUntil = null;
            if (!empty($validated['banned_until'])) {
                $bannedUntil = \Carbon\Carbon::parse($validated['banned_until'])
                    ->setTimezone(config('app.timezone'));
            }

            $op->update([
                'is_banned'    => true,
                'banned_until' => $bannedUntil, // permanent
                'ban_reason'   => 'Violated community guidelines (reported content)',
            ]);
        }

        // Mark report as resolved
        $report->update([
            'status'       => 'resolved',
            'action_taken' => $validated['ban_op'] ? 'op_banned' : 'bleep_deleted',
            'reviewed_at'  => now(),
            'reviewed_by'  => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Bleep deleted and report resolved.']);
    }

    // Ban reporter (spam/abuse)
    public function banReporter(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reporter = $report->reporter;

        $bannedUntil = null;
        if (!empty($validated['banned_until'])) {
            $bannedUntil = \Carbon\Carbon::parse($validated['banned_until'])
                ->setTimezone(config('app.timezone'));
        }

        $reporter->update([
            'is_banned'    => true,
            'banned_until' => $bannedUntil,
            'ban_reason'   => 'Abuse of report system',
        ]);

        $report->update([
            'status'       => 'resolved',
            'action_taken' => 'reporter_banned',
            'reviewed_at'  => now(),
            'reviewed_by'  => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Reporter banned and report resolved.']);
    }

    // Dismiss report (no action)
    public function dismiss(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update([
            'status'       => 'resolved',
            'action_taken' => 'none',
            'reviewed_at'  => now(),
            'reviewed_by'  => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Report dismissed.']);
    }

    // Mark as reviewed (no action yet)
    public function markReviewed(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update([
            'status'       => 'reviewed',
            'reviewed_at'  => now(),
            'reviewed_by'  => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Report marked as reviewed.']);
    }
}
