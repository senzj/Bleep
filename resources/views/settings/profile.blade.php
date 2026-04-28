@once
    @push('scripts')
        @vite(['resources/js/profile/profile-crop.js'])
    @endpush
@endonce
<x-settings.layout>
    <h1 class="text-xl font-semibold mb-6">Edit Profile</h1>

    <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="grid gap-6 md:grid-cols-2">
        @csrf
        @method('PUT')

        <div class="md:col-span-2">
            @php
                $hasAvatar = filled(Auth::user()->getOriginal('profile_picture'));
                $avatarUrl = Auth::user()->profile_picture_url;
            @endphp

            {{-- Mobile: Centered column layout --}}
            <div class="flex flex-col items-center gap-4 md:hidden">
                <div class="relative group cursor-pointer" onclick="document.getElementById('profile_picture_input').click()">
                    <div class="avatar shadow-lg rounded-full border border-base-200">
                        <div class="w-28 h-28 rounded-full ring ring-gray-400 transition-all relative overflow-hidden">
                            <img id="profile_picture_preview" src="{{ $hasAvatar ? $avatarUrl : '' }}" alt="Profile Preview" class="w-full h-full rounded-full object-cover {{ $hasAvatar ? '' : 'hidden' }}" />

                            <div id="default_avatar" class="items-center justify-center h-full w-full bg-base-300 rounded-full {{ $hasAvatar ? 'hidden' : 'flex' }}">
                                <i data-lucide="user" class="w-14 h-14 text-base-content/50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <i data-lucide="camera" class="w-8 h-8 text-white"></i>
                    </div>
                </div>

                <div class="text-center">
                    <label class="block text-sm font-medium mb-2">Profile Picture</label>
                    <input id="profile_picture_input" type="file" name="profile_picture" accept="image/*" class="hidden" />
                    <div class="flex flex-wrap justify-center gap-2 mb-2">
                        <button type="button"
                                class="btn btn-sm btn-outline btn-primary"
                                onclick="document.getElementById('profile_picture_input').click()">
                            <i data-lucide="upload" class="w-4 h-4 mr-1"></i>
                            Upload New
                        </button>
                        <button type="button"
                                id="recrop_button"
                                class="btn btn-sm btn-outline btn-secondary {{ $hasAvatar ? '' : 'hidden' }}">
                            <i data-lucide="crop" class="w-4 h-4 mr-1"></i>
                            Recrop
                        </button>
                    </div>
                    <p class="text-xs text-base-content/60">PNG/JPG up to 5MB. Square image recommended.</p>
                    @error('profile_picture') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Desktop: Row layout with avatar on left --}}
            <div class="hidden md:flex items-center gap-4">
                <div class="relative group cursor-pointer shrink-0" onclick="document.getElementById('profile_picture_input').click()">
                    <div class="avatar shadow-lg rounded-full border border-base-200">
                        <div class="w-28 h-28 rounded-full ring ring-gray-400 transition-all relative overflow-hidden">
                            <img
                                id="profile_picture_preview_desktop"
                                src="{{ $hasAvatar ? $avatarUrl : '' }}"
                                alt="Profile Preview"
                                class="w-full h-full rounded-full object-cover {{ $hasAvatar ? '' : 'hidden' }}" />

                            <div
                                id="default_avatar_desktop"
                                class="items-center justify-center h-full w-full bg-base-300 rounded-full {{ $hasAvatar ? 'hidden' : 'flex' }}">
                                <i data-lucide="user" class="w-14 h-14 text-base-content/50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <i data-lucide="camera" class="w-8 h-8 text-white"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Profile Picture</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <button type="button"
                                class="btn btn-sm btn-outline btn-primary"
                                onclick="document.getElementById('profile_picture_input').click()">
                            <i data-lucide="upload" class="w-4 h-4 mr-1"></i>
                            Upload New
                        </button>
                        <button type="button"
                                id="recrop_button_desktop"
                                class="btn btn-sm btn-outline btn-secondary {{ $hasAvatar ? '' : 'hidden' }}">
                            <i data-lucide="crop" class="w-4 h-4 mr-1"></i>
                            Recrop
                        </button>
                    </div>
                    <p class="text-xs text-base-content/60">PNG/JPG up to 5MB. Square image recommended.</p>
                    @error('profile_picture') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Username</label>
            <input type="text" value="{{ '@'.$user->username }}" class="input input-bordered w-full bg-base-200 cursor-not-allowed" disabled>
            <p class="text-xs text-base-content/60 mt-1">Username cannot be changed.</p>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Display Name</label>
            <input type="text" name="dname" value="{{ old('dname', $user->dname) }}" class="input input-bordered w-full" required>
            @error('dname') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Bio</label>
            <textarea name="bio" rows="4" class="textarea textarea-bordered w-full" placeholder="A short sentence about you...">{{ old('bio', $user->bio) }}</textarea>
            @error('bio') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Timezone</label>
            <div class="flex items-center gap-2">
                <span class="badge">{{ $user->timezone ?? 'Not set' }}</span>
                <span class="text-xs text-base-content/60">Automatically detected.</span>
            </div>
        </div>

        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

    {{-- load modal once --}}
    @once
        <x-modals.profile.crop />
    @endonce
</x-settings.layout>

