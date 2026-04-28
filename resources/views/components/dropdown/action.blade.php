@props([
    'bleep' => null,
    'buttonClass' => 'btn btn-ghost btn-xs btn-circle hover:bg-base-300',
    'buttonTitle' => 'More options',
    'buttonIcon' => 'more-vertical',
    'buttonIconClass' => 'w-5 h-5',
    'menuClass' => 'shadow-lg bg-base-100 rounded-xl w-52 border border-base-200 p-2 space-y-1',
])

<div class="dropdown dropdown-end">
    <button tabindex="0" type="button" class="{{ $buttonClass }}" title="{{ $buttonTitle }}">
        <i data-lucide="{{ $buttonIcon }}" class="{{ $buttonIconClass }}"></i>
    </button>

    <ul tabindex="0" class="dropdown-content z-1 {{ $menuClass }}">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @elseif ($bleep)
            @can('update', $bleep)
                <li>
                    <button type="button"
                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition edit-bleep-btn"
                        data-bleep-id="{{ $bleep->id }}"
                        data-bleep-message="{{ $bleep->message }}"
                        data-bleep-anonymous="{{ $bleep->is_anonymous ? '1' : '0' }}"
                        data-bleep-nsfw="{{ $bleep->is_nsfw ? '1' : '0' }}">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        <span>Edit</span>
                    </button>
                </li>
            @endcan
            @can('delete', $bleep)
                <li>
                    <form method="POST" action="/bleeps/{{ $bleep->id }}/delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                onclick="return confirm('Are you sure you want to delete this bleep?')"
                                class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition">
                            <i data-lucide="trash" class="w-4 h-4"></i>
                            <span>Delete</span>
                        </button>
                    </form>
                </li>
            @endcan
            @if (Auth::check() && Auth::user()->id !== $bleep->user->id)
                <li>
                    <button type="button" class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition report-bleep-btn" data-bleep-id="{{ $bleep->id }}">
                        <i data-lucide="flag" class="w-4 h-4"></i>
                        <span>Report</span>
                    </button>
                </li>
            @endif
        @endif
    </ul>
</div>
