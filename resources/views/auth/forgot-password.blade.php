<x-guest-layout :brand="false">
    <x-slot name="title">Reset your password</x-slot>

    <div>
        <h1 class="pesu-auth-title">Reset your password</h1>
        <p class="pesu-hint">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="pesu-form">
        @csrf

        <!-- Email Address -->
        <div class="pesu-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="pesu-auth-actions">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>

    <p class="pesu-auth-foot"><a class="pesu-link" href="{{ route('login') }}">Back to log in</a></p>
</x-guest-layout>
