<?php

namespace App\View\Components\Include;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use App\Models\FollowRequest;

class Userprofile extends Component
{
    public int $pendingRequestsCount = 0;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        if (Auth::check()) {
            $this->pendingRequestsCount = FollowRequest::where('target_id', Auth::id())
                ->where('status', 'pending')
                ->count();
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.include.userprofile');
    }
}
