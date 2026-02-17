<x-settings.layout>
    <x-slot:title>Preferences</x-slot:title>

    <div class="space-y-6">

        {{-- Theme Selection --}}
        <div class="border border-base-200 shadow-lg p-4 rounded-lg">
            <h2 class="text-2xl font-semibold text-base-content">Preferences</h2>
            <p class="text-base-content/70">Customize your experience by changing your preferences.</p>

            <div class="bg-base-200 rounded-lg inline-flex items-center gap-3 p-4 border border-base-200 shadow-lg">
                <div class="text-md font-medium text-base-content">Select Theme:</div>
                <div class="dropdown dropdown-start border border-base-200 shadow-lg rounded-md">
                    <button tabindex="0" class="btn btn-sm btn-outline theme-button gap-2">
                        <i data-lucide="sun" class="w-4 h-4 theme-current-icon"></i>
                        <span class="font-semibold theme-current-label">System</span>
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </button>
                    <ul tabindex="0" class="theme-menu dropdown-content z-1 shadow-lg bg-base-100 rounded-md w-52 border border-base-200 p-2 space-y-1 mt-2 max-h-72 overflow-y-auto" data-theme-menu="auto"></ul>
                </div>
            </div>
        </div>

        {{-- Privacy settings --}}
        <div class="border border-base-200 shadow-lg p-4 rounded-lg">
            <h3 class="text-2xl font-semibold text-base-content">Privacy Settings</h3>
            <p class="text-base-content/70 mb-4">Manage your privacy settings to control who can see your profile and posts.</p>

            <div class="bg-base-200 rounded-lg p-4 grid grid-cols-2 gap-6">
                <div class="form-control flex flex-col justify-center border border-base-200 p-1 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Make my profile private</span>
                        <input type="checkbox" class="toggle toggle-primary" id="private-profile-toggle">
                    </div>
                    <p class="text-sm text-base-content/70">Only approved followers can see your profile and posts.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-1 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Block new followers</span>
                        <input type="checkbox" class="toggle toggle-primary" id="block-followers-toggle">
                    </div>
                    <p class="text-sm text-base-content/70">Stops users from following you until disabled.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-1 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Hide my online status</span>
                        <input type="checkbox" class="toggle toggle-primary" id="hide-status-toggle">
                    </div>
                    <p class="text-sm text-base-content/70">Does not show when you were last active.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-1 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Hide activity on profile</span>
                        <input type="checkbox" class="toggle toggle-primary" id="hide-activity-toggle">
                    </div>
                    <p class="text-sm text-base-content/70">Hides likes and reposts from your profile.</p>
                </div>
            </div>
        </div>

        {{-- Other preferences can be added here in the future --}}
    </div>
</x-settings.layout>
