<?php

namespace App\View\Components\Button;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Follow extends Component
{
    public User $user;
    public bool $isOwnProfile;
    public bool $isFollowing;
    public bool $isFriend;
    public bool $isPrivate;
    public bool $canFollow;
    public bool $hasPendingRequest;

    public function __construct(User $user)
    {
        $this->user = $user;

        $authUser = Auth::user();

        $this->isOwnProfile = Auth::check() && Auth::id() === $user->id;
        $this->isFollowing = Auth::check() && $authUser->isFollowing($user);
        $this->isFriend = !$this->isOwnProfile && Auth::check() && $authUser->isFriend($user);
        $this->isPrivate = !$this->isOwnProfile && ($user->preferences?->private_profile ?? false);

        $blockNewFollows = $user->preferences?->block_new_followers ?? false;
        $this->canFollow = !$this->isOwnProfile
            && Auth::check()
            && !$this->isFollowing
            && (!$blockNewFollows || $this->isFriend);

        $this->hasPendingRequest = !$this->isOwnProfile
            && Auth::check()
            && $authUser->hasSentRequestTo($user);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.button.follow');
    }
}
