<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Bleep;
use App\Models\Comments;
use App\Models\Reports;
use App\Models\User;
use App\Services\Moderation\BanService;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private BanService $bans,
    ) {}

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
        $targetKeys = $grouped->map(fn ($g) => [
            'id' => $g->reportable_id,
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
            ->groupBy(fn ($r) => $r->reportable_type.':'.$r->reportable_id);

        // Step 4: Build the final collection and attach metadata
        $reportsCollection = $grouped->getCollection()->map(function ($group) use ($representativeReports, $allReportersRaw) {
            $report = $representativeReports[$group->representative_id] ?? null;
            if (! $report) {
                return null;
            }

            $report->report_count = (int) $group->report_count;
            $report->all_reporters = $allReportersRaw[$report->reportable_type.':'.$report->reportable_id] ?? collect();

            return $report;
        })->filter()->values();

        // Step 5: Eager load the polymorphic targets on the collection
        $reportsCollection->loadMorph('reportable', [
            Bleep::class => ['user', 'media'],
            Comments::class => ['user', 'bleep'],
        ]);

        $reportsCollection->loadMorphCount('reportable', [
            Bleep::class => ['likes', 'comments', 'reposts', 'shares'],
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
            'type' => ['required', 'in:bleep,comment'],
            'bleep_id' => ['required_if:type,bleep', 'nullable', 'exists:bleeps,id'],
            'comment_id' => ['required_if:type,comment', 'nullable', 'exists:comments,id'],
            'category' => ['required', 'in:spam,harassment,hate,nsfw,illegal,other'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reporter = Auth::user();
        $target = $validated['type'] === 'bleep'
            ? Bleep::findOrFail($validated['bleep_id'])
            : Comments::findOrFail($validated['comment_id']);

        if ($target->user_id === $reporter->id) {
            return response()->json(['message' => 'You cannot report your own content.'], 422);
        }

        $exists = Reports::where('reportable_id', $target->id)
            ->where('reportable_type', get_class($target))
            ->where('reporter_id', $reporter->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already reported this item.'], 409);
        }

        $report = $target->reports()->create([
            'reporter_id' => $reporter->id,
            'category' => $validated['category'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $this->notifications->notifyStaff(NotificationType::ReportSubmitted, [
            'actor' => $reporter,
            'subject' => $report,
            'url' => route('admin.reports', ['status' => 'pending']),
            'group_key' => $this->queueGroupKey($report),
            'data' => [
                'subject_label' => $validated['type'] === 'comment' ? 'comment' : 'bleep',
                'excerpt' => NotificationData::excerpt($target->message),
                'category' => $validated['category'],
            ],
        ], $reporter);

        return response()->json(['message' => 'Report submitted successfully.'], 201);
    }

    // ── Delete bleep + optionally ban OP
    public function deleteBleep(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'ban_op' => ['nullable', 'boolean'],
            'banned_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;

        if (! $target) {
            return response()->json(['message' => 'Content already deleted.'], 404);
        }

        $op = $target->user;
        $excerpt = NotificationData::excerpt($target->message);
        $pendingReports = $this->pendingReportsFor($report);
        $notes = $validated['notes'] ?? null;
        $banned = (bool) ($validated['ban_op'] ?? false);

        $target->delete(); // soft delete

        if ($banned && $op) {
            $bannedUntil = ! empty($validated['banned_until'])
                ? Carbon::parse($validated['banned_until'])->setTimezone(config('app.timezone'))
                : $this->bans->nextBanDuration($op);

            $this->bans->ban(
                $op,
                $bannedUntil,
                'Violated community guidelines (reported content)',
                Auth::user(),
            );
        }

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->update([
                'status' => 'resolved',
                'action_taken' => $banned ? 'op_banned' : 'bleep_deleted',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'notes' => $notes,
            ]);

        $this->notifyContentRemoved($op, 'bleep', $excerpt);
        $this->notifyReporters($pendingReports, 'action_taken', Auth::user());
        $this->notifyStaffReview($report, 'bleep', $excerpt, $notes);

        return response()->json(['message' => 'Content deleted and reports resolved.']);
    }

    // ── Delete comment
    public function deleteComment(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'ban_op' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;

        if (! $target) {
            return response()->json(['message' => 'Comment already deleted.'], 404);
        }

        $op = $target->user;
        $excerpt = NotificationData::excerpt($target->message);
        $pendingReports = $this->pendingReportsFor($report);
        $notes = $validated['notes'] ?? null;
        $banned = (bool) ($validated['ban_op'] ?? false);

        $target->delete(); // soft delete

        if ($banned && $op) {
            $this->bans->ban(
                $op,
                $this->bans->nextBanDuration($op),
                'Violated community guidelines (reported comment)',
                Auth::user(),
            );
        }

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->update([
                'status' => 'resolved',
                'action_taken' => 'bleep_deleted',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'notes' => $notes,
            ]);

        $this->notifyContentRemoved($op, 'comment', $excerpt);
        $this->notifyReporters($pendingReports, 'action_taken', Auth::user());
        $this->notifyStaffReview($report, 'comment', $excerpt, $notes);

        return response()->json(['message' => 'Comment deleted and reports resolved.']);
    }

    // ── Ban reporter
    public function banReporter(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'banned_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reporter = $report->reporter;

        if (! $reporter) {
            return response()->json(['message' => 'Reporter account no longer exists.'], 404);
        }

        $bannedUntil = ! empty($validated['banned_until'])
            ? Carbon::parse($validated['banned_until'])->setTimezone(config('app.timezone'))
            : $this->bans->nextBanDuration($reporter);

        $this->bans->ban($reporter, $bannedUntil, 'Abuse of report system', Auth::user());

        $report->update([
            'status' => 'resolved',
            'action_taken' => 'reporter_banned',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->notifyStaffReview(
            $report,
            $report->reportable instanceof Comments ? 'comment' : 'bleep',
            NotificationData::excerpt($report->reportable?->message),
            $validated['notes'] ?? null,
        );

        return response()->json(['message' => 'Reporter banned and report resolved.']);
    }

    // ── Mark reviewed / resolve without removing content
    public function markReviewed(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $pendingReports = $this->pendingReportsFor($report);

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->where('status', 'pending')
            ->update([
                'status' => 'resolved',
                'action_taken' => 'none',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

        $this->notifyReporters($pendingReports, 'no_action', Auth::user());
        $this->notifyStaffReview(
            $report,
            $report->reportable instanceof Comments ? 'comment' : 'bleep',
            NotificationData::excerpt($report->reportable?->message),
            $validated['notes'] ?? null,
        );

        return response()->json(['message' => 'Report marked as resolved.']);
    }

    // ── Dismiss
    public function dismiss(Request $request, Reports $report)
    {
        abort_unless(Auth::user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $pendingReports = $this->pendingReportsFor($report);

        Reports::where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->where('status', 'pending')
            ->update([
                'status' => 'dismissed',
                'action_taken' => 'none',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

        $this->notifyReporters($pendingReports, 'no_action', Auth::user());
        $this->notifyStaffReview(
            $report,
            $report->reportable instanceof Comments ? 'comment' : 'bleep',
            NotificationData::excerpt($report->reportable?->message),
            $validated['notes'] ?? null,
        );

        // Do NOT apply or modify strikes — false alarm, no punishment
        return response()->json(['message' => 'Report dismissed.']);
    }

    /**
     * @return EloquentCollection<int, Reports>
     */
    private function pendingReportsFor(Reports $report): EloquentCollection
    {
        return Reports::query()
            ->with('reporter')
            ->where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->where('status', 'pending')
            ->get();
    }

    /**
     * Notify each reporter without revealing the moderator. Moderator notes are
     * private, so they are deliberately not included.
     *
     * @param  EloquentCollection<int, Reports>  $reports
     */
    private function notifyReporters(EloquentCollection $reports, string $outcome, User $resolver): void
    {
        $reporters = $reports
            ->pluck('reporter')
            ->filter()
            ->reject(fn (User $reporter) => (int) $reporter->id === (int) $resolver->id)
            ->unique('id');

        $first = $reports->first();

        if ($reporters->isEmpty() || ! $first) {
            return;
        }

        $this->notifications->notify($reporters, NotificationType::ReportResolved, [
            'url' => route('notifications.index'),
            'group_key' => 'reportres:'.$first->reportable_type.':'.$first->reportable_id,
            'data' => ['outcome' => $outcome],
        ]);
    }

    private function notifyStaffReview(Reports $report, string $subjectLabel, ?string $excerpt, ?string $notes): void
    {
        $this->notifications->notifyStaff(NotificationType::ReportReviewed, [
            'subject' => $report,
            'url' => route('admin.reports', ['status' => 'resolved']),
            'group_key' => 'reportreview:'.$report->reportable_type.':'.$report->reportable_id,
            'data' => [
                'subject_label' => $subjectLabel,
                'excerpt' => $excerpt,
                'notes' => $notes,
            ],
        ], Auth::user());
    }

    private function notifyContentRemoved(?User $author, string $subjectLabel, ?string $excerpt): void
    {
        if (! $author) {
            return;
        }

        $this->notifications->notify($author, NotificationType::ContentRemoved, [
            'data' => [
                'subject_label' => $subjectLabel,
                'excerpt' => $excerpt,
                'reason' => 'Violated community guidelines',
            ],
        ]);
    }

    private function queueGroupKey(Reports $report): string
    {
        return 'reportqueue:'.$report->reportable_type.':'.$report->reportable_id;
    }
}
