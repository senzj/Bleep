<?php

namespace App\View\Components\Modals\Posts;

use App\Models\Bleep;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Comments extends Component
{
    public $bleep;

    /**
     * Create a new component instance.
     */
    public function __construct($bleepId = null)
    {
        if ($bleepId) {
            $this->bleep = Bleep::with('comments')->find($bleepId);
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.modals.posts.comments', ['bleep' => $this->bleep]);
    }
}
