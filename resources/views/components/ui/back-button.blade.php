@props([
    'fallback' => null,
    'label' => 'Back',
])

@php
    $fallbackUrl = $fallback ?: url()->previous() ?: url('/');
@endphp

<button
    type="button"
    data-back-button
    data-back-fallback="{{ $fallbackUrl }}"
    class="btn btn-ghost btn-sm gap-2 rounded-lg"
>
    <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</button>

