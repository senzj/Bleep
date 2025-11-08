@props(['user' => null, 'size' => 'size-10'])

@php
    $sizeClass = is_numeric($size) ? "size-{$size}" : $size;
    $avatarPath = $user?->profile_picture ? ltrim($user->profile_picture, '/') : null;

    if ($avatarPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($avatarPath)) {
        $avatarUrl = asset('storage/' . $avatarPath);
    } else {
        $avatarUrl = asset('images/avatar/default.jpg');
    }
@endphp

<div class="{{ $sizeClass }} rounded-full overflow-hidden" data-avatar-url="{{ $avatarUrl }}">
    <img src="{{ $avatarUrl }}" alt="{{ $user?->username }}'s avatar" class="w-full h-full object-cover" />
</div>
