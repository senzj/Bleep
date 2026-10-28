@props(['title' => 'Bulletin'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('Bleep_Icon.png') }}">

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="base_url" content="{{ url('') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @stack('meta')

        {{-- Prevent theme flash: same rule as the main layout --}}
        <script>
            (() => {
                const DEFAULT = 'lofi';
                const serverTheme = '{{ Auth::check() ? Auth::user()->getPreferences()->theme : '' }}';
                let t = serverTheme || localStorage.getItem('theme') || DEFAULT;

                if (serverTheme && serverTheme !== localStorage.getItem('theme')) {
                    localStorage.setItem('theme', serverTheme);
                }

                if (t === 'system') {
                    try {
                        t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } catch (e) {}
                }
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>

        @vite(['resources/js/init.js', 'resources/css/app.css'])

        <title>Bleep | {{ $title }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @stack('styles')
    </head>

    <body class="min-h-dvh bg-base-300 font-sans text-base-content antialiased" data-page="bulletin">
        <div class="flex min-h-dvh flex-col md:h-dvh md:overflow-hidden">
            {{ $slot }}
        </div>

        @stack('scripts')
    </body>
</html>
