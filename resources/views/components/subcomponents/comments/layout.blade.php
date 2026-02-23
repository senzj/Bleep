@props([
    'bleepid' => null,
    'layoutmode' => 'post',
])

@php
    // Determine if user is authenticated for frontend use
    if (Auth::check()) {
        $authMeta = 'true';
    } else {
        $authMeta = 'false';
    }

    // Current user data for frontend use
    $authUser = Auth::user();

    // Fetch Bleep data based on provided bleepid or route context
    // For modal mode: bleepid is null at render time, JS will fetch via API
    // For post mode: we fetch server-side for better SEO and initial load
    $anonEnabled = config('app.anonymity', true) ? 'true' : 'false';

    $blp = null;
    $resolvedBleepId = $bleepid;

    // Only fetch server-side if we have bleepid (post page) or if mode is 'post'
    if ($layoutmode === 'post') {
        // Try bleepid prop first
        if ($bleepid) {
            $blp = \App\Models\Bleep::find($bleepid);
        }
        // Fallback to route parameter
        elseif (Route::current()) {
            $bleepParam = Route::current()->parameter('bleep') ?? Route::current()->parameter('id');
            if ($bleepParam) {
                $blp = is_string($bleepParam) ? \App\Models\Bleep::find($bleepParam) : $bleepParam;
                $resolvedBleepId = $blp?->id;
            }
        }
        // Check for $bleep variable from parent scope
        elseif (isset($bleep)) {
            $blp = $bleep;
            $resolvedBleepId = $blp->id;
        }
    }

    // Build bleep data for Vue
    $bleepData = $blp ? [
        'id'   => $blp->id,
        'is_anonymous' => (bool) $blp->is_anonymous,
        'user' => $blp->is_anonymous ? null : [
            'id'       => $blp->user?->id,
            'username' => $blp->user?->username,
        ],
    ] : [];

@endphp

<div id="{{ $layoutmode === 'modal' ? 'comments-container-layout-modal' : 'comments-container-layout' }}"
    data-bleep-id="{{ $resolvedBleepId ?? '' }}"
    data-bleep='@json($bleepData)'
    data-mode="{{ $layoutmode }}"
    data-auth-user='@json($authUser)'
    data-user-avatar="{{ Auth::user()?->profile_picture ?? '/images/avatar/default.jpg' }}"
    data-is-authenticated="{{ $authMeta }}"
    data-anonymous="{{ $anonEnabled ?? 'false' }}"
    class="h-full w-full"
>
    {{-- Loading Placeholder --}}
    <div class="flex justify-center items-center h-full w-full">
        <span class="loading loading-spinner loading-md"></span>
    </div>
</div>
