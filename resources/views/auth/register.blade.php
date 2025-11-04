
<x-layout>
    <x-slot:title>
        Register
    </x-slot:title>

    <div class="hero min-h-[calc(100vh-16rem)]">
        <div class="hero-content flex-col">
            <div class="card w-96 bg-base-100">
                <div class="card-body">
                    <h1 class="text-3xl font-bold text-center mb-6">Create Account</h1>

                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        {{-- Display Name --}}
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="display_name"
                                   placeholder="Display Name"
                                   value="{{ old('display_name') }}"
                                   class="input input-bordered @error('display_name') input-error @enderror"
                                   required>
                            <span>Display Name</span>
                        </label>
                        @error('display_name')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Username --}}
                        <label class="floating-label mb-6">
                            <input type="text"
                                   name="username"
                                   placeholder="Username"
                                   value="{{ old('username') }}"
                                   class="input input-bordered @error('username') input-error @enderror"
                                   required>
                            <span>Username</span>
                        </label>
                        @error('username')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Email --}}
                        <label class="floating-label mb-6">
                            <input type="email"
                                   name="email"
                                   placeholder="Email"
                                   value="{{ old('email') }}"
                                   class="input input-bordered @error('email') input-error @enderror"
                                   required>
                            <span>Email</span>
                        </label>
                        @error('email')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Password --}}
                        <label class="floating-label mb-6">
                            <input type="password"
                                   name="password"
                                   placeholder="Password"
                                   class="input input-bordered @error('password') input-error @enderror"
                                   required>
                            <span>Password</span>
                        </label>
                        @error('password')
                            <div class="label -mt-4 mb-2">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror

                        {{-- Password Confirmation --}}
                        <label class="floating-label mb-6">
                            <input type="password"
                                   name="password_confirmation"
                                   placeholder="Password Confirmation"
                                   class="input input-bordered @error('password_confirmation') input-error @enderror"
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
