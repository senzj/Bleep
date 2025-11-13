<x-settings.layout>
    <h1 class="text-xl font-semibold mb-6">Change Password</h1>

    <form method="POST" action="{{ route('settings.password.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium mb-1">Current Password</label>
            <input type="password" name="current_password" class="input input-bordered w-full" required>
            @error('current_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">New Password</label>
            <input type="password" name="password" class="input input-bordered w-full" required>
            @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" class="input input-bordered w-full" required>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
</x-settings.layout>
