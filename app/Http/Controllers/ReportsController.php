<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\Reports;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    // ── Strike → ban duration map
    // suspension_count is incremented each time the user is banned
    // Each ban resets strikes to 0; if no violations in 7 days, strikes reset
    private function computeBanDuration(User $user): Carbon
    {
        $count = $user->suspension_count; // how many times banned so far

        return match(true) {
            $count === 0 => now()->addDay(),        // 1st ban: 1 day
            $count === 1 => now()->addWeek(),       // 2nd ban: 1 week
            $count === 2 => now()->addMonth(),      // 3rd ban: 1 month
            $count === 3 => now()->addMonths(3),    // 4th ban: 3 months
            $count === 4 => now()->addMonths(6),    // 5th ban: 6 months
            default      => now()->addYear(),       // 6th+ ban: 1 year (max)
        };
    }

    private function applyStrikeToUser(User $user, string $reason): void
    {
        // Reset strikes if user has been clean for 7+ days
        if ($user->last_strike_at && $user->last_strike_at->lt(now()->subWeek())) {
            $user->report_strikes = 0;
        }

        $user->report_strikes   += 1;
        $user->last_strike_at    = now();

        // 3 strikes → auto-ban
        if ($user->report_strikes >= 3) {
            $bannedUntil = $this->computeBanDuration($user);

            $user->is_banned        = true;
            $user->banned_until     = $bannedUntil;
            $user->ban_reason       = $reason;
            $user->report_strikes   = 0; // reset strikes on ban
            $user->suspension_count += 1;
        }

        $user->save();
    }

    // ── Admin/Mod report dashboard
    public function index(Request $request)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $status = $request->query('status', 'pending');

        // Step 1: Get grouped summary (most-reported first)
        $grouped = Reports::where('status', $status)
            ->select(
                'reportable_id',
                'reportable_type',
                DB::raw('COUNT(*) as report_count'),
                DB::raw('MAX(created_at) as latest_at'),
                DB::raw('MIN(id) as representative_id')
            )
            ->groupBy('reportable_id', 'reportable_type')
            ->orderByDesc('report_count')
            ->orderByDesc('latest_at')
            ->paginate(20);

        $representativeIds = $grouped->pluck('representative_id')->filter()->values();

        // Step 2: Load the representative report for each group
        $representativeReports = Reports::with(['reporter', 'reviewer'])
            ->whereIn('id', $representativeIds)
            ->get()
            ->keyBy('id');

        // Step 3: Load all individual reporters per target
        $targetKeys = $grouped->map(fn($g) => [
            'id'   => $g->reportable_id,
            'type' => $g->reportable_type,
        ]);

        $allReportersRaw = Reports::with('reporter')
            ->where('status', $status)
            ->where(function ($q) use ($targetKeys) {
                foreach ($targetKeys as $key) {
                    $q->orWhere(function ($inner) use ($key) {
                        $inner->where('reportable_id', $key['id'])
                            ->where('reportable_type', $key['type']);
                    });
                }
            })
            ->get()
            ->groupBy(fn($r) => $r->reportable_type . ':' . $r->reportable_id);

        // Step 4: Build the final collection and attach metadata
        $reportsCollection = $grouped->getCollection()->map(function ($group) use ($representativeReports, $allReportersRaw) {
            $report = $representativeReports[$group->representative_id] ?? null;
            if (!$report) return null;

            $report->report_count  = (int) $group->report_count;
            $report->all_reporters = $allReportersRaw[$report->reportable_type . ':' . $report->reportable_id] ?? collect();

            return $report;
        })->filter()->values();

        // Step 5: Eager load the polymorphic targets on the collection
        $reportsCollection->loadMorph('reportable', [
            Bleep::class    => ['user', 'media'],
            Comments::class => ['user', 'bleep'],
        ]);

        $reportsCollection->loadMorphCount('reportable', [
            Bleep::class    => ['likes', 'comments', 'reposts', 'shares'],
            Comments::class => ['likes', 'replies'],
        ]);

        // Step 6: Put the enriched collection back into the paginator
        $grouped->setCollection($reportsCollection);

        $reports = $grouped;

        return view('admin.reports', compact('reports', 'status'));
    }

    // ── User submits a report
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'       => ['required', 'in:bleep,comment'],
            'bleep_id'   => ['required_if:type,bleep', 'nullable', 'exists:bleeps,id'],
            'comment_id' => ['required_if:type,comment', 'nullable', 'exists:comments,id'],
            'category'   => ['required', 'in:spam,harassment,hate,nsfw,illegal,other'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);

        $reporterId = Auth::id();
        $target = $validated['type'] === 'bleep'
            ? Bleep::findOrFail($validated['bleep_id'])
            : Comments::findOrFail($validated['comment_id']);

        if ($target->user_id === $reporterId) {
            return response()->json(['message' => 'You cannot report your own content.'], 422);
        }

        $exists = Reports::where('reportable_id', $target->id)
            ->where('reportable_type', get_class($target))
            ->where('reporter_id', $reporterId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already reported this item.'], 409);
        }

        $target->reports()->create([
            'reporter_id' => $reporterId,
            'category'    => $validated['category'],
            'reason'      => $validated['reason'],
            'status'      => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully.'], 201);
    }

    // ── Delete bleep + optionally ban OP
    public function deleteBleep(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'ban_op'      => ['nullable', 'boolean'],
            'banned_until'=> ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;

        if (!$target) {
            return response()->json(['message' => 'Content already deleted.'], 404);
        }

        $op = $target->user;

        $target->delete(); // soft delete

        if (($validated['ban_op'] ?? false) && $op) {
            $bannedUntil = !empty($validated['banned_until'])
                ? Carbon::parse($validated['banned_until'])->setTimezone(config('app.timezone'))
                : $this->computeBanDuration($op);

            $op->is_banned        = true;
            $op->banned_until     = $bannedUntil;
            $op->ban_reason       = 'Violated community guidelines (reported content)';
            $op->report_strikes   = 0;
            $op->suspension_count += 1;
            $op->save();
        }

        // Resolve ALL reports for this same target
        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->update([
                'status'       => 'resolved',
                'action_taken' => ($validated['ban_op'] ?? false) ? 'op_banned' : 'bleep_deleted',
                'reviewed_at'  => now(),
                'reviewed_by'  => Auth::id(),
                'notes'        => $validated['notes'] ?? null,
            ]);

        return response()->json(['message' => 'Content deleted and reports resolved.']);
    }

    // ── Delete comment
    public function deleteComment(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'ban_op' => ['nullable', 'boolean'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;

        if (!$target) {
            return response()->json(['message' => 'Comment already deleted.'], 404);
        }

        $op = $target->user;
        $target->delete(); // soft delete

        if (($validated['ban_op'] ?? false) && $op) {
            $bannedUntil = $this->computeBanDuration($op);
            $op->is_banned        = true;
            $op->banned_until     = $bannedUntil;
            $op->ban_reason       = 'Violated community guidelines (reported comment)';
            $op->report_strikes   = 0;
            $op->suspension_count += 1;
            $op->save();
        }

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->update([
                'status'       => 'resolved',
                'action_taken' => 'bleep_deleted',
                'reviewed_at'  => now(),
                'reviewed_by'  => Auth::id(),
                'notes'        => $validated['notes'] ?? null,
            ]);

        return response()->json(['message' => 'Comment deleted and reports resolved.']);
    }

    // ── Ban reporter
    public function banReporter(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'banned_until' => ['nullable', 'date'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $reporter = $report->reporter;

        if (!$reporter) {
            return response()->json(['message' => 'Reporter account no longer exists.'], 404);
        }

        $bannedUntil = !empty($validated['banned_until'])
            ? Carbon::parse($validated['banned_until'])->setTimezone(config('app.timezone'))
            : $this->computeBanDuration($reporter);

        $reporter->is_banned        = true;
        $reporter->banned_until     = $bannedUntil;
        $reporter->ban_reason       = 'Abuse of report system';
        $reporter->report_strikes   = 0;
        $reporter->suspension_count += 1;
        $reporter->save();

        $report->update([
            'status'       => 'resolved',
            'action_taken' => 'reporter_banned',
            'reviewed_at'  => now(),
            'reviewed_by'  => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Reporter banned and report resolved.']);
    }

    // ── Dismiss
    public function dismiss(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->where('status', 'pending')
            ->update([
                'status'       => 'dismissed',
                'action_taken' => 'none',
                'reviewed_at'  => now(),
                'reviewed_by'  => Auth::id(),
                'notes'        => $validated['notes'] ?? null,
            ]);

        // Do NOT apply or modify strikes — false alarm, no punishment
        return response()->json(['message' => 'Report dismissed.']);
    }
}
