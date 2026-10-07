<x-guest-layout :brand="false">
    <x-slot name="title">Create an account</x-slot>

    <div>
        <h1 class="pesu-auth-title">Create an account</h1>
        <p class="pesu-hint">After you register, the therapist approves your account before you can use the board.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="pesu-form">
        @csrf

        <!-- Name -->
        <div class="pesu-field">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <!-- Email Address -->
        <div class="pesu-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="pesu-field">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Confirm Password -->
        <div class="pesu-field">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="pesu-auth-actions">
            <x-primary-button>
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    <p class="pesu-auth-foot">
        {{ __('Already registered?') }}<br>
        <a class="pesu-link" href="{{ route('login') }}">Log in</a>
    </p>
</x-guest-layout>
