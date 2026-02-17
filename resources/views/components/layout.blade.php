<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- icon --}}
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        {{-- optional PNG fallback (add public/favicon-32.png if you want) --}}
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('Bleep_Icon.png') }}">

        {{-- meta data --}}
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="base_url" content="{{ url('') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @stack('meta')
        {{-- Prevent flash: set theme before CSS paints --}}
        <script>
            // Run before ANY styles load to prevent flash
            (() => {
                const DEFAULT = 'lofi';
                let t = localStorage.getItem('theme') || DEFAULT;
                if (t === 'system') {
                    try {
                        t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } catch (e) {}
                }
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>
        @vite(['resources/js/init.js','resources/css/app.css','resources/js/app.js'])
        <title>Bleep | {{ isset($title) ? $title : '' }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
        <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
        @stack('styles')
    </head>

    <body class="min-h-screen flex flex-col font-sans bg-base-300/90">
        @include('components.include.navbar')

        {{-- success toast --}}
        @if (session('success'))
            <div class="toast toast-top toast-center">
                <div class="alert alert-success animate-fade-out">
                    <i data-lucide="check-circle" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @elseif (session('error'))
            <div class="toast toast-top toast-center">
                <div class="alert alert-error animate-fade-out">
                    <i data-lucide="circle-alert" class="h-6 w-6 shrink-0 stroke-current"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- main content --}}
        <main class="flex-1 container mx-auto px-1 py-4">
            {{ $slot }}
        </main>

        @stack('scripts')
    </body>
</html>
