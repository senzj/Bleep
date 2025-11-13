<x-settings.layout>
    <h1 class="text-xl font-semibold mb-6">Edit Profile</h1>

    <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center gap-4">
            @php $avatar = auth()->user()->profile_picture_url; @endphp
            <img src="{{ $avatar ?: 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->dname).'&background=random' }}"
                 class="w-16 h-16 rounded-full object-cover border" alt="avatar">
            <div>
                <label class="block text-sm font-medium mb-1">Profile Picture</label>
                <input type="file" name="profile_picture" accept="image/*" class="file-input file-input-bordered file-input-sm w-full max-w-xs" />
                @error('profile_picture') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Username</label>
            <input type="text" value="{{"@" . $user->username }}" class="input input-bordered w-full bg-base-200 cursor-not-allowed" disabled>
            <p class="text-xs text-base-content/60 mt-1">Username cannot be changed</p>
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

        <div>
            <label class="block text-sm font-medium mb-1">Bio</label>
            <textarea name="bio" rows="4" class="textarea textarea-bordered w-full">{{ old('bio', $user->bio) }}</textarea>
            @error('bio') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Timezone</label>
            <input type="text" value="{{ $user->timezone ?? 'Not set' }}" class="input input-bordered w-full bg-base-200 cursor-not-allowed" disabled>
            <p class="text-xs text-base-content/60 mt-1">Timezone is set automatically and cannot be changed</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</x-settings.layout>
