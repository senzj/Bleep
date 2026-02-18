@php
    // Get user's nav layout preference, default to horizontal
    $navLayout = 'horizontal';
    if (Auth::check()) {
        $navLayout = Auth::user()->getNavLayout();
    }
@endphp

@if($navLayout === 'vertical')
    @include('components.include.vnavbar')
@else
    @include('components.include.hnavbar')
@endif
