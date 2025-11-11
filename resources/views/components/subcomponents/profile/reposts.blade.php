@foreach($reposts as $repost)
    @if($repost->bleep && !$repost->bleep->deleted_at)
        <div class="relative">
            <div class="flex items-center gap-2 mb-2 text-xs text-base-content/60 pl-2">
                <i data-lucide="repeat" class="w-4 h-4"></i>
                <span>
                    {{ Auth::id() === ($user->id ?? null) ? 'You' : '@' . $user->username }} reposted
                    <span class="text-base-content/40">• {{ $repost->created_at->diffForHumans() }}</span>
                </span>
            </div>
            <x-bleep :bleep="$repost->bleep" />
        </div>
    @endif
@endforeach
