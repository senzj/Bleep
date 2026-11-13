<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Maintenance | {{ config('app.name', 'Bleep') }}</title>
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        @vite(['resources/css/maintenance.css', 'resources/js/maintenance.js'])
    </head>
    <body>
        <main>
            {{-- Maintenance icon --}}
            <i class="mark" data-lucide="traffic-cone" ></i>
            <h1>{{ config('app.name', 'Bleep') }} is under maintenance</h1>

            {{-- meme gifs --}}
            @if ($image)
                <div class="meme-gif">
                    <img src="{{ asset('images/maintenance/'.$image) }}" alt="Maintenance meme">
                </div>
            @endif

            {{-- paragraph --}}
            <p>
                There is currently an ongoing maintenance in progress.
            </p>

            @if ($estimatedReturn)
                <p class="eta">
                    Expected back around
                    <strong>{{ $estimatedReturn->timezone(config('app.timezone'))->format('H:i') }}</strong>
                    ({{ $estimatedReturn->diffForHumans() }}).
                </p>
            @elseif ($duration)
                <p class="eta">Estimated downtime: {{ $duration }}</p>
            @endif

            <footer>
                If this takes longer than expected, please check back later.
            </footer>
        </main>
    </body>
</html>
