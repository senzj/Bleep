<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Comments;
use App\Models\Reports;
use App\Models\User;
use App\Models\UserWarning;
use App\Services\Moderation\WarningService;
use App\Services\Notifications\NotificationData;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarningsController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private WarningService $warnings,
    ) {}

    /**
     * Issue a warning from a report and resolve every pending report for the
     * same target.
     */
    public function store(Request $request, Reports $report): JsonResponse
    {
        abort_unless($request->user()->hasAdminAccess(), 403);

        $validated = $request->validate([
            'reason' => ['required', Rule::in(config('moderation.warning_reasons'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;
        $author = $target?->user;

        if (! $target || ! $author) {
            return response()->json(['message' => 'Content author no longer exists.'], 404);
        }

        $notes = $validated['notes'] ?? null;
        $pendingReports = $this->pendingReportsFor($report);

        $result = $this->warnings->issue(
            $author,
            $validated['reason'],
            $notes,
            $request->user(),
            $report,
        );

        Reports::query()
            ->where('reportable_id', $report->reportable_id)
            ->where('reportable_type', $report->reportable_type)
            ->where('status', 'pending')
            ->update([
                'status' => 'resolved',
                'action_taken' => 'warned',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'notes' => $notes,
            ]);

        $this->notifyReporters($pendingReports, 'action_taken', $request->user());
        $this->notifyStaffReview($report, $request->user(), $notes);

        return response()->json([
            'message' => $result['banned']
                ? 'Warning issued and user auto-banned.'
                : 'Warning issued.',
            'banned' => $result['banned'],
            'strikes' => $result['strikes'],
            'warning' => $this->presentWarning($result['warning']),
        ]);
    }

    /**
     * Warning history for the admin user editor.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasAdminAccess(), 403);

        $warnings = $user->warnings()
            ->with('moderator:id,username,dname')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'active_count' => $user->warnings()->active()->count(),
            'warnings' => $warnings->map(fn (UserWarning $warning) => $this->presentWarning($warning)),
        ]);
    }

    public function revoke(Request $request, UserWarning $warning): JsonResponse
    {
        abort_unless($request->user()->hasAdminAccess(), 403);

        $this->warnings->revoke($warning, $request->user());

        $warning->refresh()->load('user');

        return response()->json([
            'message' => 'Warning revoked.',
            'active_count' => $warning->user
                ? $this->warnings->activeCount($warning->user)
                : 0,
        ]);
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
     * Tell every reporter what happened, without revealing the moderator or
     * their private notes.
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

        if ($reporters->isEmpty()) {
            return;
        }

        $this->notifications->notify($reporters, NotificationType::ReportResolved, [
            'url' => route('notifications.index'),
            'group_key' => $this->outcomeGroupKey($reports->first()),
            'data' => [
                'outcome' => $outcome,
            ],
        ]);
    }

    private function notifyStaffReview(Reports $report, User $resolver, ?string $notes): void
    {
        $this->notifications->notifyStaff(NotificationType::ReportReviewed, [
            'subject' => $report,
            'url' => route('admin.reports', ['status' => 'resolved']),
            'group_key' => 'reportreview:'.$report->reportable_type.':'.$report->reportable_id,
            'data' => [
                'subject_label' => $report->reportable instanceof Comments ? 'comment' : 'bleep',
                'excerpt' => NotificationData::excerpt($report->reportable?->message),
                'notes' => $notes,
            ],
        ], $resolver);
    }

    private function outcomeGroupKey(Reports $report): string
    {
        return 'reportres:'.$report->reportable_type.':'.$report->reportable_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWarning(UserWarning $warning): array
    {
        return [
            'id' => $warning->id,
            'reason' => $warning->reason,
            'notes' => $warning->notes,
            'moderator' => $warning->moderator
                ? $warning->moderator->dname.' (@'.$warning->moderator->username.')'
                : 'System',
            'created_at' => optional($warning->created_at)->toIso8601String(),
            'created_at_human' => optional($warning->created_at)->diffForHumans(),
            'expires_at' => optional($warning->expires_at)->toIso8601String(),
            'revoked_at' => optional($warning->revoked_at)->toIso8601String(),
            'active' => $warning->isActive(),
        ];
    }
}
