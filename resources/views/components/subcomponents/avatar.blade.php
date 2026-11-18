@props(['user' => null, 'size' => 'size-10'])

@php
    $sizeClass = is_numeric($size) ? "size-{$size}" : $size;
    $avatarUrl = $user?->profile_picture_url ?? asset('images/avatar/default.jpg');
@endphp

<div class="{{ $sizeClass }} rounded-full overflow-hidden" data-avatar-url="{{ $avatarUrl }}">
    <img src="{{ $avatarUrl }}" alt="{{ $user?->username }}'s avatar" class="block w-full h-full object-cover" />
</div>
