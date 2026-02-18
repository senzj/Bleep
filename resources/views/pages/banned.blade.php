<!DOCTYPE html>
<html lang="en" data-theme="lofi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | Account Banned</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-base-300">
    <x-layout>
        <x-slot:title>{{ config('app.name') }} | Account Banned</x-slot:title>

        {{-- Hide navbar with CSS --}}
        @push('styles')
            <style>
                nav { display: none !important; }
                main {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 0;
                }
            </style>
        @endpush

        <div class="card w-full max-w-lg bg-base-100 shadow-2xl">
            <div class="card-body text-center">
                {{-- Ban Icon --}}
                <div class="flex justify-center mb-4">
                    <div class="rounded-full bg-error/10 p-6">
                        <i data-lucide="shield-ban" class="w-16 h-16 text-error"></i>
                    </div>
                </div>

                {{-- Title --}}
                <h1 class="text-3xl font-bold text-error mb-2">
                    @if(Auth::user()->banned_until)
                        Account Suspended
                    @else
                        Account Banned
                    @endif
                </h1>

                {{-- Ban Type --}}
                @if(Auth::user()->banned_until)
                    <div class="alert alert-warning mb-4">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        <div class="text-left">
                            <p class="font-semibold">Temporary Ban</p>
                            <p class="text-sm" id="ban-countdown">Calculating...</p>
                            <p class="text-xs opacity-70 mt-1">
                                Banned Until: <span id="unban-date">Loading...</span>
                                • <span id="tz-label"></span>
                            </p>
                        </div>
                    </div>
                @else
                    <div class="alert alert-error mb-4">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                        <div>
                            <p class="font-semibold">Permanently Banned</p>
                            <p class="text-sm">Please reflect on what you did, your actions may have violated community guidelines.</p>
                        </div>
                    </div>
                @endif

                {{-- Ban Reason --}}
                <div class="bg-base-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold mb-2 flex items-center gap-2 justify-center">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                        Reason for Ban
                    </h3>
                    <p class="text-sm opacity-80">
                        {{ Auth::user()->ban_reason ?? 'No reason provided.' }}
                    </p>
                </div>

                {{-- Actions --}}
                <div class="card-actions flex flex-col items-center gap-3">
                    @if(Auth::user()->banned_until)
                        <button type="button" class="btn bg-base-300 btn-md" id="refresh-btn">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Check Ban Status
                        </button>
                    @endif

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-error btn-md">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Logout
                        </button>
                    </form>
                </div>

                {{-- Appeal Info --}}
                <div class="mt-6 text-xs opacity-60">
                    <p>If you believe this is a mistake, please contact Mod or Admin.</p>
                </div>
            </div>
        </div>

        @if(Auth::user()->banned_until)
            @push('scripts')
                <script>
                    // Pass ban date from Blade to JS (ISO8601 UTC)
                    const bannedUntilUTC = new Date("{{ Auth::user()->banned_until->toIso8601String() }}");
                    const countdownEl = document.getElementById('ban-countdown');
                    const unbanDateEl = document.getElementById('unban-date');
                    const tzLabelEl = document.getElementById('tz-label');
                    const refreshBtn = document.getElementById('refresh-btn');

                    // User timezone label
                    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local time';
                    if (tzLabelEl) tzLabelEl.textContent = tz;

                    // Format date in user's local timezone
                    function formatLocalDateTime(date) {
                        const options = {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            timeZoneName: 'short'
                        };
                        return date.toLocaleString(undefined, options);
                    }

                    // Display unban date in user's timezone
                    if (unbanDateEl) unbanDateEl.textContent = formatLocalDateTime(bannedUntilUTC);

                    function updateCountdown() {
                        const now = new Date();
                        const diff = bannedUntilUTC - now;

                        if (diff <= 0) {
                            countdownEl.innerHTML = '<span class="text-success font-semibold">Ban has expired! Refreshing...</span>';
                            setTimeout(() => window.location.reload(), 1000);
                            return;
                        }

                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                        let timeStr = '';
                        if (days > 0) timeStr += `${days}d `;
                        if (hours > 0 || days > 0) timeStr += `${hours}h `;
                        if (minutes > 0 || hours > 0 || days > 0) timeStr += `${minutes}m `;
                        timeStr += `${seconds}s`;

                        countdownEl.textContent = `Ban expires in: ${timeStr}`;
                    }

                    updateCountdown();
                    setInterval(updateCountdown, 1000);
                    refreshBtn?.addEventListener('click', () => window.location.reload());
                </script>
            @endpush
        @endif
    </x-layout>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
