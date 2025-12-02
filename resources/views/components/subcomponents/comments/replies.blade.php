@props(['replies', 'parent', 'depth' => 1])

@foreach ($replies as $reply)
    <x-subcomponents.comments.commentcard :comment="$reply" :bleep="$parent->bleep" :depth="$depth" />
@endforeach
