<x-guest-layout :brand="false">
    <x-slot name="title">Log in</x-slot>

    <div>
        <h1 class="pesu-auth-title">Log in</h1>
        <p class="pesu-hint">Welcome back to the talking board.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="pesu-form">
        @csrf

        <!-- Email Address -->
        <div class="pesu-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="pesu-field">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Remember Me -->
        <label for="remember_me" class="pesu-check">
            <input id="remember_me" type="checkbox" name="remember">
            <span>{{ __('Remember me') }}</span>
        </label>

        <div class="pesu-auth-actions">
            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>

            @if (Route::has('password.request'))
                <a class="pesu-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>
    </form>

    @if (Route::has('register'))
        <p class="pesu-auth-foot">
            New to Pesu?<br>
            <a class="pesu-link" href="{{ route('register') }}">Create an account</a>
        </p>
    @endif
</x-guest-layout>
