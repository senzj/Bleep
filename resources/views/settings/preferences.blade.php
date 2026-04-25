@once
@push('scripts')
    @vite(['resources/js/settings/preference.js'])

<script>
document.addEventListener('DOMContentLoaded', () => {

    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content;
    const toast    = (ok, msg) => window.preferencesManager?.showToast(ok, msg);

    // Current saved values from server — used to restore selection after
    // injecting the uploaded <optgroup> into both selects.
    const saved = {
        recieve_notification_sound: @json($preferences->recieve_notification_sound),
        send_notification_sound:    @json($preferences->send_notification_sound),
    };

    // Preview
    let activeAudio = null;

    function playPath(path) {
        if (!path || path === 'none') return;
        if (activeAudio) { activeAudio.pause(); activeAudio.currentTime = 0; }
        activeAudio = new Audio(path);
        activeAudio.volume = 0.7;
        activeAudio.play().catch(() => {});
    }

    document.querySelectorAll('.sound-preview-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sel = document.getElementById('select-' + btn.dataset.target);
            playPath(sel?.value);
        });
    });

    // Inject uploaded optgroup into both selects
    function injectUploadedOptions(sounds) {
        ['recieve_notification_sound', 'send_notification_sound'].forEach(key => {
            const select = document.getElementById('select-' + key);
            if (!select) return;

            // Remove existing uploaded group before re-adding
            select.querySelector('optgroup[data-uploaded]')?.remove();
            if (!sounds.length) return;

            const group        = document.createElement('optgroup');
            group.label        = 'Your Uploads';
            group.dataset.uploaded = '1';

            sounds.forEach(s => {
                const opt       = document.createElement('option');
                opt.value       = s.path;
                opt.textContent = s.name;
                if (saved[key] === s.path) opt.selected = true;
                group.appendChild(opt);
            });

            select.appendChild(group);
        });
    }

    // Render uploaded list below the upload zone
    function renderUploadedList(sounds) {
        const wrap  = document.getElementById('uploaded-sounds-list');
        const items = document.getElementById('uploaded-sounds-items');
        if (!wrap || !items) return;

        if (!sounds.length) { wrap.classList.add('hidden'); return; }

        wrap.classList.remove('hidden');
        items.innerHTML = '';

        sounds.forEach(s => {
            const row     = document.createElement('div');
            row.className = 'flex items-center gap-2 text-sm p-2 rounded-lg bg-base-200';
            row.innerHTML = `
                <i data-lucide="bell" class="w-4 h-4 text-primary shrink-0"></i>
                <span class="truncate flex-1 text-base-content">${s.name}</span>
                <button type="button" class="btn btn-ghost btn-xs btn-circle preview-row"
                        data-path="${s.path}" title="Preview">
                    <i data-lucide="play" class="w-3 h-3"></i>
                </button>
            `;
            items.appendChild(row);
        });

        items.querySelectorAll('.preview-row').forEach(btn =>
            btn.addEventListener('click', () => playPath(btn.dataset.path))
        );

        if (window.lucide) window.lucide.createIcons();
    }

    // Load uploaded sounds from API
    async function loadUploadedSounds() {
        try {
            const res  = await fetch('/api/preferences', { headers: { Accept: 'application/json' } });
            const data = await res.json();
            const uploaded = data.sounds?.uploaded ?? [];
            injectUploadedOptions(uploaded);
            renderUploadedList(uploaded);
        } catch (e) {
            console.warn('[Sounds] Failed to load uploaded sounds:', e);
        }
    }

    // File input / drop zone
    const fileInput   = document.getElementById('sound-file-input');
    const dropZone    = document.getElementById('sound-drop-zone');
    const previewWrap = document.getElementById('sound-upload-preview');
    const previewName = document.getElementById('sound-upload-filename');
    const clearBtn    = document.getElementById('sound-upload-clear');
    const uploadBtn   = document.getElementById('sound-upload-btn');
    const progWrap    = document.getElementById('sound-upload-progress-wrap');
    const progBar     = document.getElementById('sound-upload-progress');
    const progLbl     = document.getElementById('sound-upload-progress-label');

    let selectedFile = null;

    function setFile(file) {
        if (!file) return;
        selectedFile            = file;
        previewName.textContent = file.name;
        previewWrap.classList.remove('hidden');
        uploadBtn.disabled      = false;
    }

    function clearFile() {
        selectedFile       = null;
        fileInput.value    = '';
        previewWrap.classList.add('hidden');
        progWrap.classList.add('hidden');
        uploadBtn.disabled = true;
        progBar.value      = 0;
        progLbl.textContent = '0%';
    }

    fileInput.addEventListener('change', () => setFile(fileInput.files?.[0]));
    clearBtn.addEventListener('click', clearFile);

    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('border-primary', 'bg-primary/5');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary', 'bg-primary/5');
    });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        setFile(e.dataTransfer.files?.[0]);
    });

    // Upload
    uploadBtn.addEventListener('click', async () => {
        if (!selectedFile) return;

        uploadBtn.disabled = true;
        progWrap.classList.remove('hidden');

        const formData = new FormData();
        formData.append('sound', selectedFile);

        try {
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/preferences/sounds/upload');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) {
                        const pct           = Math.round((e.loaded / e.total) * 100);
                        progBar.value       = pct;
                        progLbl.textContent = pct + '%';
                    }
                };

                xhr.onload = () => {
                    if (xhr.status === 201) {
                        try { resolve(JSON.parse(xhr.responseText)); }
                        catch (err) { reject(err); }
                    } else {
                        try { reject(new Error(JSON.parse(xhr.responseText)?.message || 'Upload failed')); }
                        catch { reject(new Error('Upload failed')); }
                    }
                };

                xhr.onerror = () => reject(new Error('Network error'));
                xhr.send(formData);
            });

            toast(true, `"${result.name}" uploaded!`);
            clearFile();

            // Inject immediately without waiting for full reload
            injectUploadedOptions([result]);

            // Then do a full reload to sync the list properly
            await loadUploadedSounds();

        } catch (err) {
            toast(false, err.message || 'Upload failed');
            uploadBtn.disabled = false;
        }
    });

    // Init
    loadUploadedSounds();
});

</script>
@endpush
@endonce

<x-settings.layout>
    <x-slot:title>Preferences</x-slot:title>

    <div class="space-y-3">

        {{-- Preferences Section --}}
        <div class="p-3 space-y-3 rounded-lg border-b border-base-200 flex flex-col">
            <div class="mb-4">
                <h3 class="text-2xl font-semibold text-base-content">Preferences</h2>
                <p class="text-base-content/70">Customize your experience by changing your preferences.</p>
            </div>

            {{-- Appearance --}}
            <div class="border border-base-200 shadow-lg p-3 rounded-lg space-y-1">
                <h3 class="text-xl font-semibold text-base-content">Appearance</h3>
                <p class="text-base-content/70 mb-4">Customize the look and feel of the app to your liking.</p>

                {{-- Theme Selection --}}
                <div class="collapse collapse-arrow border border-base-200 rounded-lg bg-base-100">
                    <input type="checkbox" id="theme-collapse" />

                    <div class="collapse-title flex items-center gap-3 px-4 py-3 min-h-0">
                        <span class="text-sm font-medium text-base-content">Theme</span>

                        {{-- Collapsed preview: palette strip + label. Hidden when expanded. --}}
                        <span id="theme-collapsed-preview" class="flex items-center gap-2 transition-opacity">
                            <span class="theme-palette-preview flex rounded overflow-hidden w-18 h-6 border border-base-200 shrink-0">
                                <span class="flex-1 bg-primary"></span>
                                <span class="flex-1 bg-secondary"></span>
                                <span class="flex-1 bg-accent"></span>
                                <span class="flex-1 bg-neutral"></span>
                            </span>
                            <span class="text-sm text-base-content/60 theme-current-label"></span>
                        </span>
                    </div>

                    <div class="collapse-content px-3 pb-3">
                        <ul class="grid grid-cols-3 md:grid-cols-6 lg:grid-cols-8 gap-2 pt-1 theme-menu" data-theme-menu="auto"></ul>
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
                <h3 class="text-xl font-semibold text-base-content">Content Preferences</h3>
                <p class="text-base-content/70 mb-4">Manage how content is displayed to you.</p>

                <div class="bg-base-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-1">
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

            {{-- Notification Preferences --}}
            <div class="border border-base-200 shadow-lg p-3 rounded-lg">
                <h3 class="text-xl font-semibold text-base-content">Notifications</h3>
                <p class="text-base-content/70 mb-4">Configure notification sounds for your account.</p>

                <div class="bg-base-200 rounded-lg p-4 space-y-1">

                    {{-- Receive Sound --}}
                    <div class="form-control flex flex-col border border-base-200 p-3 rounded-lg bg-base-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text font-medium">Receive Notification Sound</span>
                            <div class="flex items-center gap-2">
                                <select class="select select-bordered select-sm pref-select"
                                        id="select-recieve_notification_sound"
                                        data-pref="recieve_notification_sound">

                                    <option value="none"
                                        {{ $preferences->recieve_notification_sound === 'none' ? 'selected' : '' }}>
                                        Off
                                    </option>

                                    {{-- System sounds grouped by category --}}
                                    @foreach(['effects' => 'Effects', 'notifications' => 'Notifications'] as $cat => $label)
                                        @php $group = collect($systemSounds)->where('category', $cat)->values(); @endphp
                                        @if($group->isNotEmpty())
                                            <optgroup label="{{ $label }}">
                                                @foreach($group as $sound)
                                                    <option value="{{ $sound['path'] }}"
                                                        {{ $preferences->recieve_notification_sound === $sound['path'] ? 'selected' : '' }}>
                                                        {{ $sound['name'] }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach

                                    {{-- Uploaded sounds injected here by JS --}}
                                </select>

                                <button type="button"
                                        class="btn btn-outline btn-circle btn-sm sound-preview-btn"
                                        data-target="recieve_notification_sound"
                                        title="Preview">
                                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-base-content/70">Sound played when you receive a notification.</p>
                    </div>

                    {{-- Send Sound --}}
                    <div class="form-control flex flex-col border border-base-200 p-3 rounded-lg bg-base-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="label-text font-medium">Send Notification Sound</span>
                            <div class="flex items-center gap-2">
                                {{-- Sound select --}}
                                <select class="select select-bordered select-sm pref-select"
                                        id="select-send_notification_sound"
                                        data-pref="send_notification_sound">

                                    <option value="none"
                                        {{ $preferences->send_notification_sound === 'none' ? 'selected' : '' }}>
                                        Off
                                    </option>

                                    @foreach(['effects' => 'Effects', 'notifications' => 'Notifications'] as $cat => $label)
                                        @php $group = collect($systemSounds)->where('category', $cat)->values(); @endphp
                                        @if($group->isNotEmpty())
                                            <optgroup label="{{ $label }}">
                                                @foreach($group as $sound)
                                                    <option value="{{ $sound['path'] }}"
                                                        {{ $preferences->send_notification_sound === $sound['path'] ? 'selected' : '' }}>
                                                        {{ $sound['name'] }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach

                                    {{-- Uploaded sounds injected here by JS --}}
                                </select>

                                {{-- Play preview button --}}
                                <button type="button"
                                        class="btn btn-outline btn-circle btn-sm sound-preview-btn"
                                        data-target="send_notification_sound"
                                        title="Preview">
                                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-base-content/70">Sound played when you post, comment, or reply.</p>
                    </div>

                    {{-- Upload Custom Notification Sound --}}
                    <div class="form-control flex flex-col border border-base-200 p-3 rounded-lg bg-base-100">

                        <div class="flex items-center justify-between mb-3">
                            <span class="label-text font-medium">Upload Custom Notification Sound</span>
                            <span class="text-xs text-base-content/50">MP3, WAV, OGG, M4A · 5 MB</span>
                        </div>

                        {{-- Drop zone --}}
                        <div id="sound-drop-zone"
                            class="relative flex flex-col items-center justify-center gap-2 border-2 border-dashed border-base-300 rounded-lg p-3 cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors">
                            <i data-lucide="music" class="w-8 h-8 text-base-content/50 pointer-events-none"></i>
                            <p class="text-sm text-base-content/50 pointer-events-none">
                                Drag & drop <span class="font-semibold">OR</span> Upload custom notification sound
                            </p>
                            <input type="file"
                                id="sound-file-input"
                                accept=".mp3,.wav,.ogg,.webm,.m4a,audio/mpeg,audio/wav,audio/ogg,audio/webm,audio/x-m4a"
                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                        </div>

                        {{-- Selected file preview --}}
                        <div id="sound-upload-preview" class="hidden mt-3 flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                            <i data-lucide="bell" class="w-5 h-5 text-primary shrink-0"></i>
                            <span id="sound-upload-filename" class="text-sm truncate flex-1"></span>
                            <button type="button" id="sound-upload-clear" class="btn btn-ghost btn-xs btn-circle shrink-0">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>

                        {{-- Progress --}}
                        <div id="sound-upload-progress-wrap" class="hidden mt-3 space-y-1">
                            <progress id="sound-upload-progress" class="progress progress-primary w-full" value="0" max="100"></progress>
                            <p id="sound-upload-progress-label" class="text-xs text-base-content/50 text-right">0%</p>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button type="button" id="sound-upload-btn" class="btn btn-primary btn-sm" disabled>
                                <i data-lucide="upload" class="w-4 h-4"></i>
                                Upload Sound
                            </button>
                        </div>

                        {{-- Uploaded sounds list (populated by JS) --}}
                        <div id="uploaded-sounds-list" class="mt-4 hidden">
                            <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wide mb-2">Your Uploads</p>
                            <div id="uploaded-sounds-items" class="space-y-1.5"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Privacy settings --}}
        <div class="p-3 space-y-3 rounded-lg flex flex-col">
            <div class="mb-4">
                <h3 class="text-2xl font-semibold text-base-content">Privacy</h3>
                <p class="text-base-content/70">Manage your privacy settings to control who can see your profile and posts.</p>
            </div>

            <div class="bg-base-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-1">
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

