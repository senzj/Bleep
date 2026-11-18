@php
    $supportPromoAt = \App\Support\SupportPromo::position($bleeps->count());
@endphp

@foreach ($bleeps as $bleep)
    @if ($supportPromoAt === $loop->index)
        <x-announcement.support />
    @endif

    <x-bleep :bleep="$bleep" :just-posted="$loop->first && session('success')" />
@endforeach
