@php
    // Get user's nav layout preference, default to horizontal
    $navLayout = 'horizontal';
    if (Auth::check()) {
        $navLayout = Auth::user()->getNavLayout();
    }
@endphp

@if($navLayout === 'vertical')
    @include('components.include.ver_navbar')
@else
    @include('components.include.hor_navbar')
@endif
