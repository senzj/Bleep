@vite([
    'resources/js/auth/register.js'
])

<x-layout>
    <x-slot:title>
        Register
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col w-full">
            <div class="card w-full max-w-md bg-base-100">
                <div class="card-body p-6 sm:p-8">
                    <h1 class="text-3xl font-bold text-center mb-6">Create Account</h1>

                    <form id="register-form" method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- Hidden timezone field --}}
                        <input type="hidden" name="timezone" id="timezone" value="UTC">

                        {{-- Hidden profile picture data --}}
                        <input type="hidden" name="profile_picture" id="profile_picture_data" value="{{ old('profile_picture', '') }}">

                        {{-- User Profile Picture --}}
                        <div class="form-control mb-6">
                            <label class="label justify-center">
                                <span class="label-text font-medium">Profile Picture</span>
                            </label>

                            {{-- Profile Picture --}}
                            <div class="flex flex-col items-center gap-4">
                                <div class="relative group cursor-pointer" onclick="document.getElementById('profile_picture_input').click()">
                                    {{-- Avatar Preview --}}
                                    <div class="avatar shadow-lg rounded-full border border-base-200">
                                       <div class="w-32 rounded-full ring ring-gray-400 transition-all relative">
                                            <img id="profile_picture_preview"
                                                src=""  {{-- src removed dynamically if empty --}}
                                                alt="Profile Preview"
                                                class="hidden w-full h-full rounded-full object-cover" />
                                            <div id="default_avatar"
                                                class="flex items-center justify-center h-full w-full bg-base-300 rounded-full">
                                                <i data-lucide="user" class="w-16 h-16 text-base-content/50"></i>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Camera overlay --}}
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        <i data-lucide="camera" class="w-8 h-8 text-white"></i>
                                    </div>
                                </div>
                                <input type="file"
                                    id="profile_picture_input"
                                    class="hidden"
                                    accept="image/*">

                                <div class="flex gap-2">
                                    <button type="button"
                                            class="btn btn-sm btn-outline btn-primary"
                                            onclick="document.getElementById('profile_picture_input').click()">
                                        <i data-lucide="upload" class="w-4 h-4 mr-1"></i>
                                        Upload Photo
                                    </button>

                                    <button type="button"
                                            id="recrop_button"
                                            class="btn btn-sm btn-outline btn-secondary hidden">
                                        <i data-lucide="crop" class="w-4 h-4 mr-1"></i>
                                        Recrop
                                    </button>
                                </div>
                                <span class="text-xs text-base-content/60 text-center">Square image recommended, max 5MB</span>
                            </div>
                            @error('profile_picture')
                                <div class="label justify-center">
                                    <span class="label-text-alt text-error wrap-break-word">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        {{-- Display Name --}}
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="display_name"
                                   placeholder="Display Name"
                                   value="{{ old('display_name') }}"
                                   class="input input-bordered w-full @error('display_name') input-error @enderror"
                                   required>
                            <span>Display Name</span>
                        </label>
                        @error('display_name')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error wrap-break-word">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Username --}}
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="username"
                                   placeholder="Username"
                                   value="{{ old('username') }}"
                                   class="input input-bordered w-full @error('username') input-error @enderror"
                                   required>
                            <span>Username</span>
                        </label>
                        @error('username')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error wrap-break-word">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Email --}}
                        <label class="floating-label mb-6">
                            <input type="email"
                                   name="email"
                                   placeholder="Email"
                                   value="{{ old('email') }}"
                                   class="input input-bordered w-full @error('email') input-error @enderror"
                                   required>
                            <span>Email</span>
                        </label>
                        @error('email')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error wrap-break-word">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Password --}}
                        <label class="floating-label mb-6">
                            <input type="password"
                                   name="password"
                                   placeholder="Password"
                                   class="input input-bordered w-full @error('password') input-error @enderror"
                                   required>
                            <span>Password</span>
                        </label>
                        @error('password')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error truncate">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Password Confirmation --}}
                        <label class="floating-label mb-6">
                            <input type="password"
                                   name="password_confirmation"
                                   placeholder="Password Confirmation"
                                   class="input input-bordered w-full @error('password_confirmation') input-error @enderror"
                                   required>
                            <span>Confirm Password</span>
                        </label>

                        {{-- Submit Button --}}
                        <div class="form-control mt-8">
                            <button type="submit" class="btn btn-primary btn-sm w-full">
                                Register
                            </button>
                        </div>
                    </form>

                    <div class="divider">OR</div>
                    <p class="text-center text-sm">
                        Already have an account?
                        <a href="/login" class="link link-primary">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Cropper Modal --}}
    <x-modals.profile.crop />

</x-layout>


