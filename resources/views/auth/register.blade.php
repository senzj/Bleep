@vite([
    'resources/js/auth/register.js',
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

                    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- Hidden timezone field --}}
                        <input type="hidden" name="timezone" id="timezone" value="UTC">

                        {{-- Hidden profile picture data --}}
                        <input type="hidden" name="profile_picture" id="profile_picture_data">

                        {{-- User Profile Picture --}}
                        <div class="form-control mb-6">
                            <label class="label justify-center">
                                <span class="label-text font-medium">Profile Picture</span>
                            </label>
                            <div class="flex flex-col items-center gap-4">
                                <div class="relative group cursor-pointer" onclick="document.getElementById('profile_picture_input').click()">
                                    <div class="avatar">
                                        <div class="w-32 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 transition-all group-hover:ring-offset-4">
                                            <img id="profile_picture_preview"
                                                 src=""
                                                 alt="Profile Preview"
                                                 class="hidden w-full h-full rounded-full object-cover" />
                                            <div id="default_avatar" class="flex items-center justify-center h-full bg-base-300">
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

                                <button type="button"
                                        class="btn btn-sm btn-outline btn-primary"
                                        onclick="document.getElementById('profile_picture_input').click()">

                                    <i data-lucide="upload" class="w-4 h-4 mr-1"></i>
                                    Upload Photo
                                </button>
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


 </x-layout>

{{-- Cropper Modal --}}
<input type="checkbox" id="cropper_modal" class="modal-toggle" />
<div class="modal">
  <div class="modal-box p-0 bg-base-100 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
    <div class="p-4 sm:p-6">
      <h3 class="font-semibold text-lg mb-2">Crop Profile Picture</h3>
      <p class="text-sm text-base-content/70 mb-4">Adjust the image inside the square. Grid shows rule-of-thirds (3×3).</p>

      <div id="cropper_container" class="relative w-full aspect-square overflow-hidden bg-base-300 rounded-lg">
        <img id="cropper_image" src="" alt="Crop" class="max-w-full block mx-auto select-none">
      </div>

      <div class="flex justify-between items-center gap-3 mt-6">
        <div class="text-sm text-base-content/60">Preview updates the profile circle after saving.</div>
        <div class="flex justify-end gap-3">
          <label for="cropper_modal" id="cancel_crop" class="btn btn-ghost btn-sm">Cancel</label>
          <button id="crop_button" type="button" class="btn btn-primary btn-sm">Crop & Save</button>
        </div>
      </div>
    </div>
  </div>
</div>
