@once
    @push('scripts')
        @vite(['resources/js/profile/profile-crop.js'])
    @endpush
@endonce

<x-settings.layout>
    <h1 class="text-xl font-semibold mb-6">Change Password</h1>

    <form method="POST" action="{{ route('settings.password.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium mb-1">Current Password</label>
            <div class="relative">
                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    class="input input-bordered w-full pr-10"
                    required>

                <button
                    type="button"
                    class="absolute cursor-pointer inset-y-0 right-2 flex items-center text-base-content/60 hover:text-base-content transition-colors"
                    aria-label="Show password"
                    onclick="togglePw('current_password', this)">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
            </div>
            @error('current_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">New Password</label>
            <div class="relative">
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="input input-bordered w-full pr-10"
                    required
                    aria-describedby="pwdHelp">

                <button
                    type="button"
                    class="absolute cursor-pointer inset-y-0 right-2 flex items-center text-base-content/60 hover:text-base-content transition-colors"
                    aria-label="Show password"
                    onclick="togglePw('password', this)">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="mt-2">
                <div class="h-1 rounded bg-base-200 overflow-hidden">
                    <div id="pwdStrength" class="h-full w-0 transition-all duration-300"></div>
                </div>
                <p id="pwdHelp" class="text-xs text-base-content/60 mt-1">Use 8+ chars with upper, lower, number, and symbol.</p>
                <div id="pwdRequirements" class="hidden mt-2 space-y-1">
                    <div class="flex items-center gap-1.5 text-xs">
                        <i id="req-length" data-lucide="circle" class="w-3 h-3 text-base-content/40"></i>
                        <span class="text-base-content/60">At least 8 characters</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <i id="req-upper" data-lucide="circle" class="w-3 h-3 text-base-content/40"></i>
                        <span class="text-base-content/60">One uppercase letter</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <i id="req-lower" data-lucide="circle" class="w-3 h-3 text-base-content/40"></i>
                        <span class="text-base-content/60">One lowercase letter</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <i id="req-number" data-lucide="circle" class="w-3 h-3 text-base-content/40"></i>
                        <span class="text-base-content/60">One number</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <i id="req-symbol" data-lucide="circle" class="w-3 h-3 text-base-content/40"></i>
                        <span class="text-base-content/60">One special character</span>
                    </div>
                </div>
            </div>
            @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Confirm Password</label>
            <div class="relative">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    class="input input-bordered w-full pr-10"
                    required>

                <button
                    type="button"
                    class="absolute cursor-pointer inset-y-0 right-2 flex items-center text-base-content/60 hover:text-base-content transition-colors"
                    aria-label="Show password"
                    onclick="togglePw('password_confirmation', this)">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
            </div>
            <p id="pwdMatch" class="text-xs mt-1 hidden"></p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
</x-settings.layout>
