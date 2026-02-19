<?php

namespace App\Http\Controllers;

use App\Models\Bleep;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index($id)
    {
        $bleep = Bleep::with(['user', 'media', 'likes'])
            ->withTrashed()
            ->findOrFail($id);

        // Record view for single post page
        $bleep->recordView(
            Auth::user(),
            session()->getId()
        );

        // Get initial comments (first page)
        $perPage = 10;
        $comments = $bleep->comments()
            ->with('user')
            ->withCount('replies')
            ->whereNull('parent_id')
            ->latest()
            ->paginate($perPage);

        return view('pages.bleeps.post', compact('bleep', 'comments'));
    }


    /**
     * Load more comments for post page (AJAX)
     */
    public function loadMorePostComments(Request $request, $id)
    {
        $bleep = Bleep::findOrFail($id);
        $page = (int) $request->get('page', 1);
        $perPage = 10;

        $comments = $bleep->comments()
            ->with('user')
            ->withCount('replies')
            ->whereNull('parent_id')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        // Group by date
        $viewerSeed = Auth::check() ? Auth::id() : $request->session()->getId();
        $groups = $comments->getCollection()->groupBy(function($c) {
            $tz = $c->user?->timezone ?? config('app.timezone', 'UTC');
            return $c->created_at->copy()->setTimezone($tz)->format('Y-m-d') . '|' . $tz;
        });

        // Build HTML
        $html = '';
        foreach ($groups as $key => $group) {
            [$date, $tz] = explode('|', $key);
            $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $tz);
            $showYear = $dt->year !== now()->year;
            $label = $dt->format('F j') . ($showYear ? ', ' . $dt->year : '');

            // Date header with unique data attribute
            $html .= '<div class="text-sm text-base-content/60 font-medium mt-4 mb-2 comment-date-header" data-date="' . $date . '">' . $label . '</div>';

            foreach ($group as $comment) {
                $html .= view('components.subcomponents.comments.commentcard', [
                    'comment' => $comment,
                    'bleep' => $bleep
                ])->render();
            }
        }

        return response()->json([
            'html' => $html,
            'has_more' => $comments->hasMorePages(),
            'next_page' => $comments->currentPage() + 1,
            'current_page' => $comments->currentPage(),
            'total' => $comments->total(),
        ]);
    }
}
