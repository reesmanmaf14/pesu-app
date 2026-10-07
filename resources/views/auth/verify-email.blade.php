<x-guest-layout>
    <x-slot name="title">Verify your email</x-slot>

    <div>
        <h1 class="pesu-auth-title">Verify your email</h1>
        <p class="pesu-hint">
            {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <x-pesu.alert type="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-pesu.alert>
    @endif

    <div class="pesu-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="pesu-btn">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
