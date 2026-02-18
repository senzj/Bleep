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
                // Check for server-provided theme (logged-in user)
                const serverTheme = '{{ Auth::check() ? Auth::user()->getPreferences()->theme : '' }}';
                let t = serverTheme || localStorage.getItem('theme') || DEFAULT;

                // Sync localStorage with server theme
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
        @vite(['resources/js/init.js','resources/css/app.css','resources/js/app.js'])
        <title>Bleep | {{ isset($title) ? $title : '' }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @stack('styles')
    </head>

    @php
        $navLayout = 'horizontal';
        if (Auth::check()) {
            $navLayout = Auth::user()->getNavLayout();
        }
    @endphp

    <body class="min-h-screen font-sans bg-base-300/90" data-nav-layout="{{ $navLayout }}">
        @if($navLayout === 'vertical')
            {{-- Vertical nav layout: flex container with sidebar + content --}}
            <div class="flex min-h-screen">
                @include('components.include.navbar')

                <div class="flex-1 flex flex-col">
                    {{-- success toast --}}
                    @if (session('success'))
                        <div class="toast toast-top toast-center z-100">
                            <div class="alert alert-success animate-fade-out">
                                <i data-lucide="check-circle" class="h-6 w-6 shrink-0 stroke-current"></i>
                                <span>{{ session('success') }}</span>
                            </div>
                        </div>
                    @elseif (session('error'))
                        <div class="toast toast-top toast-center z-100">
                            <div class="alert alert-error animate-fade-out">
                                <i data-lucide="circle-alert" class="h-6 w-6 shrink-0 stroke-current"></i>
                                <span>{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif

                    <main class="flex-1 container mx-auto px-1 py-4">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @else
            {{-- Horizontal nav layout: traditional stacked layout --}}
            @include('components.include.navbar')

            {{-- success toast --}}
            @if (session('success'))
                <div class="toast toast-top toast-center z-100">
                    <div class="alert alert-success animate-fade-out">
                        <i data-lucide="check-circle" class="h-6 w-6 shrink-0 stroke-current"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @elseif (session('error'))
                <div class="toast toast-top toast-center z-100">
                    <div class="alert alert-error animate-fade-out">
                        <i data-lucide="circle-alert" class="h-6 w-6 shrink-0 stroke-current"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <main class="flex-1 container mx-auto px-1 py-4">
                {{ $slot }}
            </main>
        @endif

        @stack('scripts')

        {{-- Sidebar toggle script for vertical nav --}}
        @if($navLayout === 'vertical')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('vertical-nav-mobile');
                const overlay = document.getElementById('sidebar-overlay');
                const openBtn = document.getElementById('open-sidebar-btn');
                const closeBtn = document.getElementById('close-sidebar-btn');

                function openSidebar() {
                    sidebar?.classList.remove('-translate-x-full');
                    overlay?.classList.remove('hidden');
                }

                function closeSidebar() {
                    sidebar?.classList.add('-translate-x-full');
                    overlay?.classList.add('hidden');
                }

                openBtn?.addEventListener('click', openSidebar);
                closeBtn?.addEventListener('click', closeSidebar);
                overlay?.addEventListener('click', closeSidebar);

                // Close on escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeSidebar();
                });

                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        </script>
        @endif
    </body>
</html>
