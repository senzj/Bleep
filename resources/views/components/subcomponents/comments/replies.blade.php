@props(['replies', 'parent', 'depth' => 1])

{{-- Called and renders in the CommentsRepliesController by javascript replies.js--}}
@foreach ($replies as $reply)
    <x-subcomponents.comments.commentcard :comment="$reply" :bleep="$parent->bleep" :depth="$depth" />
@endforeach
