@vite(['resources/js/settings/preference.js'])

<x-settings.layout>
    <x-slot:title>Preferences</x-slot:title>

    <div class="space-y-6">

        {{-- Preferences Section --}}
        <div class="border-b border-gray-500/50 p-4">
            <h2 class="text-2xl font-semibold text-base-content">Preferences</h2>
            <p class="text-base-content/70">Customize your experience by changing your preferences.</p>

            {{-- Theme Selection --}}
            <div class="border border-base-200 shadow-lg p-3 rounded-lg">
                <h3 class="text-xl font-semibold text-base-content mb-4">Appearance</h3>
                <p class="text-base-content/70 mb-4">Customize the look and feel of the app to your liking.</p>

                {{-- Theme Selection --}}
                <div class="bg-base-200 rounded-lg inline-flex items-center gap-3 p-4">
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

                {{-- Layout Selection --}}
                <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Navigation Layout</span>
                        <select id="nav-layout-select" class="select select-bordered select-sm" data-pref="nav_layout">
                            <option value="horizontal" {{ $preferences->nav_layout === 'horizontal' ? 'selected' : '' }}>
                                Horizontal (Top bar)
                            </option>
                            <option value="vertical" {{ $preferences->nav_layout === 'vertical' ? 'selected' : '' }}>
                                Vertical (Sidebar)
                            </option>
                        </select>
                    </div>
                    <p class="text-sm text-base-content/70">Choose between a horizontal top navbar or vertical sidebar navigation. Changes take effect after page reload.</p>
                </div>
            </div>

            {{-- Content Preferences --}}
            <div class="border border-base-200 shadow-lg p-3 rounded-lg">
                <h3 class="text-xl font-semibold text-base-content mt-6">Content Preferences</h3>
                <p class="text-base-content/70 mb-4">Manage how content is displayed to you.</p>

                <div class="bg-base-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Show NSFW --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Show NSFW content</span>
                            <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="show_nsfw" {{ $preferences->show_nsfw ? 'checked' : '' }}>
                        </div>
                        <p class="text-sm text-base-content/70">Toggle the visibility of NSFW content in your feed.</p>
                    </div>

                    {{-- Blur NSFW Media --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Blur NSFW media</span>
                            <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="blur_nsfw_media" {{ $preferences->blur_nsfw_media ? 'checked' : '' }}>
                        </div>
                        <p class="text-sm text-base-content/70">Blur NSFW images/videos until you click to reveal them.</p>
                    </div>

                    {{-- Autoplay Videos --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Autoplay videos</span>
                            <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="autoplay_videos" {{ $preferences->autoplay_videos ? 'checked' : '' }}>
                        </div>
                        <p class="text-sm text-base-content/70">Automatically play videos when they appear in your feed.</p>
                    </div>

                    {{-- Autoplay Audio --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Autoplay audio</span>
                            <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="autoplay_audio" {{ $preferences->autoplay_audio ? 'checked' : '' }}>
                        </div>
                        <p class="text-sm text-base-content/70">Play audio automatically with videos. Disable to start muted.</p>
                    </div>

                    {{-- Show Reposts in Feed --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Show reposts in feed</span>
                            <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="show_reposts_in_feed" {{ $preferences->show_reposts_in_feed ? 'checked' : '' }}>
                        </div>
                        <p class="text-sm text-base-content/70">Include reposts from people you follow in your feed.</p>
                    </div>

                    {{-- Show Anonymous Bleeps --}}
                    @if (env('ANONYMITY', true))
                        <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="label-text">Show anonymous bleeps</span>
                                <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="show_anonymous_bleeps" {{ $preferences->show_anonymous_bleeps ? 'checked' : '' }}>
                            </div>
                            <p class="text-sm text-base-content/70">Display bleeps posted anonymously in your feed.</p>
                        </div>
                    @endif

                    {{-- Default Feed Sort --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Default For You sort</span>
                            <select class="select select-bordered select-sm pref-select" data-pref="default_feed_sort">
                                <option value="newest" {{ $preferences->default_feed_sort === 'newest' ? 'selected' : '' }}>Newest first</option>
                                <option value="popular" {{ $preferences->default_feed_sort === 'popular' ? 'selected' : '' }}>Most popular</option>
                                <option value="following" {{ $preferences->default_feed_sort === 'following' ? 'selected' : '' }}>Following first</option>
                            </select>
                        </div>
                        <p class="text-sm text-base-content/70">How bleeps are sorted when you open your feed.</p>
                    </div>

                    {{-- Bleeps Per Page --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Number of Bleeps</span>
                            <select class="select select-bordered select-sm pref-select" data-pref="bleeps_per_page">
                                <option value="10" {{ $preferences->bleeps_per_page == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ $preferences->bleeps_per_page == 20 ? 'selected' : '' }}>20</option>
                                <option value="30" {{ $preferences->bleeps_per_page == 30 ? 'selected' : '' }}>30</option>
                                <option value="40" {{ $preferences->bleeps_per_page == 40 ? 'selected' : '' }}>40</option>
                                <option value="50" {{ $preferences->bleeps_per_page == 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>
                        <p class="text-sm text-base-content/70">Number of bleeps to load at a time.</p>
                    </div>
                </div>
            </div>

            {{-- System Preferences --}}
            <div class="">
                <h3 class="text-xl font-semibold text-base-content mt-6">Notifications</h3>
                <p class="text-base-content/70 mb-4">Configure notification settings for your account.</p>

                <div class="bg-base-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Notification Recieve Sound --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Recieve Notification Sound</span>
                            <select class="select select-bordered select-sm pref-select" data-pref="recieve_notification_sound">
                                <option value="off" {{ $preferences->recieve_notification_sound === 'off' ? 'selected' : '' }}>Off</option>
                                <option value="default" {{ $preferences->recieve_notification_sound === 'default' ? 'selected' : '' }}>Default</option>
                            </select>
                        </div>
                        <p class="text-sm text-base-content/70">Choose the sound played for notifications.</p>
                    </div>

                    {{-- Notification Send Sound --}}
                    <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Send Notification Sound</span>
                            <select class="select select-bordered select-sm pref-select" data-pref="send_notification_sound">
                                <option value="off" {{ $preferences->send_notification_sound === 'off' ? 'selected' : '' }}>Off</option>
                                <option value="default" {{ $preferences->send_notification_sound === 'default' ? 'selected' : '' }}>Default</option>
                            </select>
                        </div>
                        <p class="text-sm text-base-content/70">Choose the sound played when you post new content, comments, or send messages.</p>
                    </div>

                    {{-- Upload Notification sound --}}
                    <div class="form-control flex flex-col-2 justify-center border border-base-200 p-3 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text">Upload Notification Sound</span>
                            {{-- file upload --}}
                        </div>
                        <p class="text-sm text-base-content/70">Choose the sound played when you recieve or send.</p>
                    </div>

                </div>
            </div>
        </div>

        {{-- Privacy settings --}}
        <div class="border border-base-200 shadow-lg p-4 rounded-lg">
            <h3 class="text-2xl font-semibold text-base-content">Privacy Settings</h3>
            <p class="text-base-content/70 mb-4">Manage your privacy settings to control who can see your profile and posts.</p>

            <div class="bg-base-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Make my profile private</span>
                        <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="private_profile" {{ $preferences->private_profile ? 'checked' : '' }}>
                    </div>
                    <p class="text-sm text-base-content/70">Only approved followers can see your profile and posts.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Block new followers</span>
                        <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="block_new_followers" {{ $preferences->block_new_followers ? 'checked' : '' }}>
                    </div>
                    <p class="text-sm text-base-content/70">Stops users from following you until disabled.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Hide my online status</span>
                        <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="hide_online_status" {{ $preferences->hide_online_status ? 'checked' : '' }}>
                    </div>
                    <p class="text-sm text-base-content/70">Does not show when you were last active.</p>
                </div>

                <div class="form-control flex flex-col justify-center border border-base-200 p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="label-text">Hide activity on profile</span>
                        <input type="checkbox" class="toggle toggle-primary pref-toggle" data-pref="hide_activity" {{ $preferences->hide_activity ? 'checked' : '' }}>
                    </div>
                    <p class="text-sm text-base-content/70">Hides likes and reposts from your profile.</p>
                </div>
            </div>
        </div>

        {{-- Other preferences can be added here in the future --}}
    </div>

    {{-- Toast notification for preference updates --}}
    <div id="pref-toast" class="toast toast-top toast-center z-100 hidden">
        <div class="alert alert-success">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>Preference saved!</span>
        </div>
    </div>
</x-settings.layout>

@push('scripts')
    @vite(['resources/js/settings/preference.js'])
@endpush
