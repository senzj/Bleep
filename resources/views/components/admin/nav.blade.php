<nav class="space-y-1" aria-label="Admin navigation">

    {{-- Dashboard --}}
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-primary-content' : 'hover:bg-base-200' }}">
        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
        <span>Dashboard</span>
    </a>

    {{-- Reports --}}
    <a href="{{ route('admin.reports') }}" class="flex items-center gap-3 px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.reports') ? 'bg-primary text-primary-content' : 'hover:bg-base-200' }}">
        <i data-lucide="flag" class="w-5 h-5"></i>
        <span>Reports</span>
    </a>

    {{-- Users --}}

    {{-- Security --}}


</nav>
