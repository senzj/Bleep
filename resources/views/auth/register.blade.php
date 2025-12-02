@vite([
    'resources/js/auth/register.js'
])

<x-layout>
    <x-slot:title>
        Register
    </x-slot:title>

    <div class="min-h-screen bg-base-200 py-8">
        <div class="container mx-auto px-4 max-w-7xl">

            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-bold mb-2">Create Your Account</h1>
                <p class="text-base-content/60">Join us today and get started on your journey</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">

                {{-- LEFT SIDEBAR: Profile Picture & Tips (Desktop: 1/3 width) --}}
                <div class="lg:col-span-1 space-y-6">

                    {{-- Profile Picture Card --}}
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body p-6">
                            <h2 class="card-title text-base mb-4">Profile Picture</h2>

                            <div class="flex flex-col items-center gap-4">
                                <div class="relative group cursor-pointer" onclick="document.getElementById('profile_picture_input').click()">
                                    <div class="avatar">
                                        <div class="w-32 rounded-full transition-all duration-300 hover:scale-105 border border-base-300 overflow-hidden shadow-md">
                                            <img id="profile_picture_preview"
                                                src=""
                                                alt="Profile Preview"
                                                class="hidden w-full h-full rounded-full object-cover" />
                                            <div id="default_avatar"
                                                class="flex items-center justify-center h-full w-full bg-base-300 rounded-full">
                                                <i data-lucide="user" class="w-16 h-16 text-base-content/50"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <i data-lucide="camera" class="w-8 h-8 text-white"></i>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 w-full">
                                    <button type="button"
                                            class="btn btn-sm btn-outline btn-primary w-full"
                                            onclick="document.getElementById('profile_picture_input').click()">
                                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i>
                                        Upload Photo
                                    </button>
                                    <button type="button"
                                            id="recrop_button"
                                            class="btn btn-sm btn-outline btn-secondary w-full hidden">
                                        <i data-lucide="crop" class="w-4 h-4 mr-2"></i>
                                        Recrop Image
                                    </button>
                                </div>

                                <p class="text-xs text-center text-base-content/60">
                                    <i data-lucide="info" class="w-3 h-3 inline"></i>
                                    Square image recommended<br/>Maximum size: 5MB
                                </p>
                            </div>

                            {{-- Profile Picture Form --}}
                            <input type="file" id="profile_picture_input" name="profile_picture" class="hidden" accept="image/*" form="register-form" />

                            @error('profile_picture')
                                <div class="alert alert-error py-2 text-xs mt-4">
                                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                    <span>{{ $message }}</span>
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- MAIN FORM: Registration Fields (Desktop: 2/3 width) --}}
                <div class="lg:col-span-2">
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body p-6 sm:p-8">
                            <h2 class="card-title text-xl mb-6">Account Information</h2>

                            <form id="register-form" method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                                @csrf

                                {{-- Hidden timezone field --}}
                                <input type="hidden" name="timezone" id="timezone" value="UTC">

                                <div class="space-y-5">

                                    {{-- Display Name --}}
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-medium">Display Name</span>
                                        </label>
                                        <input type="text"
                                            id="display_name"
                                            name="display_name"
                                            value="{{ old('display_name') }}"
                                            placeholder="Enter your display name"
                                            class="input input-bordered w-full transition-all"
                                            required>
                                        <label class="label">
                                            <span id="display_name_feedback" class="label-text-alt min-h-4"></span>
                                        </label>
                                        @error('display_name')
                                            <label class="label">
                                                <span class="label-text-alt text-error">{{ $message }}</span>
                                            </label>
                                        @enderror
                                    </div>

                                    {{-- Username --}}
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-medium">Username</span>
                                        </label>
                                        <input type="text"
                                            id="username"
                                            name="username"
                                            value="{{ old('username') }}"
                                            placeholder="Choose a unique username"
                                            class="input input-bordered w-full transition-all"
                                            autocomplete="username"
                                            required>
                                        <label class="label">
                                            <span id="username_feedback" class="label-text-alt min-h-4"></span>
                                        </label>
                                        @error('username')
                                            <label class="label">
                                                <span class="label-text-alt text-error">{{ $message }}</span>
                                            </label>
                                        @enderror
                                    </div>

                                    {{-- Email --}}
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-medium">Email Address</span>
                                        </label>
                                        <input type="email"
                                            id="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            placeholder="your.email@example.com"
                                            class="input input-bordered w-full transition-all"
                                            autocomplete="email"
                                            required>
                                        <label class="label">
                                            <span id="email_feedback" class="label-text-alt min-h-4"></span>
                                        </label>
                                        @error('email')
                                            <label class="label">
                                                <span class="label-text-alt text-error">{{ $message }}</span>
                                            </label>
                                        @enderror
                                    </div>

                                    {{-- Password Grid: Two columns on desktop --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                        {{-- Password --}}
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text font-medium">Password</span>
                                            </label>
                                            <div class="relative">
                                                <input type="password"
                                                    id="password"
                                                    name="password"
                                                    placeholder="Create a strong password"
                                                    class="input input-bordered w-full pr-10 transition-all"
                                                    autocomplete="new-password"
                                                    required>
                                                <button type="button"
                                                        id="toggle_password"
                                                        class="absolute right-3 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100 transition-all duration-200 hover:bg-base-200 rounded-lg p-1">
                                                    <i data-lucide="eye" class="w-5 h-5" id="eye_icon"></i>
                                                    <i data-lucide="eye-off" class="w-5 h-5 hidden" id="eye_off_icon"></i>
                                                </button>
                                            </div>

                                            {{-- Password Strength Indicator --}}
                                            <div class="flex items-center gap-2 mt-2">
                                                <div class="flex-1 flex gap-1">
                                                    <div id="strength_bar_1" class="h-1.5 flex-1 rounded-full bg-base-300 transition-colors"></div>
                                                    <div id="strength_bar_2" class="h-1.5 flex-1 rounded-full bg-base-300 transition-colors"></div>
                                                    <div id="strength_bar_3" class="h-1.5 flex-1 rounded-full bg-base-300 transition-colors"></div>
                                                    <div id="strength_bar_4" class="h-1.5 flex-1 rounded-full bg-base-300 transition-colors"></div>
                                                    <div id="strength_bar_5" class="h-1.5 flex-1 rounded-full bg-base-300 transition-colors"></div>
                                                </div>
                                                <span id="strength_text" class="text-xs font-semibold min-w-[50px] text-right"></span>
                                            </div>

                                            @error('password')
                                                <label class="label">
                                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                                </label>
                                            @enderror
                                        </div>

                                        {{-- Confirm Password --}}
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text font-medium">Confirm Password</span>
                                            </label>
                                            <div class="relative">
                                                <input type="password"
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    placeholder="Re-enter your password"
                                                    class="input input-bordered w-full pr-10 transition-all"
                                                    autocomplete="new-password"
                                                    required>
                                                <button type="button"
                                                        id="toggle_password_confirmation"
                                                        class="absolute right-3 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100 transition-all duration-200 hover:bg-base-200 rounded-lg p-1">
                                                    <i data-lucide="eye" class="w-5 h-5" id="eye_icon_confirm"></i>
                                                    <i data-lucide="eye-off" class="w-5 h-5 hidden" id="eye_off_icon_confirm"></i>
                                                </button>
                                            </div>
                                            <label class="label">
                                                <span id="confirm_feedback" class="label-text-alt min-h-4"></span>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Password Requirements Panel --}}
                                    <div id="password_requirements" class="hidden">
                                        <div class="bg-base-300/50 shadow-sm p-4 mt-2">
                                            <div class="w-full">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <i data-lucide="info" class="w-4 h-4"></i>
                                                    <span class="font-semibold text-sm">Password Requirements:</span>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <div id="req_length" class="flex items-center gap-2">
                                                        <i data-lucide="circle" class="w-4 h-4 text-base-content/30 shrink-0"></i>
                                                        <span class="text-xs text-base-content/70">At least 8 characters</span>
                                                    </div>
                                                    <div id="req_upper" class="flex items-center gap-2">
                                                        <i data-lucide="circle" class="w-4 h-4 text-base-content/30 shrink-0"></i>
                                                        <span class="text-xs text-base-content/70">One uppercase letter (A-Z)</span>
                                                    </div>
                                                    <div id="req_lower" class="flex items-center gap-2">
                                                        <i data-lucide="circle" class="w-4 h-4 text-base-content/30 shrink-0"></i>
                                                        <span class="text-xs text-base-content/70">One lowercase letter (a-z)</span>
                                                    </div>
                                                    <div id="req_number" class="flex items-center gap-2">
                                                        <i data-lucide="circle" class="w-4 h-4 text-base-content/30 shrink-0"></i>
                                                        <span class="text-xs text-base-content/70">One number (0-9)</span>
                                                    </div>
                                                    <div id="req_special" class="flex items-center gap-2 sm:col-span-2">
                                                        <i data-lucide="circle" class="w-4 h-4 text-base-content/30 shrink-0"></i>
                                                        <span class="text-xs text-base-content/70">One special character (@$!%*#?&)</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Submit Button --}}
                                    <div class="form-control mt-8">
                                        <button type="submit" class="btn btn-primary btn-lg w-full transition-all duration-200 hover:-translate-y-1 hover:shadow-lg active:translate-y-0 btn-disabled opacity-50 cursor-not-allowed" disabled>
                                            <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                                            Create Account
                                        </button>
                                        <p class="text-xs text-center text-base-content/60 mt-2">
                                            <i data-lucide="info" class="w-3 h-3 inline"></i>
                                            Complete all fields with valid information to enable registration
                                        </p>
                                    </div>
                                </div>
                            </form>

                            {{-- Already have account --}}
                            <div class="divider my-6">OR</div>
                            <p class="text-center">
                                Already have an account?
                                <a href="/login" class="link link-primary font-semibold hover:translate-x-1 transition-transform duration-200">Sign in here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cropper Modal --}}
    <x-modals.profile.crop />

</x-layout>
