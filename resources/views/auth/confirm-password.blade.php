<x-guest-layout>
    <x-slot name="title">Confirm your password</x-slot>

    <div>
        <h1 class="pesu-auth-title">Confirm your password</h1>
        <p class="pesu-hint">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="pesu-form">
        @csrf

        <!-- Password -->
        <div class="pesu-field">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="pesu-actions">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
