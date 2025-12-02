@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Password toggle functionality for login page
            const passwordInput = document.getElementById('login_password');
            const togglePassword = document.getElementById('toggle_login_password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', () => {
                    const type = passwordInput.type === 'password' ? 'text' : 'password';
                    passwordInput.type = type;

                    const eyeIcon = document.getElementById('login_eye_icon');
                    const eyeOffIcon = document.getElementById('login_eye_off_icon');

                    if (type === 'text') {
                        eyeIcon.classList.add('hidden');
                        eyeOffIcon.classList.remove('hidden');
                    } else {
                        eyeIcon.classList.remove('hidden');
                        eyeOffIcon.classList.add('hidden');
                    }
                });
            }
        });
    </script>
@endpush

{{-- @vite(['resources/js/auth/login.js']) --}}

<x-layout>
    <x-slot:title>
        Sign In
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)] py-8">
        <div class="hero-content flex-col w-full max-w-md px-4">
            <div class="card w-full bg-base-100 shadow-xl">
                <div class="card-body p-6 sm:p-8">
                    <h1 class="text-2xl sm:text-3xl font-bold text-center mb-4 sm:mb-6">Welcome Back</h1>

                    <form method="POST" action="/login">
                        @csrf

                        {{-- Username --}}
                        <div class="mb-4">
                            <label class="floating-label">
                                <input type="text"
                                       name="username"
                                       placeholder="Username"
                                       value="{{ old('username') }}"
                                       class="input input-bordered w-full @error('username') input-error @enderror"
                                       required
                                       autofocus>
                                <span>Username</span>
                            </label>
                            @error('username')
                                <div class="label">
                                    <span class="label-text-alt text-error text-xs">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="mb-4">
                            <label class="floating-label relative">
                                <input type="password"
                                       id="login_password"
                                       name="password"
                                       placeholder="Password"
                                       class="input input-bordered w-full pr-10 @error('password') input-error @enderror"
                                       required>
                                <span>Password</span>
                                <button type="button"
                                        id="toggle_login_password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/50 hover:text-base-content transition-colors"
                                        aria-label="Toggle password visibility">
                                    <i data-lucide="eye" class="w-5 h-5" id="login_eye_icon"></i>
                                    <i data-lucide="eye-off" class="w-5 h-5 hidden" id="login_eye_off_icon"></i>
                                </button>
                            </label>
                            @error('password')
                                <div class="label">
                                    <span class="label-text-alt text-error text-xs">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        {{-- Remember Me --}}
                        <div class="form-control mb-6">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox"
                                       name="remember"
                                       value="1"
                                       class="checkbox checkbox-sm"
                                       {{ old('remember') ? 'checked' : '' }}>
                                <span class="label-text text-sm">Remember me</span>
                            </label>
                        </div>

                        {{-- Submit Button --}}
                        <div class="form-control">
                            <button type="submit" class="btn btn-primary w-full">
                                <i data-lucide="log-in" class="w-4 h-4 mr-2"></i>
                                Sign In
                            </button>
                        </div>
                    </form>

                    <div class="divider my-4 sm:my-6">OR</div>
                    <p class="text-center text-sm">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="link link-primary font-medium">Create account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layout>
