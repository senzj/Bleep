<?php

namespace App\View\Components\Social;

use Closure;
use App\Models\User;
use Illuminate\View\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class People extends Component
{
    public $suggestedUsers;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        if (Auth::check()) {
            // Pre-fetch followed user IDs
            $followingIds = Auth::user()->following()->pluck('followed_id');

            // Step 1: Get mutual suggestions (users followed by people you follow)
            $mutualUsers = User::whereHas('followers', function ($query) use ($followingIds) {
                    $query->whereIn('follower_id', $followingIds);
                })
                ->where('id', '!=', Auth::id()) // Exclude self
                ->whereDoesntHave('followers', function ($query) {
                    $query->where('follower_id', Auth::id()); // Exclude already followed
                })
                ->inRandomOrder()
                ->limit(10)
                ->get();

            $suggestedUsers = $mutualUsers;

            // Step 2: If less than 10, fill with random users
            if ($mutualUsers->count() < 10) {
                $needed = 10 - $mutualUsers->count();
                $excludedIds = $mutualUsers->pluck('id')->push(Auth::id())->merge($followingIds)->unique();

                $randomUsers = User::whereNotIn('id', $excludedIds)
                    ->inRandomOrder()
                    ->limit($needed)
                    ->get();

                $suggestedUsers = $mutualUsers->merge($randomUsers);
            }

            $this->suggestedUsers = $suggestedUsers;
        } else {
            $this->suggestedUsers = collect(); // Empty if not authenticated
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.social.people', [
            'suggestedUsers' => $this->suggestedUsers,
        ]);
    }
}
