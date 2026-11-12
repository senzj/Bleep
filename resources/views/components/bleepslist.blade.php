@foreach ($bleeps as $bleep)
    <x-bleep :bleep="$bleep" :just-posted="$loop->first && session('success')" />
@endforeach
