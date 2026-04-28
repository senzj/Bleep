<?php

namespace App\View\Components\Card;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Users extends Component
{
    public $users;
    public $emptyMessage;
    public $showMessage;

    /**
     * Create a new component instance.
     */
    public function __construct($users = null, string $emptyMessage = 'No suggestions available at the moment.', bool $showMessage = true)
    {
        $this->users = $users;
        $this->emptyMessage = $emptyMessage;
        $this->showMessage = $showMessage;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.card.users', [
            'users' => $this->users,
            'emptyMessage' => $this->emptyMessage,
            'showMessage' => $this->showMessage,
        ]);
    }
}
