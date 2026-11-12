@props([
    'status' => 500,
    'title' => 'Something went wrong',
    'message' => 'An unexpected error occurred. Please try again in a moment.',
    'showHome' => true,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>{{ $title }} | {{ config('app.name', 'Bleep') }}</title>
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
    </head>
    <body>
        <main>
            <div class="code" aria-hidden="true">{{ $status }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>

            <div class="actions">
                @if ($showHome)
                    <a class="primary" href="{{ url('/') }}">Go home</a>
                @endif

                @auth
                    <a href="{{ url('/') }}#notifications">Notifications</a>
                @endauth
            </div>

            <footer>{{ config('app.name', 'Bleep') }} · {{ now()->format('j M Y, H:i') }}</footer>
        </main>
    </body>
</html>
